<?php

use Illuminate\Support\Facades\Storage;

const UPLOADS_ALLOWED_PREFIXES = [
    'admin/articles/',
    'admin/projects/',
    'admin/profile/',
    'admin/general/',
    'admin/temp/',
    'admin/work/',
];

const MEDIA_ALLOWED_PREFIXES = [
    'linkedin/documents/',
    'linkedin/videos/',
];

if (! function_exists('storage_path_normalize')) {
    function storage_path_normalize(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);

        if (str_contains($path, "\0")) {
            return '';
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}

if (! function_exists('uploads_path_for_disk')) {
    function uploads_path_for_disk(?string $path): string
    {
        $path = storage_path_normalize($path);

        if (str_starts_with($path, 'uploads/')) {
            $path = substr($path, 8);
        }

        return $path;
    }
}

if (! function_exists('uploads_path_safe_to_delete')) {
    function uploads_path_safe_to_delete(?string $path): bool
    {
        $path = uploads_path_for_disk($path);
        if ($path === '') {
            return false;
        }
        foreach (UPLOADS_ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('media_path_for_disk')) {
    function media_path_for_disk(?string $path): string
    {
        $path = storage_path_normalize($path);

        if (str_starts_with($path, 'media/')) {
            $path = substr($path, 6);
        }

        return $path;
    }
}

if (! function_exists('media_path_is_allowed')) {
    function media_path_is_allowed(?string $path): bool
    {
        $path = media_path_for_disk($path);

        if ($path === '') {
            return false;
        }

        foreach (MEDIA_ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) && strlen($path) > strlen($prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('upload_url')) {
    function upload_url(?string $path): string
    {
        $disk = Storage::disk('uploads');
        $default = 'admin/image_default.png';
        $path = uploads_path_for_disk($path ?: 'uploads/admin/image_default.png');
        $path = $path ?: $default;
        return $disk->exists($path) ? $disk->url($path) : $disk->url($default);
    }
}
