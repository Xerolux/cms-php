<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'filepath' => $this->filepath,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'filesize' => $this->filesize,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'created_at' => $this->created_at->toIso8601String(),
            'uploaded_by' => new UserResource($this->whenLoaded('uploader')),
        ];
    }
}
