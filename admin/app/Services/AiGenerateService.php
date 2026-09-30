<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGenerateService
{
    private const MAX_TOOL_ITERATIONS = 5;
    private const REQUEST_TIMEOUT = 180;
    private const MAX_CONTENT_ROWS = 12;
    private const MAX_ACTIVITY_ROWS = 20;
    private const MAX_DESCRIPTION_CHARS = 1400;

    private static function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search',
                    'description' => 'Recall my own work and background: my articles, projects, skills, experience, services, testimonials, GitHub activity (commits, PRs, reviews, issues), and contact info. Use keyword and/or date filters. Try multiple keyword variations and different types before concluding that something is not part of my work.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => 'Keyword to recall by matching titles and descriptions. Omit to list everything.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['article', 'project', 'commit', 'pr', 'review', 'issue', 'skill', 'experience', 'service', 'testimonial'],
                                'description' => 'Narrow recall to one kind of work. Omit to recall across everything.',
                            ],
                            'startDate' => [
                                'type' => 'string',
                                'description' => 'Recall items from this date onward (YYYY-MM-DD).',
                            ],
                            'endDate' => [
                                'type' => 'string',
                                'description' => 'Recall items up to this date (YYYY-MM-DD).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'count',
                    'description' => 'Count items of my own work (articles, projects, skills, experience, services, testimonials, GitHub activity). Use when totals or volume of work matters. Returns counts grouped by kind.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => 'Keyword to narrow the count. Omit to count everything.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['article', 'project', 'commit', 'pr', 'review', 'issue', 'skill', 'experience', 'service', 'testimonial'],
                                'description' => 'Narrow count to one kind of work. Omit to count across everything.',
                            ],
                            'startDate' => [
                                'type' => 'string',
                                'description' => 'Count items from this date onward (YYYY-MM-DD).',
                            ],
                            'endDate' => [
                                'type' => 'string',
                                'description' => 'Count items up to this date (YYYY-MM-DD).',
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

        $section = self::getEnabledSections();

        try {
            $systemPrompt = str_replace('{{today}}', date('Y-m-d'), $systemPrompt);

            $messages = [];
            if ($systemPrompt !== '') {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $webConfig = WebToolsService::config($general);
            $tools = [...self::toolDefinitions(), ...WebToolsService::toolDefinitions($webConfig)];
            $baseParams = self::buildModelParams($model, $reasoning);
            $toolResultCache = [];
            $lastContent = '';

            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $offerTools = $i < self::MAX_TOOL_ITERATIONS - 1;

                $body = [
                    'model' => $model,
                    'messages' => $messages,
                    ...($offerTools ? ['tools' => $tools, 'tool_choice' => 'auto'] : []),
                    ...$baseParams,
                ];

                $res = Http::connectTimeout(10)->timeout(self::REQUEST_TIMEOUT)
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

                $content = trim((string) ($message['content'] ?? ''));
                if ($content !== '') {
                    $lastContent = $content;
                }

                $toolCalls = $message['tool_calls'] ?? [];
                if (empty($toolCalls)) {
                    if ($content === '') {
                        $content = trim((string) data_get($json, 'choices.0.text', '')) ?: $lastContent;
                    }
                    return ['text' => $content];
                }

                $messages[] = $message;

                foreach ($toolCalls as $tc) {
                    $toolName = $tc['function']['name'] ?? '';
                    $rawArgs = $tc['function']['arguments'] ?? '';
                    $cacheKey = $toolName . ':' . $rawArgs;
                    if (!array_key_exists($cacheKey, $toolResultCache)) {
                        $toolArgs = json_decode($rawArgs ?: '{}', true) ?: [];
                        $toolResultCache[$cacheKey] = WebToolsService::isWebTool($toolName)
                            ? WebToolsService::run($toolName, $toolArgs, $webConfig)
                            : self::executeTool($toolName, $toolArgs, $section);
                    }
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc['id'],
                        'content' => $toolResultCache[$cacheKey],
                    ];
                }
            }

            return ['text' => $lastContent];
        } catch (\Throwable $e) {
            Log::error('AI generate failed', [
                'model' => $model,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['error' => __('content.ai_unavailable')];
        }
    }

    private static function executeTool(string $name, array $args, array $section): string
    {
        switch ($name) {
            case 'search':
                return self::toolSearch($args, $section);
            case 'count':
                return self::toolCount($args, $section);
            default:
                return '{}';
        }
    }

    private static function emptyResultHint(array $args, string $toolName): string
    {
        $keyword = trim($args['keyword'] ?? '');
        $type = trim($args['type'] ?? '');
        $tried = [];
        if ($keyword !== '') $tried[] = "keyword=\"{$keyword}\"";
        if ($type !== '') $tried[] = "type=\"{$type}\"";
        $triedStr = empty($tried) ? 'no filters' : implode(', ', $tried);

        return "No items matched {$triedStr}. Do NOT mention a database, records, storage, the search tool, or that nothing was found. Either call {$toolName} again with broader or different keywords (drop the type filter, try synonyms or related terms), or simply write the article authentically in first person without disclaimers or meta-commentary about missing data.";
    }

    private static function getEnabledSections(): array
    {
        try {
            $row = DB::table('page_section')->where('id', 1)->first();
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

    private static function toolSearch(array $args, array $section): string
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
                    ($hasKeyword ? ' ORDER BY relevance DESC' : ' ORDER BY created_at DESC') . ' LIMIT ' . self::MAX_CONTENT_ROWS,
                    [...$ftParams, ...$dp]
                );
                $rows = array_map(fn($r) => (array) $r, $rows);

                if (!empty($rows)) {
                    $result['articles'] = array_map(fn($r) => [
                        'title' => $r['title'],
                        'description' => mb_substr(self::stripHtml($r['description']), 0, self::MAX_DESCRIPTION_CHARS),
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
                    ($hasKeyword ? ' ORDER BY relevance DESC' : ' ORDER BY created_at DESC') . ' LIMIT ' . self::MAX_CONTENT_ROWS,
                    [...$ftParams, ...$dp]
                );
                $rows = array_map(fn($r) => (array) $r, $rows);

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
                        'description' => mb_substr(self::stripHtml($r['description']), 0, self::MAX_DESCRIPTION_CHARS),
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
                    "SELECT id, repo, title, type, additions, deletions, created_at FROM github_activity WHERE 1=1{$typeFilter}{$ftFilter}{$dateClause} ORDER BY created_at DESC LIMIT " . self::MAX_ACTIVITY_ROWS,
                    [...$typeParams, ...$ftParams, ...$dp]
                );
                $rows = array_map(fn($r) => (array) $r, $rows);

                if (!empty($rows)) {
                    $result['activity'] = [
                        'items' => array_map(fn($r) => array_filter([
                            'repo' => $r['repo'],
                            'title' => $r['title'],
                            'type' => $r['type'],
                            'date' => $r['created_at'],
                            'additions' => $r['additions'],
                            'deletions' => $r['deletions'],
                        ], fn($v) => $v !== null), $rows),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Search activity failed: ' . $e->getMessage());
            }
        }

        if ($wantAbout && $skillsOn && ($wantAll || $t === 'skill')) {
            try {
                $rows = DB::select('SELECT id, title, type FROM skill ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
                if (!empty($rows)) {
                    $result['skills'] = array_map(fn($r) => ['title' => $r['title'], 'type' => $r['type']], $rows);
                }
            } catch (\Throwable $e) {}
        }

        if ($wantAbout && $experienceOn && ($wantAll || $t === 'experience')) {
            try {
                $rows = DB::select('SELECT id, title, type, period, description FROM experience ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
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
                if (!empty($rows)) {
                    $result['services'] = array_map(fn($r) => ['title' => $r['title'], 'description' => self::stripHtml($r['description'])], $rows);
                }
            } catch (\Throwable $e) {}
        }

        if ($wantAbout && $testimonialOn && ($wantAll || $t === 'testimonial')) {
            try {
                $rows = DB::select('SELECT id, name, company, description FROM testimonial ORDER BY `order` ASC');
                $rows = array_map(fn($r) => (array) $r, $rows);
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

        $contentKeys = ['articles', 'projects', 'activity', 'skills', 'experiences', 'services', 'testimonials'];
        $hasContent = false;
        foreach ($contentKeys as $k) {
            if (!empty($result[$k])) { $hasContent = true; break; }
        }
        if (!$hasContent) {
            $result['hint'] = self::emptyResultHint($args, 'search');
        }

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

        $hasContent = false;
        foreach ($result as $k => $v) {
            if (is_numeric($v) && $v > 0) { $hasContent = true; break; }
            if (is_array($v) && !empty($v)) { $hasContent = true; break; }
        }
        if (!$hasContent) {
            $result['hint'] = self::emptyResultHint($args, 'count');
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function buildModelParams(string $model, string $reasoning): array
    {
        $modelLower = strtolower($model);
        $useThinking = $reasoning !== 'none';
        $isGemini = str_contains($modelLower, 'gemini');

        $params = [];

        if (!$isGemini) {
            $params['frequency_penalty'] = 0.3;
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
