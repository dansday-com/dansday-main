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
        $social_icons = config('social_icons', []);
        return view('admin.pages.general')
            ->with('general', $general)
            ->with('social_icons', $social_icons)
            ->with('user', $user);
    }

    public function update(Request $request, General $general)
    {
        $general = General::find(1);
        $data = [
            'title'          => $request->input('title'),
            'description'    => $request->input('description'),
            'analytics_code' => $request->input('analytics_code'),
            'openai_url'     => $request->input('openai_url'),
            'openai_key'     => $request->input('openai_key'),
            'social_links'   => $request->input('social_links'),
            'image_favicon'  => $request->file('image_favicon'),
            'image_favicon_current' => $request->input('image_favicon_current'),
        ];
        $route_image_favicon = $data['image_favicon_current'];

        $validate = Validator::make($data, [
            'title'          => ['string', 'max:55'],
            'description'    => ['string', 'max:255'],
            'analytics_code' => ['nullable', 'string', 'max:55'],
            'openai_url'     => ['nullable', 'string', 'max:500'],
            'openai_key'     => ['nullable', 'string', 'max:500'],
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
                $favicon_dimensions = ['96', '57', '72', '76', '114', '120', '144', '152'];
                foreach ($favicon_dimensions as $dimension) {
                    $filename = ($dimension == '96')
                        ? 'favicon.' . $ext
                        : 'apple-touch-icon-' . $dimension . 'x' . $dimension . '-precomposed.' . $ext;
                    $destiny = @imagecreatetruecolor((int) $dimension, (int) $dimension);
                    if ($destiny === false) {
                        continue;
                    }
                    if ($isPng) {
                        imagealphablending($destiny, false);
                        imagesavealpha($destiny, true);
                    }
                    if (! @imagecopyresampled($destiny, $source, 0, 0, 0, 0, (int) $dimension, (int) $dimension, $width, $height)) {
                        imagedestroy($destiny);
                        continue;
                    }
                    $tmpPath = $tempDir . '/' . $filename;
                    if ($isPng) {
                        @imagepng($destiny, $tmpPath);
                    } else {
                        @imagejpeg($destiny, $tmpPath, 90);
                    }
                    imagedestroy($destiny);
                    if (file_exists($tmpPath)) {
                        $disk->putFileAs($directory, new File($tmpPath), $filename);
                        @unlink($tmpPath);
                    }
                }
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
            $route_image_favicon = '';
        }

        $data_new = [
            'title'          => $data['title'],
            'description'    => $data['description'],
            'analytics_code' => $data['analytics_code'],
            'openai_url'     => $data['openai_url'] ? trim($data['openai_url']) : null,
            'image_favicon'  => $route_image_favicon,
            'social_links'   => $data['social_links'],
        ];
        $keyInput = $request->input('openai_key');
        if ($keyInput !== null && trim((string) $keyInput) !== '') {
            $data_new['openai_key'] = trim($keyInput);
        }
        General::where('id', 1)->update($data_new);
        return redirect('/admin/general')->with('ok-update', '');
    }
}
