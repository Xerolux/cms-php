<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadController extends Controller
{
    public function index()
    {
        $downloads = Download::with(['uploader'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($downloads);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:102400',
            'description' => 'nullable|string',
            'access_level' => 'in:public,registered,premium',
            'expires_at' => 'nullable|date',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
        $filepath = 'downloads/' . date('Y/m');

        $path = $file->storeAs($filepath, $filename, 'local');

        $download = Download::create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'filepath' => $path,
            'mime_type' => $file->getMimeType(),
            'filesize' => $file->getSize(),
            'description' => $validated['description'] ?? null,
            'access_level' => $validated['access_level'] ?? 'public',
            'expires_at' => $validated['expires_at'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json($download, 201);
    }

    public function show($id)
    {
        $download = Download::with(['uploader'])->findOrFail($id);
        return response()->json($download);
    }

    public function download($token)
    {
        $tokenModel = \App\Models\DownloadToken::with('download')
            ->where('token', $token)
            ->where('is_valid', true)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->firstOrFail();

        $download = $tokenModel->download;

        $tokenModel->markAsUsed();
        $download->incrementDownloadCount();

        return Storage::download($download->filepath, $download->original_filename);
    }

    public function destroy($id)
    {
        $download = Download::findOrFail($id);

        Storage::disk('local')->delete($download->filepath);
        $download->delete();

        return response()->json(null, 204);
    }
}
