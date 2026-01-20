<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownloadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'title' => $this->title,
            'description' => $this->description,
            'filepath' => $this->filepath,
            'mime_type' => $this->mime_type,
            'filesize' => $this->filesize,
            'access_level' => $this->access_level,
            'download_count' => $this->download_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
