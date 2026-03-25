<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    /**
     * Embed a single row after create/update.
     * Runs in background-safe way — silently fails if embedding is not configured.
     */
    public static function embedRow(string $table, int $rowId): void
    {
        try {
            $config = self::getConfig();
            if (!$config) return;

            $row = DB::table($table)->where('id', $rowId)->first();
            if (!$row) return;

            $text = self::buildContent($table, (array) $row);
            if (!$text) return;

            $hash = hash('sha256', $text);

            $existing = DB::table('embeddings')
                ->where('table_name', $table)
                ->where('row_id', $rowId)
                ->first();

            if ($existing && $existing->content_hash === $hash) {
                return; // Content unchanged
            }

            $vector = self::callApi($config, $text);
            if (!$vector) return;

            $vectorJson = json_encode($vector);

            if ($existing) {
                DB::table('embeddings')
                    ->where('table_name', $table)
                    ->where('row_id', $rowId)
                    ->update([
                        'vector' => $vectorJson,
                        'content_hash' => $hash,
                        'created_at' => now(),
                    ]);
            } else {
                DB::table('embeddings')->insert([
                    'table_name' => $table,
                    'row_id' => $rowId,
                    'content_hash' => $hash,
                    'vector' => $vectorJson,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Embedding failed for ' . $table . ':' . $rowId . ' - ' . $e->getMessage());
        }
    }

    /**
     * Remove embedding for a deleted row.
     */
    public static function deleteRow(string $table, int $rowId): void
    {
        try {
            DB::table('embeddings')
                ->where('table_name', $table)
                ->where('row_id', $rowId)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Embedding delete failed for ' . $table . ':' . $rowId . ' - ' . $e->getMessage());
        }
    }

    /** Queries to fetch all embeddable rows per table */
    private static array $tableQueries = [
        'articles'    => 'SELECT id, title, description FROM articles WHERE enable = 1',
        'projects'    => 'SELECT id, title, description FROM projects WHERE enable = 1',
        'experience'  => 'SELECT id, title, period, description FROM experience',
        'service'     => 'SELECT id, title, description FROM service',
        'skill'       => 'SELECT id, title, type FROM skill',
        'testimonial' => 'SELECT id, name, company, description FROM testimonial',
    ];

    /**
     * Embed all content across all tables.
     * Returns stats: embedded, skipped, errors.
     */
    public static function embedAll(): array
    {
        $config = self::getConfig();
        if (!$config) {
            return ['embedded' => 0, 'skipped' => 0, 'errors' => ['Embedding not configured']];
        }

        $embedded = 0;
        $skipped = 0;
        $errors = [];

        foreach (self::$tableQueries as $table => $sql) {
            $rows = DB::select($sql);

            foreach ($rows as $row) {
                try {
                    $rowArr = (array) $row;
                    $text = self::buildContent($table, $rowArr);
                    if (!$text) continue;

                    $hash = hash('sha256', $text);
                    $rowId = $rowArr['id'];

                    $existing = DB::table('embeddings')
                        ->where('table_name', $table)
                        ->where('row_id', $rowId)
                        ->first();

                    if ($existing && $existing->content_hash === $hash) {
                        $skipped++;
                        continue;
                    }

                    $vector = self::callApi($config, $text);
                    if (!$vector) {
                        $errors[] = "$table:$rowId: API returned no vector";
                        continue;
                    }

                    $vectorJson = json_encode($vector);

                    if ($existing) {
                        DB::table('embeddings')
                            ->where('table_name', $table)
                            ->where('row_id', $rowId)
                            ->update([
                                'vector' => $vectorJson,
                                'content_hash' => $hash,
                                'created_at' => now(),
                            ]);
                    } else {
                        DB::table('embeddings')->insert([
                            'table_name' => $table,
                            'row_id' => $rowId,
                            'content_hash' => $hash,
                            'vector' => $vectorJson,
                            'created_at' => now(),
                        ]);
                    }
                    $embedded++;
                } catch (\Throwable $e) {
                    $errors[] = "$table:{$rowArr['id']}: {$e->getMessage()}";
                }
            }

            // Clean up embeddings for deleted rows
            $rowIds = array_map(fn($r) => $r->id, $rows);
            if (count($rowIds) > 0) {
                DB::table('embeddings')
                    ->where('table_name', $table)
                    ->whereNotIn('row_id', $rowIds)
                    ->delete();
            } else {
                DB::table('embeddings')
                    ->where('table_name', $table)
                    ->delete();
            }
        }

        return compact('embedded', 'skipped', 'errors');
    }

    private static function getConfig(): ?array
    {
        $general = DB::table('page_setting')->where('id', 1)->first();
        if (!$general) return null;

        $url = trim($general->embedding_url ?? '');
        $key = trim($general->embedding_key ?? '');
        $model = trim($general->embedding_model ?? '');

        if (!$url || !$key || !$model) return null;

        return compact('url', 'key', 'model');
    }

    private static function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function buildContent(string $table, array $row): string
    {
        $s = fn(string $field) => self::stripHtml($row[$field] ?? '');

        return match ($table) {
            'articles'    => $s('title') . "\n" . $s('description'),
            'projects'    => $s('title') . "\n" . $s('description'),
            'experience'  => $s('title') . "\n" . $s('period') . "\n" . $s('description'),
            'service'     => $s('title') . "\n" . $s('description'),
            'skill'       => $s('title') . ' ' . $s('type'),
            'testimonial' => $s('name') . ' ' . $s('company') . "\n" . $s('description'),
            default       => '',
        };
    }

    private static function callApi(array $config, string $text): ?array
    {
        $baseUrl = rtrim($config['url'], '/');
        if (!str_ends_with($baseUrl, '/embeddings')) {
            $baseUrl .= '/embeddings';
        }

        $res = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withToken($config['key'])
            ->post($baseUrl, [
                'model' => $config['model'],
                'input' => $text,
            ]);

        if (!$res->successful()) {
            Log::warning('Embedding API error: HTTP ' . $res->status());
            return null;
        }

        return data_get($res->json(), 'data.0.embedding');
    }
}
