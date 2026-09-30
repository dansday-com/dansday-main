<?php

namespace App\Http\Controllers;

use App\Models\General;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiController extends Controller
{
    private const SECRET_KEYS = ['ai_key', 'search_api_key', 'fetch_api_key'];

    public function index()
    {
        $general = General::find(1);
        if (! $general) {
            abort(500, 'Initial data not found.');
        }
        $user = User::find(1);
        return view('admin.pages.ai')
            ->with('general', $general)
            ->with('user', $user);
    }

    public function update(Request $request)
    {
        $data = [
            'ai_url'                  => $request->input('ai_url'),
            'ai_key'                  => $request->input('ai_key'),
            'ai_model'                => $request->input('ai_model'),
            'ai_content_model'        => $request->input('ai_content_model'),
            'ai_terminal_prompt'      => $request->input('ai_terminal_prompt'),
            'ai_terminal_reasoning'   => $request->input('ai_terminal_reasoning'),
            'ai_content_reasoning'    => $request->input('ai_content_reasoning'),
            'ai_article_prompt'       => $request->input('ai_article_prompt'),
            'ai_project_prompt'       => $request->input('ai_project_prompt'),
            'search_api_url'          => $request->input('search_api_url'),
            'search_api_key'          => $request->input('search_api_key'),
            'search_model'            => $request->input('search_model'),
            'fetch_api_url'           => $request->input('fetch_api_url'),
            'fetch_api_key'           => $request->input('fetch_api_key'),
            'fetch_model'             => $request->input('fetch_model'),
        ];

        $general = General::find(1);

        foreach (self::SECRET_KEYS as $key) {
            $currentKeyMask = ($general && !empty($general->{$key})) ? preg_replace('/./', '*', $general->{$key}) : null;
            if ($currentKeyMask && $data[$key] === $currentKeyMask) {
                $data[$key] = null;
            }
        }

        $validate = Validator::make($data, [
            'ai_url'                  => ['nullable', 'string', 'max:500'],
            'ai_key'                  => ['nullable', 'string', 'max:500'],
            'ai_model'                => ['nullable', 'string', 'max:255'],
            'ai_content_model'        => ['nullable', 'string', 'max:255'],
            'ai_terminal_prompt'      => ['nullable', 'string'],
            'ai_terminal_reasoning'   => ['nullable', 'string', 'in:none,minimal,low,medium,high,xhigh'],
            'ai_content_reasoning'    => ['nullable', 'string', 'in:none,minimal,low,medium,high,xhigh'],
            'ai_article_prompt'       => ['nullable', 'string'],
            'ai_project_prompt'       => ['nullable', 'string'],
            'search_api_url'          => ['nullable', 'string', 'max:500'],
            'search_api_key'          => ['nullable', 'string', 'max:500'],
            'search_model'            => ['nullable', 'string', 'max:255'],
            'fetch_api_url'           => ['nullable', 'string', 'max:500'],
            'fetch_api_key'           => ['nullable', 'string', 'max:500'],
            'fetch_model'             => ['nullable', 'string', 'max:255'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/ai')
                ->with('error-validation', '')
                ->withErrors($validate)
                ->withInput();
        }

        $data_new = [
            'ai_url'                  => $data['ai_url'] ? trim($data['ai_url']) : null,
            'ai_model'                => $data['ai_model'] ? trim((string) $data['ai_model']) : null,
            'ai_content_model'        => $data['ai_content_model'] ? trim((string) $data['ai_content_model']) : null,
            'ai_terminal_prompt'      => $data['ai_terminal_prompt'] ? trim($data['ai_terminal_prompt']) : null,
            'ai_terminal_reasoning'   => $data['ai_terminal_reasoning'] ?? null,
            'ai_content_reasoning'    => $data['ai_content_reasoning'] ?? null,
            'ai_article_prompt'       => $data['ai_article_prompt'] ? trim($data['ai_article_prompt']) : null,
            'ai_project_prompt'       => $data['ai_project_prompt'] ? trim($data['ai_project_prompt']) : null,
            'search_api_url'          => $data['search_api_url'] ? trim($data['search_api_url']) : null,
            'search_model'            => $data['search_model'] ? trim((string) $data['search_model']) : null,
            'fetch_api_url'           => $data['fetch_api_url'] ? trim($data['fetch_api_url']) : null,
            'fetch_model'             => $data['fetch_model'] ? trim((string) $data['fetch_model']) : null,
        ];
        foreach (self::SECRET_KEYS as $key) {
            if (!empty($data[$key])) {
                $data_new[$key] = trim($data[$key]);
            }
        }

        General::where('id', 1)->update($data_new);
        return redirect('/admin/ai')->with('ok-update', '');
    }
}
