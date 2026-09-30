<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebToolsService
{
    private const SEARCH_PATH = '/search';
    private const FETCH_PATH = '/web/fetch';

    private const TOOL_TIMEOUT = 20;
    private const MAX_RESULTS = 5;
    private const MAX_RESULTS_CAP = 10;
    private const MAX_SNIPPET_LENGTH = 400;
    private const MAX_FETCH_CHARACTERS = 6000;
    private const THIN_SNIPPET_LENGTH = 160;

    private const SEARCH_DESCRIPTION = "Search the live web. This is your lookup for anything outside my own work — news, current events, prices, release dates, versions, who someone is, whether something is real, how something works, or any claim where your own memory could be out of date.\n\nThe search and count tools are the source for my own articles, projects, GitHub activity and background. Use those for my work, and use this for everything else.\n\nKeep going until you actually have the answer. If the results are thin, contradict each other, or only half answer the question, open the most promising result with fetch_web_page, or search again with different keywords.\n\nWrite only facts present in the results, never fill a gap from memory. Write the query in English.";

    private const FETCH_DESCRIPTION = "Read the full text of one web page by its URL. Use this whenever a search snippet looks like the answer but is too short to write from confidently, whenever you need an exact number, price, date or version, and whenever the topic includes a link. Reading the page beats writing from a snippet.\n\nYou must already have the exact URL — from the topic or from a search_web result. Never invent or guess a URL. One page per call. Write only from the text that comes back; if the page turns out not to have it, go back to search_web and try the next result rather than giving up.";

    private const SEARCH_EMPTY_HINT = 'Nothing came back for this query. Search again with different or broader English keywords before giving up.';

    private const SEARCH_THIN_HINT = 'These snippets are too short to write from confidently. Open the most relevant result with fetch_web_page first.';

    private const SEARCH_FAILED_HINT = 'This search did not go through. Try search_web once more.';

    private const FETCH_FAILED_HINT = 'This page could not be read. Go back to your search_web results and open a different one, or search again — do not write from memory.';

    public static function config(object $general): array
    {
        return [
            'search' => [
                'api_url' => trim((string) ($general->search_api_url ?? '')),
                'api_key' => trim((string) ($general->search_api_key ?? '')),
                'model' => trim((string) ($general->search_model ?? '')),
            ],
            'fetch' => [
                'api_url' => trim((string) ($general->fetch_api_url ?? '')),
                'api_key' => trim((string) ($general->fetch_api_key ?? '')),
                'model' => trim((string) ($general->fetch_model ?? '')),
            ],
        ];
    }

    private static function configured(array $endpoint): bool
    {
        return $endpoint['api_url'] !== '' && $endpoint['api_key'] !== '' && $endpoint['model'] !== '';
    }

    public static function isWebTool(string $name): bool
    {
        return $name === 'search_web' || $name === 'fetch_web_page';
    }

    public static function toolDefinitions(array $config): array
    {
        $tools = [];

        if (self::configured($config['search'])) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'search_web',
                    'description' => self::SEARCH_DESCRIPTION,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'What to search for, in English, kept short — keywords rather than a full sentence or question.',
                            ],
                            'search_type' => [
                                'type' => 'string',
                                'enum' => ['web', 'news'],
                                'description' => 'Use "news" only for recent news or current events. Otherwise leave this out.',
                            ],
                            'max_results' => [
                                'type' => 'integer',
                                'description' => 'How many results to read, 1 to ' . self::MAX_RESULTS_CAP . '. Leave out for ' . self::MAX_RESULTS . '.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ];
        }

        if (self::configured($config['fetch'])) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_web_page',
                    'description' => self::FETCH_DESCRIPTION,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => [
                                'type' => 'string',
                                'description' => 'The full http:// or https:// URL to read. Must be a URL you were given or found in a search result, never one you made up.',
                            ],
                        ],
                        'required' => ['url'],
                    ],
                ],
            ];
        }

        return $tools;
    }

    public static function run(string $name, array $args, array $config): string
    {
        $result = $name === 'search_web' ? self::search($config['search'], $args) : self::fetch($config['fetch'], $args);
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function resolveUrl(string $rawUrl, string $path): string
    {
        $base = rtrim(trim($rawUrl), '/');
        if ($base === '') return '';
        return str_ends_with($base, $path) ? $base : $base . $path;
    }

    private static function postJson(string $url, string $apiKey, array $body): array
    {
        $res = Http::connectTimeout(10)->timeout(self::TOOL_TIMEOUT)
            ->acceptJson()
            ->withToken($apiKey)
            ->post($url, $body);

        if (!$res->successful()) {
            $detail = substr($res->body(), 0, 200);
            throw new \RuntimeException('HTTP ' . $res->status() . ($detail !== '' ? ' ' . $detail : ''));
        }

        return $res->json() ?? [];
    }

    private static function clampResults(mixed $raw): int
    {
        $count = is_numeric($raw) ? (int) $raw : 0;
        if ($count <= 0) return self::MAX_RESULTS;
        return min($count, self::MAX_RESULTS_CAP);
    }

    private static function search(array $endpoint, array $args): array
    {
        if (!self::configured($endpoint)) return ['ok' => false, 'reason' => 'web_search_not_configured'];

        $query = trim((string) ($args['query'] ?? ''));
        if ($query === '') return ['ok' => false, 'reason' => 'missing_query'];

        $searchType = ($args['search_type'] ?? null) === 'news' ? 'news' : 'web';

        try {
            $payload = self::postJson(self::resolveUrl($endpoint['api_url'], self::SEARCH_PATH), $endpoint['api_key'], [
                'model' => $endpoint['model'],
                'query' => $query,
                'search_type' => $searchType,
                'max_results' => self::clampResults($args['max_results'] ?? null),
            ]);

            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
            if (empty($results)) return ['ok' => false, 'reason' => 'no_results', 'query' => $query, 'next_step' => self::SEARCH_EMPTY_HINT];

            $mapped = array_map(fn ($entry) => [
                'title' => $entry['title'] ?? null,
                'url' => $entry['url'] ?? null,
                'snippet' => is_string($entry['snippet'] ?? null) ? mb_substr($entry['snippet'], 0, self::MAX_SNIPPET_LENGTH) : null,
                'published_at' => $entry['published_at'] ?? null,
            ], array_slice($results, 0, self::MAX_RESULTS_CAP));

            $answer = $payload['answer'] ?? null;
            $thin = !$answer && collect($mapped)->every(fn ($entry) => mb_strlen(trim((string) ($entry['snippet'] ?? ''))) < self::THIN_SNIPPET_LENGTH);

            return [
                'ok' => true,
                'query' => $query,
                'answer' => $answer,
                'results' => $mapped,
                ...($thin ? ['next_step' => self::SEARCH_THIN_HINT] : []),
            ];
        } catch (\Throwable $e) {
            Log::warning('Web search failed', ['message' => $e->getMessage()]);
            return ['ok' => false, 'reason' => $e instanceof ConnectionException ? 'timeout' : 'search_failed', 'next_step' => self::SEARCH_FAILED_HINT];
        }
    }

    private static function fetch(array $endpoint, array $args): array
    {
        if (!self::configured($endpoint)) return ['ok' => false, 'reason' => 'web_fetch_not_configured'];

        $url = trim((string) ($args['url'] ?? ''));
        if (!preg_match('/^https?:\/\//i', $url)) return ['ok' => false, 'reason' => 'invalid_url', 'next_step' => self::FETCH_FAILED_HINT];

        try {
            $payload = self::postJson(self::resolveUrl($endpoint['api_url'], self::FETCH_PATH), $endpoint['api_key'], [
                'model' => $endpoint['model'],
                'url' => $url,
                'format' => 'markdown',
                'max_characters' => self::MAX_FETCH_CHARACTERS,
            ]);

            $text = (string) ($payload['content']['text'] ?? '');
            if ($text === '') return ['ok' => false, 'reason' => 'empty_page', 'url' => $url, 'next_step' => self::FETCH_FAILED_HINT];

            return [
                'ok' => true,
                'url' => $payload['url'] ?? $url,
                'title' => $payload['title'] ?? null,
                'content' => mb_substr($text, 0, self::MAX_FETCH_CHARACTERS),
                'truncated' => mb_strlen($text) > self::MAX_FETCH_CHARACTERS,
            ];
        } catch (\Throwable $e) {
            Log::warning('Web fetch failed', ['message' => $e->getMessage()]);
            return ['ok' => false, 'reason' => $e instanceof ConnectionException ? 'timeout' : 'fetch_failed', 'next_step' => self::FETCH_FAILED_HINT];
        }
    }
}
