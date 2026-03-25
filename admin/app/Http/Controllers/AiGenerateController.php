<?php

namespace App\Http\Controllers;

use App\Models\General;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AiGenerateController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'topic' => ['nullable', 'string', 'max:15000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $topic = $request->input('topic', '');

        $context = trim($topic);
        $prompt = $context !== '' ? $context : 'Generate content.';

        $baseUrl = '';

        $model = 'default';
        $apiKey = '';
        $dbUrl = '';
        $systemPrompt = '';
        $reasoning = 'none';
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
            $promptFromDb = $general?->ai_content_prompt;
            if (is_string($promptFromDb) && trim($promptFromDb) !== '') {
                $systemPrompt = trim($promptFromDb);
            }
            $reasoningFromDb = $general?->ai_content_reasoning;
            if (is_string($reasoningFromDb) && trim($reasoningFromDb) !== '') {
                $reasoning = trim($reasoningFromDb);
            }
        } catch (\Throwable $e) {

        }

        if ($apiKey === '') {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }

        if ($dbUrl === '') {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }
        $baseUrl = $dbUrl;

        try {
            $endpoint = rtrim($baseUrl, '/') . '/chat/completions';
            $res = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->withToken($apiKey)
                ->post($endpoint, array_filter([
                    'model' => $model,
                    'messages' => array_values(array_filter([
                        $systemPrompt !== '' ? ['role' => 'system', 'content' => $systemPrompt] : null,
                        ['role' => 'user', 'content' => $prompt],
                    ])),
                    'reasoning_effort' => $reasoning !== 'none' ? $reasoning : null,
                ], fn($v) => $v !== null));

            if (! $res->successful()) {
                Log::warning('AI generate HTTP failed', [
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
            Log::warning('AI generate failed', [
                'base_url' => $baseUrl,
                'model' => $model,
                'message' => $e->getMessage(),
            ]);
            report($e);
            return response()->json(['error' => __('content.ai_unavailable')], 502);
        }
    }
}
