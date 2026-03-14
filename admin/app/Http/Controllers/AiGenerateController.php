<?php

namespace App\Http\Controllers;

use App\Models\General;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AiGenerateController extends Controller
{
    private const FIELD_PROMPTS = [
        'article' => [
            'title' => 'Generate a single short, catchy article title (max 55 characters). Output only the title, no quotes or explanation.',
            'short_desc' => 'Generate a single short article summary or excerpt (max 255 characters). Output only the text, no quotes or explanation.',
            'text' => 'Generate engaging article body content in HTML (paragraphs with <p>, lists with <ul>/<ol> where appropriate). Output only the HTML, no markdown or explanation.',
        ],
        'project' => [
            'title' => 'Generate a single short project title (max 55 characters). Output only the title, no quotes or explanation.',
            'short_desc' => 'Generate a single short project description or tagline (max 110 characters). Output only the text, no quotes or explanation.',
            'description' => 'Generate engaging project description content in HTML (paragraphs with <p>, lists with <ul>/<ol> where appropriate). Output only the HTML, no markdown or explanation.',
        ],
    ];

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:article,project'],
            'field' => ['required', 'string'],
            'topic' => ['nullable', 'string', 'max:15000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $type = $request->input('type');
        $field = $request->input('field');
        $topic = $request->input('topic', '');

        $allowed = self::FIELD_PROMPTS[$type] ?? null;
        if (! $allowed || ! array_key_exists($field, $allowed)) {
            return response()->json(['error' => 'Invalid type or field.'], 422);
        }
        $allowedFields = $type === 'project' ? ['description'] : ['text'];
        if (! in_array($field, $allowedFields, true)) {
            return response()->json(['error' => 'Only description/content generation is supported.'], 422);
        }

        $baseUrl = '';

        $basePrompt = $allowed[$field];
        $context = trim($topic);
        $userHint = $context !== ''
            ? " Use the following as context, outline, or draft (expand or refine based on this):\n\n{$context}"
            : '';
        $prompt = $basePrompt . $userHint;

        $model = 'default';
        $apiKey = '';
        $dbUrl = '';
        try {
            $general = General::find(1);
            $fromDb = $general?->ai_model;
            if (is_string($fromDb) && trim($fromDb) !== '') {
                $model = trim($fromDb);
            }
            $keyFromDb = $general?->ai_key;
            if (is_string($keyFromDb) && trim($keyFromDb) !== '') {
                $apiKey = trim($keyFromDb);
            }
            $urlFromDb = $general?->ai_url;
            if (is_string($urlFromDb) && trim($urlFromDb) !== '') {
                $dbUrl = trim($urlFromDb);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($apiKey === '') {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }

        // If URL is defined in General use it; otherwise fallback to public OpenAI base URL.
        if ($dbUrl !== '') {
            $baseUrl = $dbUrl;
        }
        if ($baseUrl === '') {
            $baseUrl = 'https://api.openai.com/v1';
        }
        if (! str_starts_with($baseUrl, 'http')) {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (empty($host) || strpos($host, '.') === false) {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }

        try {
            $endpoint = rtrim($baseUrl, '/') . '/chat/completions';
            $res = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->withToken($apiKey)
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a concise copywriter. Reply with only the requested content, no preamble or quotes.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if (! $res->successful()) {
                \Illuminate\Support\Facades\Log::warning('AI generate HTTP failed', [
                    'endpoint' => $endpoint,
                    'status' => $res->status(),
                    'model' => $model,
                    'body' => $res->body(),
                ]);
                return response()->json(['error' => __('content.ai_unavailable')], 502);
            }

            $json = $res->json();
            $text = trim((string) data_get($json, 'choices.0.message.content', ''));
            if ($text === '') {
                $text = trim((string) data_get($json, 'choices.0.text', ''));
            }

            return response()->json(['text' => $text]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AI generate failed', [
                'base_url' => $baseUrl,
                'model' => $model,
                'message' => $e->getMessage(),
            ]);
            report($e);
            return response()->json(['error' => __('content.ai_unavailable')], 502);
        }
    }
}
