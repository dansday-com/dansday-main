<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
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

            $existingHash = DB::table('embeddings')
                ->where('table_name', $table)
                ->where('row_id', $rowId)
                ->where('chunk_index', 0)
                ->value('content_hash');

            if ($existingHash === $hash) return;

            DB::table('embeddings')
                ->where('table_name', $table)
                ->where('row_id', $rowId)
                ->delete();

            $chunks = self::chunkText($text);
            foreach ($chunks as $index => $chunk) {
                $vector = self::callApi($config, $chunk);
                if (!$vector) continue;
                DB::table('embeddings')->insert([
                    'table_name'  => $table,
                    'row_id'      => $rowId,
                    'chunk_index' => $index,
                    'content_hash' => $hash,
                    'vector'      => json_encode($vector),
                    'created_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Embedding failed for ' . $table . ':' . $rowId . ' - ' . $e->getMessage());
        }
    }

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

    private static array $tableQueries = [
        'articles'        => 'SELECT id, title, description FROM articles WHERE enable = 1',
        'projects'        => 'SELECT id, title, description FROM projects WHERE enable = 1',
        'experience'      => 'SELECT id, title, period, description FROM experience',
        'service'         => 'SELECT id, title, description FROM service',
        'skill'           => 'SELECT id, title, type FROM skill',
        'testimonial'     => 'SELECT id, name, company, description FROM testimonial',
        'github_activity' => 'SELECT id, repo, title, type FROM github_activity',
    ];

    private static array $tableMissingQueries = [
        'articles' => "SELECT a.id, a.title, a.description FROM articles a LEFT JOIN embeddings e ON e.table_name = 'articles' AND e.row_id = a.id AND e.chunk_index = 0 WHERE a.enable = 1 AND e.id IS NULL",
        'projects' => "SELECT p.id, p.title, p.description FROM projects p LEFT JOIN embeddings e ON e.table_name = 'projects' AND e.row_id = p.id AND e.chunk_index = 0 WHERE p.enable = 1 AND e.id IS NULL",
        'experience' => "SELECT x.id, x.title, x.period, x.description FROM experience x LEFT JOIN embeddings e ON e.table_name = 'experience' AND e.row_id = x.id AND e.chunk_index = 0 WHERE e.id IS NULL",
        'service' => "SELECT s.id, s.title, s.description FROM service s LEFT JOIN embeddings e ON e.table_name = 'service' AND e.row_id = s.id AND e.chunk_index = 0 WHERE e.id IS NULL",
        'skill' => "SELECT s.id, s.title, s.type FROM skill s LEFT JOIN embeddings e ON e.table_name = 'skill' AND e.row_id = s.id AND e.chunk_index = 0 WHERE e.id IS NULL",
        'testimonial' => "SELECT t.id, t.name, t.company, t.description FROM testimonial t LEFT JOIN embeddings e ON e.table_name = 'testimonial' AND e.row_id = t.id AND e.chunk_index = 0 WHERE e.id IS NULL",
        'github_activity' => "SELECT g.id, g.repo, g.title, g.type FROM github_activity g LEFT JOIN embeddings e ON e.table_name = 'github_activity' AND e.row_id = g.id AND e.chunk_index = 0 WHERE e.id IS NULL",
    ];

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

                    $existingHash = DB::table('embeddings')
                        ->where('table_name', $table)
                        ->where('row_id', $rowId)
                        ->where('chunk_index', 0)
                        ->value('content_hash');

                    if ($existingHash === $hash) {
                        $skipped++;
                        continue;
                    }

                    $chunks = self::chunkText($text);
                    foreach ($chunks as $chunkIndex => $chunk) {
                        $pending[] = ['rowId' => $rowId, 'chunkIndex' => $chunkIndex, 'text' => $chunk, 'hash' => $hash, 'isFirst' => $chunkIndex === 0];
                    }
                } catch (\Throwable $e) {
                    $errors[] = "$table:{$rowArr['id']}: {$e->getMessage()}";
                }
            }

            $rowIdsToDelete = array_unique(array_map(fn($item) => $item['rowId'], array_filter($pending, fn($item) => $item['isFirst'])));
            if ($rowIdsToDelete) {
                DB::table('embeddings')->where('table_name', $table)->whereIn('row_id', $rowIdsToDelete)->delete();
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

                        DB::table('embeddings')->insert([
                            'table_name'   => $table,
                            'row_id'       => $item['rowId'],
                            'chunk_index'  => $item['chunkIndex'],
                            'content_hash' => $item['hash'],
                            'vector'       => json_encode($vector),
                            'created_at'   => now(),
                        ]);
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

    public static function embedMissing(int $maxRows = 1, bool $pruneOrphans = true): array
    {
        $config = self::getConfig();
        if (!$config) {
            return ['embedded' => 0, 'errors' => []];
        }

        $embedded = 0;
        $errors = [];
        $remaining = max(0, $maxRows);

        foreach (self::$tableMissingQueries as $table => $sql) {
            if ($remaining <= 0) {
                break;
            }

            try {
                $rows = DB::select($sql.' LIMIT ?', [$remaining]);
            } catch (\Throwable $e) {
                $errors[] = "{$table}: query failed – {$e->getMessage()}";

                continue;
            }

            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $rowArr = (array) $row;
                try {
                    $text = self::buildContent($table, $rowArr);
                    if (! $text) {
                        continue;
                    }
                    $hash = hash('sha256', $text);
                    $rowId = (int) ($rowArr['id'] ?? 0);
                    if ($rowId <= 0) {
                        continue;
                    }

                    $chunks = self::chunkText($text);
                    $allOk = true;
                    foreach ($chunks as $chunkIndex => $chunk) {
                        $vector = self::callApi($config, $chunk);
                        if (! $vector) {
                            $errors[] = "{$table}:{$rowId}: No vector returned for chunk {$chunkIndex}";
                            $allOk = false;
                            break;
                        }
                        DB::table('embeddings')->insert([
                            'table_name'   => $table,
                            'row_id'       => $rowId,
                            'chunk_index'  => $chunkIndex,
                            'content_hash' => $hash,
                            'vector'       => json_encode($vector),
                            'created_at'   => now(),
                        ]);
                    }
                    if ($allOk) {
                        $embedded++;
                        $remaining--;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $table.':'.($rowArr['id'] ?? '?').': '.$e->getMessage();
                }
            }
        }

        if ($pruneOrphans) {
            try {
                self::pruneOrphanEmbeddings();
            } catch (\Throwable $e) {
                $errors[] = 'prune: '.$e->getMessage();
            }
        }

        return compact('embedded', 'errors');
    }

    public static function pruneOrphanEmbeddings(): void
    {
        foreach (array_keys(self::$tableQueries) as $table) {
            try {
                DB::statement(
                    'DELETE e FROM embeddings e LEFT JOIN `'.$table.'` t ON t.id = e.row_id WHERE e.table_name = ? AND t.id IS NULL',
                    [$table]
                );
            } catch (\Throwable $e) {
                Log::warning('Embedding prune failed for '.$table.': '.$e->getMessage());
            }
        }
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

    private static function chunkText(string $text, int $chunkSize = 500, int $overlap = 50): array
    {
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $len = mb_strlen($text);
        $step = $chunkSize - $overlap;
        for ($i = 0; $i < $len; $i += $step) {
            $chunks[] = mb_substr($text, $i, $chunkSize);
            if ($i + $chunkSize >= $len) break;
        }
        return $chunks;
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
        $endpoint = rtrim($config['url'], '/');
        if (!str_ends_with($endpoint, '/embeddings')) {
            $endpoint .= '/embeddings';
        }

        $res = Http::timeout(60)
            ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->withToken($config['key'])
            ->post($endpoint, ['model' => $config['model'], 'input' => $texts]);

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
        $endpoint = rtrim($config['url'], '/');
        if (!str_ends_with($endpoint, '/embeddings')) {
            $endpoint .= '/embeddings';
        }

        $res = Http::timeout(30)
            ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->withToken($config['key'])
            ->post($endpoint, ['model' => $config['model'], 'input' => $text]);

        if (!$res->successful()) {
            Log::warning('Embedding API error: HTTP ' . $res->status());
            return null;
        }

        return data_get($res->json(), 'data.0.embedding');
    }
}
