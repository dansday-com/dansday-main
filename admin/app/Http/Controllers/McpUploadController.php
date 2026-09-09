<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class McpUploadController extends Controller
{
    private const KINDS = [
        'article'  => ['disk' => 'uploads', 'folder' => 'admin/articles',       'prefix' => 'post_image',    'family' => 'image',    'max_kb' => 8192],
        'project'  => ['disk' => 'uploads', 'folder' => 'admin/projects',       'prefix' => 'project_image', 'family' => 'image',    'max_kb' => 8192],
        'inline'   => ['disk' => 'uploads', 'folder' => 'admin/temp',           'prefix' => 'img',           'family' => 'image',    'max_kb' => 8192],
        'document' => ['disk' => 'media',   'folder' => 'linkedin/documents', 'prefix' => 'document',      'family' => 'document', 'max_kb' => 102400],
        'video'    => ['disk' => 'media',   'folder' => 'linkedin/videos',    'prefix' => 'video',         'family' => 'video',    'max_kb' => 204800],
    ];

    private const FAMILIES = [
        'image' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
        ],
        'document' => [
            'application/pdf'                                                           => 'pdf',
            'application/msword'                                                        => 'doc',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        ],
        'video' => [
            'video/mp4'       => 'mp4',
            'video/quicktime' => 'mov',
        ],
    ];

    private const ZIP_BACKED = ['docx' => true, 'pptx' => true];

    public function store(Request $request): JsonResponse
    {
        $kind = (string) $request->input('kind', 'article');

        if (! array_key_exists($kind, self::KINDS)) {
            return response()->json([
                'error' => 'Unknown kind "'.$kind.'". Use one of: '.implode(', ', array_keys(self::KINDS)).'.',
            ], 422);
        }

        $target = self::KINDS[$kind];
        $allowed = self::FAMILIES[$target['family']];

        $accepted = array_keys($allowed);

        if ($target['family'] === 'document') {
            $accepted[] = 'application/zip';
        }

        $rules = ['required', 'file', 'max:'.$target['max_kb'], 'mimetypes:'.implode(',', $accepted)];

        if ($target['family'] === 'image') {
            array_splice($rules, 2, 0, 'image');
        }

        $validator = validator($request->all(), ['file' => $rules]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('file'),
                'kind'  => $kind,
                'allowed_types' => array_values(array_unique($allowed)),
                'max_mb' => round($target['max_kb'] / 1024, 1),
            ], 422);
        }

        $file = $request->file('file');
        $extension = $this->resolveExtension($file, $allowed);

        if ($extension === null) {
            return response()->json([
                'error' => 'That file is not one of the accepted types for kind "'.$kind.'".',
                'allowed_types' => array_values(array_unique($allowed)),
            ], 422);
        }

        $name = $target['prefix'].'_'.Str::random(24).'.'.$extension;
        $stored = $file->storeAs($target['folder'], $name, $target['disk']);

        if ($stored === false) {
            return response()->json(['error' => 'Could not write the file to the '.$target['disk'].' disk.'], 500);
        }

        $namespace = $target['disk'] === 'uploads' ? 'uploads/' : 'media/';

        return response()->json(array_filter([
            'path'  => $namespace.$stored,
            'url'   => $target['disk'] === 'uploads' ? Storage::disk('uploads')->url($stored) : null,
            'kind'  => $kind,
            'type'  => $extension,
            'bytes' => $file->getSize(),
            'note'  => $target['disk'] === 'media'
                ? 'Stored privately and not served over the web. Pass this path to post_to_linkedin.'
                : null,
        ], fn ($value) => $value !== null));
    }

    private function resolveExtension(UploadedFile $file, array $allowed): ?string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return null;
        }

        $mime = (string) (mime_content_type($path) ?: '');

        if (isset($allowed[$mime])) {
            return $allowed[$mime];
        }

        if ($mime === 'application/zip') {
            return $this->resolveZipBacked($file, $path, $allowed);
        }

        return null;
    }

    private function resolveZipBacked(UploadedFile $file, string $path, array $allowed): ?string
    {
        $claimed = strtolower((string) $file->getClientOriginalExtension());

        if (! isset(self::ZIP_BACKED[$claimed]) || ! in_array($claimed, $allowed, true)) {
            return null;
        }

        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return null;
        }

        $marker = $claimed === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
        $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($marker) !== false;

        $zip->close();

        return $valid ? $claimed : null;
    }
}
