<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use function Laravel\Ai\agent;

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
            'model' => ['required', 'string', 'max:255'],
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

        $basePrompt = $allowed[$field];
        $context = trim($topic);
        $userHint = $context !== ''
            ? " Use the following as context, outline, or draft (expand or refine based on this):\n\n{$context}"
            : '';
        $prompt = $basePrompt . $userHint;

        $model = trim((string) $request->input('model'));
        if ($model === '') {
            return response()->json(['error' => 'Please select a model from the dropdown.'], 422);
        }

        try {
            $response = agent(
                instructions: 'You are a concise copywriter. Reply with only the requested content, no preamble or quotes.',
                messages: [],
                tools: [],
            )->prompt($prompt, provider: config('ai.default'), model: $model);

            $text = trim((string) $response->text);
            if ($field === 'short_desc' && mb_strlen($text) > 255) {
                $text = mb_substr($text, 0, 252) . '...';
            }
            if (in_array($field, ['title'], true) && mb_strlen($text) > 55) {
                $text = mb_substr($text, 0, 52) . '...';
            }

            return response()->json(['text' => $text]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'AI generation failed: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function models(): JsonResponse
    {
        $cacheKey = 'ai_models_list';
        $ttl = 3600;

        $list = Cache::remember($cacheKey, $ttl, function () {
            $baseUrl = config('ai.providers.openai.url') ?? env('OPENAI_BASE_URL');
            if (empty($baseUrl)) {
                return $this->fallbackModelsList();
            }
            $url = rtrim($baseUrl, '/') . '/v1/models';
            try {
                $response = Http::timeout(10)->get($url);
                if (! $response->successful()) {
                    return $this->fallbackModelsList();
                }
                $data = $response->json();
                $items = $data['data'] ?? [];
                $models = [];
                foreach ($items as $m) {
                    $id = $m['id'] ?? null;
                    $name = $m['name'] ?? $id;
                    $modality = $m['architecture']['modality'] ?? $m['architecture']['output_modalities'][0] ?? null;
                    if ($id && ($modality === 'text->text' || ($m['architecture']['output_modalities'][0] ?? null) === 'text')) {
                        $models[] = ['id' => $id, 'name' => $name];
                    }
                }
                return ! empty($models) ? $models : $this->fallbackModelsList();
            } catch (\Throwable $e) {
                return $this->fallbackModelsList();
            }
        });

        return response()->json(['models' => $list]);
    }

    /** Fallback when gateway /v1/models is unavailable (no config or env). */
    private function fallbackModelsList(): array
    {
        return [
            ['id' => 'openai/gpt-oss-20b', 'name' => 'GPT-OSS 20B'],
            ['id' => 'openai/gpt-oss-120b', 'name' => 'GPT-OSS 120B'],
            ['id' => 'google/gemini-3-flash-preview', 'name' => 'Gemini 3 Flash'],
            ['id' => 'google/gemini-3.1-flash-lite-preview', 'name' => 'Gemini 3.1 Flash-Lite'],
            ['id' => 'google/gemini-3.1-pro-preview', 'name' => 'Gemini 3.1 Pro'],
            ['id' => 'anthropic/claude-opus-4.6', 'name' => 'Claude Opus 4.6'],
        ];
    }
}
