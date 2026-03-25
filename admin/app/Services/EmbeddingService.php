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
        'articles'        => 'SELECT id, title, description FROM articles WHERE enable = 1',
        'projects'        => 'SELECT id, title, description FROM projects WHERE enable = 1',
        'experience'      => 'SELECT id, title, period, description FROM experience',
        'service'         => 'SELECT id, title, description FROM service',
        'skill'           => 'SELECT id, title, type FROM skill',
        'testimonial'     => 'SELECT id, name, company, description FROM testimonial',
        'github_activity' => 'SELECT id, repo, title, type FROM github_activity',
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

            $pending = [];
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

                    $pending[] = ['rowId' => $rowId, 'text' => $text, 'hash' => $hash, 'existing' => $existing];
                } catch (\Throwable $e) {
                    $errors[] = "$table:{$rowArr['id']}: {$e->getMessage()}";
                }
            }

            $batchSize = 20;
            foreach (array_chunk($pending, $batchSize) as $batch) {
                try {
                    $texts = array_map(fn($item) => $item['text'], $batch);
                    $vectors = self::callApiBatch($config, $texts);
                    if (!$vectors) {
                        foreach ($batch as $item) {
                            $errors[] = "$table:{$item['rowId']}: Batch API returned no vectors";
                        }
                        continue;
                    }

                    foreach ($batch as $i => $item) {
                        $vector = $vectors[$i] ?? null;
                        if (!$vector) {
                            $errors[] = "$table:{$item['rowId']}: No vector in batch response";
                            continue;
                        }

                        $vectorJson = json_encode($vector);
                        if ($item['existing']) {
                            DB::table('embeddings')
                                ->where('table_name', $table)
                                ->where('row_id', $item['rowId'])
                                ->update([
                                    'vector' => $vectorJson,
                                    'content_hash' => $item['hash'],
                                    'created_at' => now(),
                                ]);
                        } else {
                            DB::table('embeddings')->insert([
                                'table_name' => $table,
                                'row_id' => $item['rowId'],
                                'content_hash' => $item['hash'],
                                'vector' => $vectorJson,
                                'created_at' => now(),
                            ]);
                        }
                        $embedded++;
                    }
                } catch (\Throwable $e) {
                    foreach ($batch as $item) {
                        $errors[] = "$table:{$item['rowId']}: {$e->getMessage()}";
                    }
                }
            }

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
            'articles'        => "Article: " . $s('title') . "\n" . $s('description'),
            'projects'        => "Project: " . $s('title') . "\n" . $s('description'),
            'experience'      => "Experience: " . $s('title') . " (" . $s('period') . ")\n" . $s('description'),
            'service'         => "Service: " . $s('title') . "\n" . $s('description'),
            'skill'           => "Skill: " . $s('title') . " (" . $s('type') . ")",
            'testimonial'     => "Testimonial from " . $s('name') . " at " . $s('company') . ": " . $s('description'),
            'github_activity' => "GitHub " . $s('type') . " in " . $s('repo') . ": " . $s('title'),
            default           => '',
        };
    }

    public static function countPending(): int
    {
        $total = 0;
        foreach (self::$tableQueries as $sql) {
            $total += count(DB::select($sql));
        }
        return $total;
    }

    public static function getStatus(): array
    {
        $embedded = DB::table('embeddings')->count();
        $total = self::countPending();
        return ['embedded' => $embedded, 'total' => $total];
    }

    private static function callApiBatch(array $config, array $texts): ?array
    {
        $baseUrl = rtrim($config['url'], '/');
        if (!str_ends_with($baseUrl, '/embeddings')) {
            $baseUrl .= '/embeddings';
        }

        $res = Http::timeout(60)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withToken($config['key'])
            ->post($baseUrl, [
                'model' => $config['model'],
                'input' => $texts,
            ]);

        if (!$res->successful()) {
            Log::warning('Embedding batch API error: HTTP ' . $res->status());
            return null;
        }

        $data = $res->json('data');
        if (!is_array($data)) return null;

        $vectors = [];
        foreach ($data as $item) {
            $vectors[] = $item['embedding'] ?? null;
        }
        return $vectors;
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
