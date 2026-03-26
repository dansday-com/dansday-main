<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGenerateService
{
    public static function generate(string $type, string $topic): array
    {
        $general = DB::table('page_setting')->where('id', 1)->first();
        if (!$general) {
            return ['error' => __('content.ai_unavailable')];
        }

        $url = trim($general->ai_url ?? '');
        $key = trim($general->ai_key ?? '');
        $model = trim($general->ai_model ?? '') ?: 'default';

        if (!$url || !$key) {
            return ['error' => __('content.ai_unavailable')];
        }

        $context = trim($topic);
        $prompt = $context !== '' ? $context : 'Generate content.';

        $systemPrompt = self::resolvePrompt($general, $type);
        $reasoning = self::resolveReasoning($general, $type);

        $similarContext = '';
        try {
            $similar = SimilarContentService::find($type, $context, 10);
            $similarContext = SimilarContentService::formatContext($similar, $type);
        } catch (\Throwable $e) {
            Log::warning('Similar content search failed: ' . $e->getMessage());
        }

        if ($similarContext !== '') {
            $prompt = $prompt . "\n\n---\n" . $similarContext;
        }

        try {
            $messages = [];
            if ($systemPrompt !== '') {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $body = array_filter([
                'model' => $model,
                'messages' => $messages,
                ...self::buildModelParams($model, $reasoning),
            ], fn($v) => $v !== null);

            $endpoint = rtrim($url, '/');
            if (!str_ends_with($endpoint, '/chat/completions')) {
                $endpoint .= '/chat/completions';
            }

            $res = Http::timeout(60)
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
            $text = trim((string) data_get($json, 'choices.0.message.content', ''));
            if ($text === '') {
                $text = trim((string) data_get($json, 'choices.0.text', ''));
            }

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

    private static function resolveReasoning(object $general, string $type): string
    {
        if ($type === 'project') {
            $reasoning = trim($general->ai_project_reasoning ?? '');
            if ($reasoning !== '') return $reasoning;
        }
        $reasoning = trim($general->ai_article_reasoning ?? '');
        return $reasoning !== '' ? $reasoning : 'none';
    }
}
