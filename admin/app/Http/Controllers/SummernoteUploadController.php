<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SummernoteUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'file'   => ['required', 'file', 'image', 'mimes:jpg,jpeg,png'],
            'folder' => ['required', 'string', 'in:uploads/admin/temp'],
            'code'   => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file');
        $ext = $file->guessExtension();
        if ($ext === null || ! in_array(strtolower($ext), ['jpg', 'jpeg', 'png'], true)) {
            abort(422, 'Invalid or unsupported image type.');
        }

        $code = $request->input('code', 'img');
        $name = $code . '_' . Str::random(24) . '.' . $ext;
        $path = $file->storeAs('admin/temp', $name, 'uploads');

        return response(Storage::disk('uploads')->url($path), 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
