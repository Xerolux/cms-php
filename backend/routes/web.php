<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Alle Routes werden durch die React SPA gehandelt
// Dieser Catch-all Route leitet alles an die Frontend-App weiter
Route::get('/{any?}', function () {
    // Versuche die index.html vom Frontend zu laden
    $indexPath = public_path('admin/index.html');

    if (File::exists($indexPath)) {
        return File::get($indexPath);
    }

    // Fallback: return view('spa');
    return response()->json([
        'message' => 'API is running. Please access the frontend at /admin',
        'api_docs' => '/api/v1/health'
    ]);
})->where('any', '.*');
