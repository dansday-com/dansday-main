<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGenerateService
{
    private static function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search',
                    'description' => 'Search across all data: articles, projects, skills, experiences, services, testimonials, GitHub activity (commits, PRs, reviews, issues), and site info (social links, email, site URL). Supports keyword filtering and/or date filtering.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => 'Search keyword to match against titles and descriptions. Omit to return all results.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['article', 'project', 'commit', 'pr', 'review', 'issue', 'skill', 'experience', 'service', 'testimonial'],
                                'description' => 'Filter by data type. Omit to search all types.',
                            ],
                            'startDate' => [
                                'type' => 'string',
                                'description' => 'Filter results from this date (YYYY-MM-DD).',
                            ],
                            'endDate' => [
                                'type' => 'string',
                                'description' => 'Filter results up to this date (YYYY-MM-DD).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'count',
                    'description' => 'Count data items (articles, projects, skills, experiences, services, testimonials, GitHub activity). Use this when you need totals or numbers. Returns counts grouped by type.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => 'Search keyword to filter results. Omit to count all.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['article', 'project', 'commit', 'pr', 'review', 'issue', 'skill', 'experience', 'service', 'testimonial'],
                                'description' => 'Filter by data type. Omit to count all types.',
                            ],
                            'startDate' => [
                                'type' => 'string',
                                'description' => 'Count from this date (YYYY-MM-DD).',
                            ],
                            'endDate' => [
                                'type' => 'string',
                                'description' => 'Count up to this date (YYYY-MM-DD).',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function generate(string $type, string $topic): array
    {
        $general = DB::table('page_setting')->where('id', 1)->first();
        if (!$general) {
            return ['error' => __('content.ai_unavailable')];
        }

        $url = trim($general->ai_url ?? '');
        $key = trim($general->ai_key ?? '');
        $model = trim($general->ai_content_model ?? '') ?: trim($general->ai_model ?? '') ?: 'default';

        if (!$url || !$key) {
            return ['error' => __('content.ai_unavailable')];
        }

        $context = trim($topic);
        $prompt = $context !== '' ? $context : 'Generate content.';

        $systemPrompt = self::resolvePrompt($general, $type);
        $reasoning = self::resolveReasoning($general);

        $endpoint = rtrim($url, '/');
        if (!str_ends_with($endpoint, '/chat/completions')) {
            $endpoint .= '/chat/completions';
        }

        $embeddingClient = self::buildEmbeddingClient($general);
        $section = self::getEnabledSections();

        try {
            $messages = [];
            if ($systemPrompt !== '') {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $tools = self::toolDefinitions();
            $baseParams = self::buildModelParams($model, $reasoning);

            for ($i = 0; $i < 10; $i++) {
                $body = array_filter([
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                    ...$baseParams,
                ], fn($v) => $v !== null);

                $res = Http::connectTimeout(10)->timeout(600)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->withToken($key)
                    ->post($endpoint, $body);

                if (!$res->successful()) {
                    Log::error('AI generate HTTP failed', [
                        'endpoint' => $endpoint,
                        'status' => $res->status(),
                        'model' => $model,
                        'body' => substr($res->body(), 0, 500),
                    ]);
                    return ['error' => __('content.ai_unavailable')];
                }

                $json = $res->json();
                $message = data_get($json, 'choices.0.message');
                if (!$message) break;

                $toolCalls = $message['tool_calls'] ?? [];
                if (empty($toolCalls)) {
                    $text = trim((string) ($message['content'] ?? ''));
                    if ($text === '') {
                        $text = trim((string) data_get($json, 'choices.0.text', ''));
                    }
                    return ['text' => $text];
                }

                $messages[] = $message;

                foreach ($toolCalls as $tc) {
                    $toolName = $tc['function']['name'] ?? '';
                    $toolArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                    $result = self::executeTool($toolName, $toolArgs, $section, $embeddingClient);
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc['id'],
                        'content' => $result,
                    ];
                }
            }

            $body = array_filter([
                'model' => $model,
                'messages' => $messages,
                ...$baseParams,
            ], fn($v) => $v !== null);

            $res = Http::connectTimeout(10)->timeout(600)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->withToken($key)
                ->post($endpoint, $body);

            if (!$res->successful()) {
                return ['error' => __('content.ai_unavailable')];
            }

            $text = trim((string) data_get($res->json(), 'choices.0.message.content', ''));
            return ['text' => $text];
        } catch (\Throwable $e) {
            Log::error('AI generate failed', [
                'model' => $model,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['error' => __('content.ai_unavailable')];
        }
    }

    private static function executeTool(string $name, array $args, array $section, ?array $embeddingClient): string
    {
        switch ($name) {
            case 'search':
                return self::toolSearch($args, $section, $embeddingClient);
            case 'count':
                return self::toolCount($args, $section);
            default:
                return '{}';
        }
    }

    private static function getEnabledSections(): array
    {
        try {
            $row = DB::table('section')->where('id', 1)->first();
            if (!$row) return [];
            return (array) $row;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function sectionOn(array $section, string $key): bool
    {
        return ($section[$key] ?? null) !== false && ($section[$key] ?? null) !== 0 && ($section[$key] ?? null) !== null;
    }

    private static function buildDateFilter(array $args): array
    {
        $conditions = [];
        $params = [];
        if (!empty($args['startDate'])) {
            $conditions[] = 'created_at >= ?';
            $params[] = $args['startDate'];
        }
        if (!empty($args['endDate'])) {
            $conditions[] = 'created_at <= ?';
            $params[] = $args['endDate'] . ' 23:59:59';
        }
        $clause = count($conditions) > 0 ? ' AND ' . implode(' AND ', $conditions) : '';
        return ['clause' => $clause, 'params' => $params];
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

    private static function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\s+/', ' ', trim($text));
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

    private static function embedQuery(?array $embeddingClient, string $text): ?array
    {
        if (!$embeddingClient) return null;

        $endpoint = rtrim($embeddingClient['url'], '/');
        if (!str_ends_with($endpoint, '/embeddings')) {
            $endpoint .= '/embeddings';
        }

        $res = Http::connectTimeout(5)->timeout(15)
            ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->withToken($embeddingClient['key'])
            ->post($endpoint, ['model' => $embeddingClient['model'], 'input' => $text]);

        if (!$res->successful()) return null;

        $vector = data_get($res->json(), 'data.0.embedding');
        return is_array($vector) ? $vector : null;
    }

    private static function semanticSearch(?array $embeddingClient, string $text): array
    {
        if (!$embeddingClient) return [];

        try {
            $queryVector = self::embedQuery($embeddingClient, $text);
            if (!$queryVector) return [];

            $scored = [];
            DB::table('embeddings')
                ->select('table_name', 'row_id', 'vector')
                ->orderBy('id')
                ->chunk(100, function ($rows) use ($queryVector, &$scored) {
                    foreach ($rows as $emb) {
                        $vector = json_decode($emb->vector, true);
                        if (!$vector) continue;
                        $similarity = self::cosineSimilarity($queryVector, $vector);
                        if ($similarity >= 0.3) {
                            $scored[] = [
                                'table_name' => $emb->table_name,
                                'row_id' => $emb->row_id,
                                'similarity' => $similarity,
                            ];
                        }
                    }
                });

            usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            return array_slice($scored, 0, 20);
        } catch (\Throwable $e) {
            Log::warning('Semantic search failed: ' . $e->getMessage());
            return [];
        }
    }

    private static function rrfSort(array $bm25Rows, array $semanticScoreMap, string $tableName, ?string $bm25Key = null): array
    {
        if (empty($bm25Rows)) return $bm25Rows;
        $K = 60;
        $sorted = [];
        foreach ($bm25Rows as $rank => $row) {
            $bm25Score = ($bm25Key && isset($row[$bm25Key])) ? 1 / ($K + $rank + 1) : 0;
            $semScore = $semanticScoreMap["{$tableName}:{$row['id']}"] ?? 0;
            $row['_rrfScore'] = $bm25Score + 1.5 * $semScore;
            $sorted[] = $row;
        }
        usort($sorted, fn($a, $b) => ($b['_rrfScore'] ?? 0) <=> ($a['_rrfScore'] ?? 0));
        return $sorted;
    }

    private static function mergeSemanticRows(array $existing, array $semanticHits, string $tableName, string $selectSql): array
    {
        $sIds = [];
        foreach ($semanticHits as $h) {
            if ($h['table_name'] === $tableName) {
                $sIds[] = $h['row_id'];
            }
        }
        if (empty($sIds)) return $existing;

        $existingIds = array_column($existing, 'id');
        $existingIdSet = array_flip($existingIds);
        $missingIds = array_filter($sIds, fn($id) => !isset($existingIdSet[$id]));
        if (empty($missingIds)) return $existing;

        $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
        $extra = DB::select("{$selectSql} WHERE id IN ({$placeholders})", array_values($missingIds));
        $extra = array_map(fn($r) => (array) $r, $extra);
        return array_merge($existing, $extra);
    }

    private static function toolSearch(array $args, array $section, ?array $embeddingClient): string
    {
        $rawKeyword = trim($args['keyword'] ?? '');
        $hasKeyword = $rawKeyword !== '';
        $df = self::buildDateFilter($args);
        $dateClause = $df['clause'];
        $dp = $df['params'];
        $ftQuery = self::buildFtQuery($rawKeyword);

        $t = $args['type'] ?? null;
        $ghTypes = ['commit', 'pr', 'review', 'issue'];
        $aboutTypes = ['skill', 'experience', 'service', 'testimonial'];
        $hasDateFilter = !empty($args['startDate']) || !empty($args['endDate']);

        $on = fn($key) => self::sectionOn($section, $key);
        $articlesOn = $on('articles_enable');
        $projectsOn = $on('projects_enable');
        $contributeOn = $on('contribute_enable');
        $aboutOn = $on('about_enable');
        $skillsOn = $aboutOn && $on('skills_enable');
        $experienceOn = $aboutOn && $on('experience_enable');
        $servicesOn = $aboutOn && $on('services_enable');
        $testimonialOn = $aboutOn && $on('testimonial_enable');

        $wantAll = !$t;
        $wantAbout = !$hasDateFilter || !$wantAll || in_array($t, $aboutTypes);
        $wantProjects = $projectsOn && (!$hasDateFilter || $t === 'project');
        $wantGh = $contributeOn && ($wantAll || in_array($t, $ghTypes));

        $semanticHits = [];
        $semanticScoreMap = [];
        if ($hasKeyword && $embeddingClient) {
            $semanticHits = self::semanticSearch($embeddingClient, $rawKeyword);
            $K = 60;
            foreach ($semanticHits as $rank => $h) {
                $semanticScoreMap["{$h['table_name']}:{$h['row_id']}"] = 1 / ($K + $rank + 1);
            }
        }

        $result = [];

        if ($articlesOn && ($wantAll || $t === 'article')) {
            try {
                $ftFilter = '';
                $ftParams = [];
                $scoreCol = '';
                if ($hasKeyword && $ftQuery !== '') {
                    $matchExpr = "MATCH(title, description) AGAINST(? IN BOOLEAN MODE)";
                    $ftFilter = " AND {$matchExpr}";
                    $scoreCol = ", {$matchExpr} AS relevance";
                    $ftParams = [$ftQuery, $ftQuery];
                }
                $rows = DB::select(
                    "SELECT id, title, description, created_at{$scoreCol} FROM articles WHERE enable = 1{$ftFilter}{$dateClause}" .
                    ($hasKeyword ? ' ORDER BY relevance DESC' : ' ORDER BY created_at DESC'),
                    [...$ftParams, ...$dp]
                );
                $rows = array_map(fn($r) => (array) $r, $rows);

                if ($hasKeyword) {
                    $rows = self::mergeSemanticRows($rows, $semanticHits, 'articles', 'SELECT id, title, description, created_at FROM articles');
                    $rows = self::rrfSort($rows, $semanticScoreMap, 'articles', 'relevance');
                }

                if (!empty($rows)) {
                    $result['articles'] = array_map(fn($r) => [
                        'title' => $r['title'],
                        'description' => self::stripHtml($r['description']),
                        'created_at' => $r['created_at'],
                    ], $rows);
                }
            } catch (\Throwable $e) {
                Log::warning('Search articles failed: ' . $e->getMessage());
            }
        }

        if ($wantProjects && ($wantAll || $t === 'project')) {
            try {
                $ftFilter = '';
                $ftParams = [];
                $scoreCol = '';
                if ($hasKeyword && $ftQuery !== '') {
                    $matchExpr = "MATCH(title, description) AGAINST(? IN BOOLEAN MODE)";
                    $ftFilter = " AND {$matchExpr}";
                    $scoreCol = ", {$matchExpr} AS relevance";
                    $ftParams = [$ftQuery, $ftQuery];
                }
                $rows = DB::select(
                    "SELECT id, title, description, category_id, created_at{$scoreCol} FROM projects WHERE enable = 1{$ftFilter}{$dateClause}" .
                    ($hasKeyword ? ' ORDER BY relevance DESC' : ' ORDER BY created_at DESC'),
                    [...$ftParams, ...$dp]
                );
                $rows = array_map(fn($r) => (array) $r, $rows);

                if ($hasKeyword) {
                    $rows = self::mergeSemanticRows($rows, $semanticHits, 'projects', 'SELECT id, title, description, category_id, created_at FROM projects');
                    $rows = self::rrfSort($rows, $semanticScoreMap, 'projects', 'relevance');
                }

                $catMap = [];
                try {
                    $cats = DB::select('SELECT id, name FROM project_categories ORDER BY id ASC');
                    foreach ($cats as $c) {
                        $catMap[$c->id] = $c->name;
                    }
                } catch (\Throwable $e) {}

                if (!empty($rows)) {
                    $result['projects'] = array_map(fn($r) => [
                        'title' => $r['title'],
                        'description' => self::stripHtml($r['description']),
                        'category' => $catMap[$r['category_id']] ?? null,
                        'created_at' => $r['created_at'],
                    ], $rows);
                }
            } catch (\Throwable $e) {
                Log::warning('Search projects failed: ' . $e->getMessage());
            }
        }

        if ($wantGh) {
            try {
                $ftFilter = '';
                $ftParams = [];
                if ($hasKeyword && $ftQuery !== '') {
                    $ftFilter = " AND MATCH(repo, title) AGAINST(? IN BOOLEAN MODE)";
                    $ftParams = [$ftQuery];
                }
                $typeFilter = '';
                $typeParams = [];
                if (!$wantAll && $t && in_array($t, $ghTypes)) {
                    $typeFilter = ' AND type = ?';
                    $typeParams = [$t];
                }
                $rows = DB::select(
                    "SELECT id, repo, title, type, additions, deletions, created_at FROM github_activity WHERE 1=1{$typeFilter}{$ftFilter}{$dateClause} ORDER BY created_at DESC",
                    [...$typeParams, ...$ftParams, ...$dp]
                );
                $rows = array_map(fn($r) => (array) $r, $rows);

                if ($hasKeyword) {
                    $bm25IdSet = array_flip(array_column($rows, 'id'));
                    $bm25RankMap = [];
                    foreach ($rows as $rank => $r) {
                        $bm25RankMap[$r['id']] = $rank;
                    }

                    $sIds = [];
                    foreach ($semanticHits as $h) {
                        if ($h['table_name'] === 'github_activity') {
                            $sIds[] = $h['row_id'];
                        }
                    }
                    if (!empty($sIds)) {
                        $missingIds = array_filter($sIds, fn($id) => !isset($bm25IdSet[$id]));
                        if (!empty($missingIds)) {
                            $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
                            $extra = DB::select(
                                "SELECT id, repo, title, type, additions, deletions, created_at FROM github_activity WHERE id IN ({$placeholders})",
                                array_values($missingIds)
                            );
                            $rows = array_merge($rows, array_map(fn($r) => (array) $r, $extra));
                        }
                    }

                    $K = 60;
                    $sorted = [];
                    foreach ($rows as $r) {
                        $bm25Rank = $bm25RankMap[$r['id']] ?? null;
                        $bm25Score = $bm25Rank !== null ? 1 / ($K + $bm25Rank + 1) : 0;
                        $semScore = $semanticScoreMap["github_activity:{$r['id']}"] ?? 0;
                        $r['_rrfScore'] = $bm25Score + 1.5 * $semScore;
                        $sorted[] = $r;
                    }
                    usort($sorted, fn($a, $b) => ($b['_rrfScore'] ?? 0) <=> ($a['_rrfScore'] ?? 0));
                    $rows = $sorted;
                }

                if (!empty($rows)) {
                    $result['activity'] = array_map(fn($r) => array_filter([
                        'repo' => $r['repo'],
                        'title' => $r['title'],
                        'type' => $r['type'],
                        'date' => $r['created_at'],
                        'additions' => $r['additions'],
                        'deletions' => $r['deletions'],
                    ], fn($v) => $v !== null), $rows);
                }
            } catch (\Throwable $e) {
                Log::warning('Search activity failed: ' . $e->getMessage());
            }
        }

        if ($wantAbout && $skillsOn && ($wantAll || $t === 'skill')) {
            try {
                $rows = DB::select('SELECT id, title, type FROM skill ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
                if ($hasKeyword) {
                    $rows = self::mergeSemanticRows($rows, $semanticHits, 'skill', 'SELECT id, title, type FROM skill');
                    $rows = self::rrfSort($rows, $semanticScoreMap, 'skill');
                }
                if (!empty($rows)) {
                    $result['skills'] = array_map(fn($r) => ['title' => $r['title'], 'type' => $r['type']], $rows);
                }
            } catch (\Throwable $e) {}
        }

        if ($wantAbout && $experienceOn && ($wantAll || $t === 'experience')) {
            try {
                $rows = DB::select('SELECT id, title, type, period, description FROM experience ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
                if ($hasKeyword) {
                    $rows = self::mergeSemanticRows($rows, $semanticHits, 'experience', 'SELECT id, title, type, period, description FROM experience');
                    $rows = self::rrfSort($rows, $semanticScoreMap, 'experience');
                }
                if (!empty($rows)) {
                    $result['experiences'] = array_map(fn($r) => [
                        'title' => $r['title'],
                        'type' => $r['type'],
                        'period' => $r['period'],
                        'description' => self::stripHtml($r['description']),
                    ], $rows);
                }
            } catch (\Throwable $e) {}
        }

        if ($wantAbout && $servicesOn && ($wantAll || $t === 'service')) {
            try {
                $rows = DB::select('SELECT id, title, description FROM service ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
                if ($hasKeyword) {
                    $rows = self::mergeSemanticRows($rows, $semanticHits, 'service', 'SELECT id, title, description FROM service');
                    $rows = self::rrfSort($rows, $semanticScoreMap, 'service');
                }
                if (!empty($rows)) {
                    $result['services'] = array_map(fn($r) => ['title' => $r['title'], 'description' => self::stripHtml($r['description'])], $rows);
                }
            } catch (\Throwable $e) {}
        }

        if ($wantAbout && $testimonialOn && ($wantAll || $t === 'testimonial')) {
            try {
                $rows = DB::select('SELECT id, name, company, description FROM testimonial ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
                if ($hasKeyword) {
                    $rows = self::mergeSemanticRows($rows, $semanticHits, 'testimonial', 'SELECT id, name, company, description FROM testimonial');
                    $rows = self::rrfSort($rows, $semanticScoreMap, 'testimonial');
                }
                if (!empty($rows)) {
                    $result['testimonials'] = array_map(fn($r) => ['name' => $r['name'], 'company' => $r['company'], 'description' => self::stripHtml($r['description'])], $rows);
                }
            } catch (\Throwable $e) {}
        }

        try {
            $general = DB::table('page_setting')->where('id', 1)->first();
            $home = DB::table('home')->where('id', 1)->first();
            $siteUrl = trim(config('app.url', ''), '/');
            $result['site'] = [
                'title' => $home->title ?? '',
                'description' => $home->description ?? '',
                'site_url' => $siteUrl,
                'social_links' => json_decode($general->social_links ?? '[]', true),
            ];
        } catch (\Throwable $e) {}

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function toolCount(array $args, array $section): string
    {
        $rawKeyword = trim($args['keyword'] ?? '');
        $hasKeyword = $rawKeyword !== '';
        $df = self::buildDateFilter($args);
        $dateClause = $df['clause'];
        $dp = $df['params'];
        $ftQuery = self::buildFtQuery($rawKeyword);

        $t = $args['type'] ?? null;
        $ghTypes = ['commit', 'pr', 'review', 'issue'];
        $aboutTypes = ['skill', 'experience', 'service', 'testimonial'];
        $wantAll = !$t;

        $on = fn($key) => self::sectionOn($section, $key);
        $aboutOn = $on('about_enable');

        $result = [];

        if ($wantAll || $t === 'article') {
            if ($on('articles_enable')) {
                try {
                    $ftFilter = $hasKeyword && $ftQuery !== '' ? " AND MATCH(title, description) AGAINST(? IN BOOLEAN MODE)" : '';
                    $ftParams = $hasKeyword && $ftQuery !== '' ? [$ftQuery] : [];
                    $rows = DB::select("SELECT COUNT(*) as cnt FROM articles WHERE enable = 1{$ftFilter}{$dateClause}", [...$ftParams, ...$dp]);
                    $result['articles'] = $rows[0]->cnt ?? 0;
                } catch (\Throwable $e) {}
            }
        }

        if ($wantAll || $t === 'project') {
            if ($on('projects_enable')) {
                try {
                    $ftFilter = $hasKeyword && $ftQuery !== '' ? " AND MATCH(title, description) AGAINST(? IN BOOLEAN MODE)" : '';
                    $ftParams = $hasKeyword && $ftQuery !== '' ? [$ftQuery] : [];
                    $rows = DB::select("SELECT COUNT(*) as cnt FROM projects WHERE enable = 1{$ftFilter}{$dateClause}", [...$ftParams, ...$dp]);
                    $result['projects'] = $rows[0]->cnt ?? 0;
                } catch (\Throwable $e) {}
            }
        }

        if ($wantAll || in_array($t, $ghTypes)) {
            if ($on('contribute_enable')) {
                try {
                    $ftFilter = $hasKeyword && $ftQuery !== '' ? " AND MATCH(repo, title) AGAINST(? IN BOOLEAN MODE)" : '';
                    $ftParams = $hasKeyword && $ftQuery !== '' ? [$ftQuery] : [];
                    $typeFilter = !$wantAll && $t && in_array($t, $ghTypes) ? ' AND type = ?' : '';
                    $typeParams = !$wantAll && $t && in_array($t, $ghTypes) ? [$t] : [];

                    $totalRows = DB::select(
                        "SELECT COUNT(*) as cnt FROM github_activity WHERE 1=1{$typeFilter}{$ftFilter}{$dateClause}",
                        [...$typeParams, ...$ftParams, ...$dp]
                    );
                    $byTypeRows = DB::select(
                        "SELECT type, COUNT(*) as cnt FROM github_activity WHERE 1=1{$typeFilter}{$ftFilter}{$dateClause} GROUP BY type ORDER BY cnt DESC",
                        [...$typeParams, ...$ftParams, ...$dp]
                    );
                    $byRepoRows = DB::select(
                        "SELECT repo, COUNT(*) as cnt FROM github_activity WHERE 1=1{$typeFilter}{$ftFilter}{$dateClause} GROUP BY repo ORDER BY cnt DESC LIMIT 10",
                        [...$typeParams, ...$ftParams, ...$dp]
                    );

                    $result['activity'] = [
                        'total' => $totalRows[0]->cnt ?? 0,
                        'byType' => array_map(fn($r) => ['type' => $r->type, 'count' => $r->cnt], $byTypeRows),
                        'topRepos' => array_map(fn($r) => ['repo' => $r->repo, 'count' => $r->cnt], $byRepoRows),
                    ];
                } catch (\Throwable $e) {}
            }
        }

        if ($wantAll || in_array($t, $aboutTypes)) {
            if ($aboutOn && $on('skills_enable') && ($wantAll || $t === 'skill')) {
                try {
                    $rows = DB::select('SELECT COUNT(*) as cnt FROM skill');
                    $result['skills'] = $rows[0]->cnt ?? 0;
                } catch (\Throwable $e) {}
            }
            if ($aboutOn && $on('experience_enable') && ($wantAll || $t === 'experience')) {
                try {
                    $rows = DB::select('SELECT COUNT(*) as cnt FROM experience');
                    $result['experiences'] = $rows[0]->cnt ?? 0;
                } catch (\Throwable $e) {}
            }
            if ($aboutOn && $on('services_enable') && ($wantAll || $t === 'service')) {
                try {
                    $rows = DB::select('SELECT COUNT(*) as cnt FROM service');
                    $result['services'] = $rows[0]->cnt ?? 0;
                } catch (\Throwable $e) {}
            }
            if ($aboutOn && $on('testimonial_enable') && ($wantAll || $t === 'testimonial')) {
                try {
                    $rows = DB::select('SELECT COUNT(*) as cnt FROM testimonial');
                    $result['testimonials'] = $rows[0]->cnt ?? 0;
                } catch (\Throwable $e) {}
            }
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function buildEmbeddingClient(object $general): ?array
    {
        $url = trim($general->embedding_url ?? '');
        $key = trim($general->embedding_key ?? '');
        $model = trim($general->embedding_model ?? '');
        if (!$url || !$key || !$model) return null;
        return ['url' => $url, 'key' => $key, 'model' => $model];
    }

    private static function buildModelParams(string $model, string $reasoning): array
    {
        $modelLower = strtolower($model);
        $useThinking = $reasoning !== 'none';
        $isGemini = str_contains($modelLower, 'gemini');

        $params = [];

        if (!$isGemini) {
            $params['frequency_penalty'] = 1.2;
        }

        if ($useThinking) {
            $effort = ($isGemini && $reasoning === 'xhigh') ? 'high' : $reasoning;
            $params['reasoning_effort'] = $effort;

            if (str_contains($modelLower, 'glm')) {
                $params['chat_template_kwargs'] = ['enable_thinking' => true, 'clear_thinking' => false];
            } elseif (str_contains($modelLower, 'nemotron')) {
                $params['chat_template_kwargs'] = ['enable_thinking' => true];
                $params['reasoning_budget'] = -1;
            } elseif (str_contains($modelLower, 'qwen')) {
                $params['chat_template_kwargs'] = ['enable_thinking' => true];
            } elseif (str_contains($modelLower, 'deepseek') || str_contains($modelLower, 'kimi')) {
                $params['chat_template_kwargs'] = ['thinking' => true];
            }
        }

        return $params;
    }

    private static function resolvePrompt(object $general, string $type): string
    {
        if ($type === 'project') {
            $prompt = trim($general->ai_project_prompt ?? '');
            if ($prompt !== '') return $prompt;
        }
        return trim($general->ai_article_prompt ?? '');
    }

    private static function resolveReasoning(object $general): string
    {
        $reasoning = trim($general->ai_content_reasoning ?? '');
        if ($reasoning !== '' && $reasoning !== 'none') return $reasoning;
        $fallback = trim($general->ai_terminal_reasoning ?? '');
        return $fallback !== '' ? $fallback : 'none';
    }
}
