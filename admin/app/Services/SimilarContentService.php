<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SimilarContentService
{
    private static array $searchTables = [
        'articles' => [
            'select' => 'SELECT id, title, description, created_at',
            'ftFields' => ['title', 'description'],
            'where' => 'enable = 1',
            'label' => 'Article',
        ],
        'projects' => [
            'select' => 'SELECT id, title, description, created_at',
            'ftFields' => ['title', 'description'],
            'where' => 'enable = 1',
            'label' => 'Project',
        ],
        'github_activity' => [
            'select' => 'SELECT id, repo, title, type, additions, deletions, created_at',
            'ftFields' => ['repo', 'title'],
            'where' => '1=1',
            'label' => 'GitHub',
            'ftScoreOnly' => false,
        ],
    ];

    public static function find(string $type, string $text, int $limit = 10): array
    {
        $text = trim($text);
        if ($text === '') return [];

        $bm25ByTable = self::bm25SearchAll($text);
        $semanticByTable = self::semanticSearchAll($text);

        $results = [];
        foreach (self::$searchTables as $table => $cfg) {
            $bm25 = $bm25ByTable[$table] ?? [];
            $semantic = $semanticByTable[$table] ?? [];
            $merged = self::rrfMerge($bm25, $semantic);

            foreach ($merged as $row) {
                $row['_table'] = $table;
                $results[] = $row;
            }
        }

        usort($results, fn($a, $b) => ($b['_rrfScore'] ?? 0) <=> ($a['_rrfScore'] ?? 0));

        return array_slice($results, 0, $limit);
    }

    private static function buildFtQuery(string $text): string
    {
        $words = preg_split('/[\s\-]+/', $text);
        $words = array_filter($words, fn($w) => strlen($w) > 0);
        $words = array_map(fn($w) => preg_replace('/[+><~*"@()]/', '', $w), $words);
        $words = array_filter($words, fn($w) => strlen($w) > 0);
        if (empty($words)) return '';
        return implode(' ', array_map(fn($w) => $w . '*', array_slice($words, 0, 20)));
    }

    private static function bm25SearchAll(string $text): array
    {
        $ftQuery = self::buildFtQuery($text);
        if ($ftQuery === '') return [];

        $results = [];
        foreach (self::$searchTables as $table => $cfg) {
            try {
                $fields = implode(', ', $cfg['ftFields']);
                $matchExpr = "MATCH({$fields}) AGAINST(? IN BOOLEAN MODE)";
                $scoreOnly = $cfg['ftScoreOnly'] ?? true;

                if ($scoreOnly !== false) {
                    $sql = "{$cfg['select']}, {$matchExpr} AS relevance FROM {$table} WHERE {$cfg['where']} AND {$matchExpr} ORDER BY relevance DESC LIMIT 20";
                    $params = [$ftQuery, $ftQuery];
                } else {
                    $sql = "{$cfg['select']} FROM {$table} WHERE {$cfg['where']} AND {$matchExpr} ORDER BY created_at DESC LIMIT 20";
                    $params = [$ftQuery];
                }

                $rows = DB::select($sql, $params);
                $results[$table] = array_map(fn($r) => (array) $r, $rows);
            } catch (\Throwable $e) {
                Log::warning("BM25 search failed for {$table}: " . $e->getMessage());
                $results[$table] = [];
            }
        }
        return $results;
    }

    private static function semanticSearchAll(string $text): array
    {
        try {
            $general = DB::table('page_setting')->where('id', 1)->first();
            if (!$general) return [];

            $url = trim($general->embedding_url ?? '');
            $key = trim($general->embedding_key ?? '');
            $model = trim($general->embedding_model ?? '');
            if (!$url || !$key || !$model) return [];

            $endpoint = rtrim($url, '/');
            if (!str_ends_with($endpoint, '/embeddings')) {
                $endpoint .= '/embeddings';
            }

            $res = Http::connectTimeout(5)->timeout(15)
                ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
                ->withToken($key)
                ->post($endpoint, ['model' => $model, 'input' => $text]);

            if (!$res->successful()) return [];

            $queryVector = data_get($res->json(), 'data.0.embedding');
            if (!$queryVector || !is_array($queryVector)) return [];

            $tableNames = array_keys(self::$searchTables);
            $best = [];
            DB::table('embeddings')
                ->whereIn('table_name', $tableNames)
                ->select('table_name', 'row_id', 'vector')
                ->orderBy('id')
                ->chunk(100, function ($rows) use ($queryVector, &$best) {
                    foreach ($rows as $emb) {
                        $vector = json_decode($emb->vector, true);
                        if (!$vector) continue;
                        $similarity = self::cosineSimilarity($queryVector, $vector);
                        if ($similarity < 0.5) continue;
                        $key = $emb->table_name . ':' . $emb->row_id;
                        if (!isset($best[$key]) || $similarity > $best[$key]['similarity']) {
                            $best[$key] = [
                                'table_name' => $emb->table_name,
                                'row_id'     => $emb->row_id,
                                'similarity' => $similarity,
                            ];
                        }
                    }
                });

            $scored = array_values($best);
            usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            $scored = array_slice($scored, 0, 50);

            $byTable = [];
            foreach ($scored as $s) {
                $byTable[$s['table_name']][] = $s;
            }

            $results = [];
            foreach ($byTable as $table => $items) {
                $ids = array_map(fn($s) => $s['row_id'], $items);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $cfg = self::$searchTables[$table];
                $rows = DB::select("{$cfg['select']} FROM {$table} WHERE id IN ({$placeholders})", $ids);

                $rowMap = [];
                foreach ($rows as $r) {
                    $rowMap[$r->id] = (array) $r;
                }

                $results[$table] = [];
                foreach ($items as $s) {
                    if (isset($rowMap[$s['row_id']])) {
                        $results[$table][] = array_merge($rowMap[$s['row_id']], ['similarity' => $s['similarity']]);
                    }
                }
            }
            return $results;
        } catch (\Throwable $e) {
            Log::warning('Semantic search failed: ' . $e->getMessage());
            return [];
        }
    }

    private static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0;
        $normA = 0;
        $normB = 0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        $denom = sqrt($normA) * sqrt($normB);
        return $denom === 0.0 ? 0.0 : $dot / $denom;
    }

    private static function rrfMerge(array $bm25Results, array $semanticResults): array
    {
        $K = 40;
        $scores = [];
        $data = [];

        foreach ($bm25Results as $rank => $row) {
            $key = $row['id'];
            $scores[$key] = ($scores[$key] ?? 0) + 1 / ($K + $rank + 1);
            $data[$key] = $row;
        }

        foreach ($semanticResults as $rank => $row) {
            $key = $row['id'];
            $scores[$key] = ($scores[$key] ?? 0) + 2.0 * (1 / ($K + $rank + 1));
            if (!isset($data[$key])) {
                $data[$key] = $row;
            }
        }

        arsort($scores);

        $merged = [];
        foreach ($scores as $id => $score) {
            $entry = $data[$id];
            $entry['_rrfScore'] = $score;
            $merged[] = $entry;
        }
        return $merged;
    }

    public static function formatContext(array $results, string $type): string
    {
        if (empty($results)) return '';

        $grouped = [];
        foreach ($results as $row) {
            $table = $row['_table'] ?? '';
            $grouped[$table][] = $row;
        }

        $sections = [];

        if (!empty($grouped['articles'])) {
            $items = [];
            foreach ($grouped['articles'] as $row) {
                $items[] = [
                    'title' => strip_tags($row['title'] ?? ''),
                    'description' => self::stripHtml($row['description'] ?? ''),
                    'created_at' => $row['created_at'] ?? '',
                ];
            }
            $sections['articles'] = $items;
        }

        if (!empty($grouped['projects'])) {
            $items = [];
            foreach ($grouped['projects'] as $row) {
                $items[] = [
                    'title' => strip_tags($row['title'] ?? ''),
                    'description' => self::stripHtml($row['description'] ?? ''),
                    'created_at' => $row['created_at'] ?? '',
                ];
            }
            $sections['projects'] = $items;
        }

        if (!empty($grouped['github_activity'])) {
            $items = [];
            foreach ($grouped['github_activity'] as $row) {
                $item = [
                    'repo' => strip_tags($row['repo'] ?? ''),
                    'title' => strip_tags($row['title'] ?? ''),
                    'type' => $row['type'] ?? '',
                    'date' => $row['created_at'] ?? '',
                ];
                if (($row['additions'] ?? null) !== null) $item['additions'] = $row['additions'];
                if (($row['deletions'] ?? null) !== null) $item['deletions'] = $row['deletions'];
                $items[] = $item;
            }
            $sections['activity'] = $items;
        }

        if (empty($sections)) return '';

        return "Similar existing content for reference/follow-up:\n" . json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private static function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\s+/', ' ', trim($text));
    }
}
