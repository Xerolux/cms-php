<?php

namespace App\GraphQL\Mutations;

use App\Models\Media;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaMutations
{
    /**
     * Upload a media file
     */
    public function upload($root, array $args)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        if (!isset($args['file']) || !$args['file'] instanceof \Illuminate\Http\UploadedFile) {
            throw new \InvalidArgumentException('Invalid file upload');
        }

        $file = $args['file'];
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('media', $filename, 'public');

        // Get image dimensions if it's an image
        $width = null;
        $height = null;
        if (str_starts_with($file->getMimeType(), 'image/')) {
            try {
                [$width, $height] = getimagesize($file->getPathname());
            } catch (\Exception $e) {
                // Could not get dimensions
            }
        }

        $media = Media::create([
            'filename' => $filename,
            'original_filename' => $originalName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
            'url' => Storage::url($path),
            'title' => $args['title'] ?? pathinfo($originalName, PATHINFO_FILENAME),
            'alt_text' => $args['alt_text'] ?? null,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $user->id,
        ]);

        return $media;
    }
}
