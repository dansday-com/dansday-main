<?php

namespace App\Http\Controllers;

use App\Models\General;
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
            'model' => ['nullable', 'string', 'max:255'],
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

        $baseUrl = config('ai.providers.openai.url') ?: config('prism.providers.openai.url');
        $baseUrl = is_string($baseUrl) ? trim($baseUrl) : '';
        if ($baseUrl === '' || ! str_starts_with($baseUrl, 'http')) {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (empty($host) || strpos($host, '.') === false) {
            return response()->json(['error' => __('content.ai_unavailable')], 503);
        }

        $basePrompt = $allowed[$field];
        $context = trim($topic);
        $userHint = $context !== ''
            ? " Use the following as context, outline, or draft (expand or refine based on this):\n\n{$context}"
            : '';
        $prompt = $basePrompt . $userHint;

        // Model is set in General settings (DB); UI has no model selector.
        $model = 'default';
        try {
            $general = General::find(1);
            $fromDb = $general?->openai_model;
            if (is_string($fromDb) && trim($fromDb) !== '') {
                $model = trim($fromDb);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $response = agent(
                instructions: 'You are a concise copywriter. Reply with only the requested content, no preamble or quotes.',
                messages: [],
                tools: [],
            )->prompt($prompt, provider: config('ai.default'), model: $model);

            $raw = $response->text ?? '';
            $text = is_string($raw) ? trim($raw) : '';

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

    public function models(): JsonResponse
    {
        $cacheKey = 'ai_models_list';
        $ttl = 3600;

        $list = Cache::remember($cacheKey, $ttl, function () {
            $baseUrl = config('ai.providers.openai.url') ?? config('prism.providers.openai.url');
            if (empty($baseUrl)) {
                return $this->fallbackModelsList();
            }
            $url = rtrim($baseUrl, '/') . '/models';
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
