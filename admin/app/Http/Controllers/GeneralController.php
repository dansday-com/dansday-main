<?php

namespace App\Http\Controllers;

use App\Models\General;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GeneralController extends Controller
{
    public function index()
    {
        $general = General::find(1);
        if (! $general) {
            if (! auth()->check()) {
                return redirect()->route('login')->with('message', 'Initial data not found. Please run: php artisan db:seed');
            }
            Log::error('Initial data not found (General). Please run: php artisan db:seed', [
                'user_id' => auth()->id(),
                'path' => request()->path(),
            ]);
            abort(500, 'Initial data not found. Please run: php artisan db:seed');
        }
        $user = User::find(1);
        return view('admin.pages.general')
            ->with('general', $general)
            ->with('user', $user);
    }

    public function update(Request $request, General $general)
    {
        $general = General::find(1);
        $data = [
            'title'          => $request->input('title'),
            'description'    => $request->input('description'),
            'analytics_code' => $request->input('analytics_code'),
            'social_links'   => $request->input('social_links'),
            'image_favicon'  => $request->file('image_favicon'),
            'image_favicon_current' => $request->input('image_favicon_current'),
        ];
        $route_image_favicon = $data['image_favicon_current'];

        $validate = Validator::make($data, [
            'title'          => ['string', 'max:55'],
            'description'    => ['string', 'max:255'],
            'analytics_code' => ['nullable', 'string', 'max:55'],
            'social_links'   => ['string'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/general')
                ->with('error-validation', '')
                ->withErrors($validate)
                ->withInput();
        }

        $disk = Storage::disk('uploads');
        $directory = 'img/general/favicon';

        if (! empty($data['image_favicon'])) {
            $validate = Validator::make($data, [
                'image_favicon' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            ]);
            if ($validate->fails()) {
                return redirect('/admin/general')
                    ->with('error-validation', '')
                    ->withErrors($validate)
                    ->withInput();
            }
            $pathname = $data['image_favicon']->getPathname();
            $ext = $data['image_favicon']->guessExtension();
            if ($ext === null || $ext === '' || ! in_array(strtolower($ext), ['jpg', 'jpeg', 'png'], true)) {
                return redirect('/admin/general')
                    ->with('error-validation', '')
                    ->withErrors(['image_favicon' => ['The file could not be recognized as a valid image (jpg, jpeg or png).']])
                    ->withInput();
            }
            $size = @getimagesize($pathname);
            if ($size === false || ! isset($size[0], $size[1]) || $size[0] < 1 || $size[1] < 1) {
                return redirect('/admin/general')
                    ->with('error-validation', '')
                    ->withErrors(['image_favicon' => ['The file could not be read as an image.']])
                    ->withInput();
            }
            $width = (int) $size[0];
            $height = (int) $size[1];

            if ($route_image_favicon != '' && uploads_path_safe_to_delete($route_image_favicon)) {
                $dirForDisk = uploads_path_for_disk($route_image_favicon);
                if ($dirForDisk !== '') {
                    $parent = dirname($dirForDisk);
                    if ($disk->exists($parent)) {
                        $existing = $disk->files($parent);
                        foreach ($existing as $file) {
                            $disk->delete($file);
                        }
                    }
                }

                $publicFaviconDir = public_path('favicon');
                if (is_dir($publicFaviconDir)) {
                    $filesToDelete = [
                        'favicon.ico',
                        'favicon-96x96.png',
                        'favicon.svg',
                        'apple-touch-icon.png',
                        'site.webmanifest',
                        'web-app-manifest-192x192.png',
                        'web-app-manifest-512x512.png',
                    ];
                    foreach ($filesToDelete as $file) {
                        $filePath = $publicFaviconDir . '/' . $file;
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
            }

            @ini_set('memory_limit', '256M');
            $route_image_favicon = 'uploads/' . $directory . '/favicon.' . $ext;
            $isPng = strtolower($ext) === 'png';
            $source = $isPng ? @imagecreatefrompng($pathname) : @imagecreatefromjpeg($pathname);
            if ($source === false) {
                return redirect('/admin/general')
                    ->with('error-validation', '')
                    ->withErrors(['image_favicon' => ['The file could not be processed as a valid image.']])
                    ->withInput();
            }

            $tempDir = sys_get_temp_dir() . '/favicon_' . uniqid();
            mkdir($tempDir, 0755, true);
            try {
                $faviconPath = $tempDir . '/source_favicon.' . $ext;
                if ($isPng) {
                    @imagepng($source, $faviconPath, 0);
                } else {
                    @imagejpeg($source, $faviconPath, 100);
                }

                \Illuminate\Support\Facades\Artisan::call('favicon:generate', [
                    'source' => $faviconPath,
                    '--force' => true,
                    '--silent' => true,
                ]);

                $filesToCopy = [
                    'favicon.ico',
                    'favicon-96x96.png',
                    'favicon.svg',
                    'apple-touch-icon.png',
                    'site.webmanifest',
                    'web-app-manifest-192x192.png',
                    'web-app-manifest-512x512.png',
                ];

                foreach ($filesToCopy as $file) {
                    $generatedFile = public_path('favicon/' . $file);
                    if (file_exists($generatedFile)) {
                        $disk->putFileAs($directory, new File($generatedFile), $file);
                    }
                }

                $route_image_favicon = 'uploads/' . $directory . '/favicon.ico';
            } finally {
                if (isset($source) && $source !== false) {
                    imagedestroy($source);
                }
                if (isset($tempDir) && is_dir($tempDir)) {
                    foreach (glob($tempDir . '/*') ?: [] as $f) {
                        @unlink($f);
                    }
                    @rmdir($tempDir);
                }
            }
        }
        if (empty($data['image_favicon']) && empty($data['image_favicon_current']) && ! empty($general->image_favicon) && uploads_path_safe_to_delete($general->image_favicon)) {
            $dirForDisk = uploads_path_for_disk($general->image_favicon);
            if ($dirForDisk !== '') {
                $parent = dirname($dirForDisk);
                if ($disk->exists($parent)) {
                    $existing = $disk->files($parent);
                    foreach ($existing as $file) {
                        $disk->delete($file);
                    }
                }
            }

            $publicFaviconDir = public_path('favicon');
            if (is_dir($publicFaviconDir)) {
                $filesToDelete = [
                    'favicon.ico',
                    'favicon-96x96.png',
                    'favicon.svg',
                    'apple-touch-icon.png',
                    'site.webmanifest',
                    'web-app-manifest-192x192.png',
                    'web-app-manifest-512x512.png',
                ];
                foreach ($filesToDelete as $file) {
                    $filePath = $publicFaviconDir . '/' . $file;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }

            $route_image_favicon = '';
        }

        $data_new = [
            'title'          => $data['title'],
            'description'    => $data['description'],
            'analytics_code' => $data['analytics_code'],
            'image_favicon'  => $route_image_favicon,
            'social_links'   => $data['social_links'],
        ];
        General::where('id', 1)->update($data_new);
        return redirect('/admin/general')->with('ok-update', '');
    }

    public function updateAi(Request $request)
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
            'embedding_url'           => $request->input('embedding_url'),
            'embedding_key'           => $request->input('embedding_key'),
            'embedding_model'         => $request->input('embedding_model'),
        ];

        $general = General::find(1);

        $currentKeyMask = ($general && !empty($general->ai_key)) ? preg_replace('/./', '*', $general->ai_key) : null;
        if ($currentKeyMask && $data['ai_key'] === $currentKeyMask) {
            $data['ai_key'] = null;
        }

        $currentEmbKeyMask = ($general && !empty($general->embedding_key)) ? preg_replace('/./', '*', $general->embedding_key) : null;
        if ($currentEmbKeyMask && $data['embedding_key'] === $currentEmbKeyMask) {
            $data['embedding_key'] = null;
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
            'embedding_url'           => ['nullable', 'string', 'max:500'],
            'embedding_key'           => ['nullable', 'string', 'max:500'],
            'embedding_model'         => ['nullable', 'string', 'max:255'],
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
            'embedding_url'           => $data['embedding_url'] ? trim($data['embedding_url']) : null,
            'embedding_model'         => $data['embedding_model'] ? trim((string) $data['embedding_model']) : null,
        ];
        if (!empty($data['ai_key'])) {
            $data_new['ai_key'] = trim($data['ai_key']);
        }
        if (!empty($data['embedding_key'])) {
            $data_new['embedding_key'] = trim($data['embedding_key']);
        }

        General::where('id', 1)->update($data_new);
        return redirect('/admin/ai')->with('ok-update', '');
    }

    public function aiIndex()
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

    public function terminalIndex()
    {
        $general = General::find(1);
        if (! $general) {
            abort(500, 'Initial data not found.');
        }
        $user = User::find(1);
        return view('admin.pages.terminal')
            ->with('general', $general)
            ->with('user', $user);
    }

    public function updateTerminal(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'terminal_username' => ['nullable', 'string', 'max:100'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/terminal')
                ->with('error-validation', '')
                ->withErrors($validate)
                ->withInput();
        }

        General::where('id', 1)->update([
            'terminal_username' => $request->input('terminal_username') ? trim($request->input('terminal_username')) : null,
        ]);
        return redirect('/admin/terminal')->with('ok-update', '');
    }
}
