<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Http\UploadedFile;

class ImageService
{
    protected ImageManager $manager;

    protected array $thumbnailSizes = [
        'thumbnail' => [150, 150],
        'small' => [300, 200],
        'medium' => [600, 400],
        'large' => [1200, 800],
    ];

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Prozessiert ein hochgeladenes Bild
     * Erstellt Thumbnails und konvertiert zu WebP
     */
    public function processImage(UploadedFile $file, string $originalFilename): array
    {
        // Jahr/Monat Ordnerstruktur
        $datePath = date('Y/m');
        $filename = $this->generateUniqueFilename($file->getClientOriginalExtension());

        // Original speichern
        $path = $file->storeAs("media/{$datePath}", $filename, 'public');
        $fullPath = Storage::disk('public')->path($path);

        // Bild-Metadaten auslesen
        $imageData = $this->getImageMetadata($fullPath);

        // Thumbnails generieren (nur für Bilder)
        if ($imageData['mime_type'] !== 'image/svg+xml') {
            $thumbnails = $this->generateThumbnails($fullPath, $datePath, $filename);
        } else {
            $thumbnails = [];
        }

        // WebP Version erstellen (wenn nicht SVG)
        if ($imageData['mime_type'] !== 'image/svg+xml' && $imageData['mime_type'] !== 'image/gif') {
            $webpPath = $this->convertToWebP($fullPath, $datePath, $filename);
            $imageData['webp_url'] = Storage::disk('public')->url($webpPath);
        }

        return [
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'filepath' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime_type' => $imageData['mime_type'],
            'filesize' => $file->getSize(),
            'width' => $imageData['width'],
            'height' => $imageData['height'],
            'thumbnails' => $thumbnails,
            'webp_url' => $imageData['webp_url'] ?? null,
        ];
    }

    /**
     * Generiert Thumbnails in verschiedenen Größen
     */
    protected function generateThumbnails(string $imagePath, string $datePath, string $filename): array
    {
        $thumbnails = [];
        $image = $this->manager->read($imagePath);

        foreach ($this->thumbnailSizes as $size => [$width, $height]) {
            try {
                // Thumbnail erstellen
                $thumbnail = $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                // Thumbnail-Pfad
                $thumbnailPath = "media/thumbnails/{$datePath}/{$size}_{$filename}";
                $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

                // Ordner erstellen falls nicht vorhanden
                $thumbnailDir = dirname($fullThumbnailPath);
                if (!is_dir($thumbnailDir)) {
                    mkdir($thumbnailDir, 0755, true);
                }

                // Thumbnail speichern
                $thumbnail->save($fullThumbnailPath, 85);

                $thumbnails[$size] = [
                    'url' => Storage::disk('public')->url($thumbnailPath),
                    'width' => $width,
                    'height' => $height,
                ];
            } catch (\Exception $e) {
                \Log::error("Failed to generate {$size} thumbnail: " . $e->getMessage());
            }
        }

        return $thumbnails;
    }

    /**
     * Konvertiert Bild zu WebP für bessere Performance
     */
    protected function convertToWebP(string $imagePath, string $datePath, string $filename): string
    {
        $image = $this->manager->read($imagePath);

        // WebP Dateiname
        $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
        $webpPath = "media/webp/{$datePath}/{$webpFilename}";
        $fullWebpPath = Storage::disk('public')->path($webpPath);

        // Ordner erstellen
        $webpDir = dirname($fullWebpPath);
        if (!is_dir($webpDir)) {
            mkdir($webpDir, 0755, true);
        }

        // Als WebP speichern mit 85% Qualität
        $image->toWebp(85)->save($fullWebpPath);

        return $webpPath;
    }

    /**
     * Liest Bild-Metadaten aus
     */
    protected function getImageMetadata(string $imagePath): array
    {
        try {
            $image = $this->manager->read($imagePath);

            return [
                'width' => $image->width(),
                'height' => $image->height(),
                'mime_type' => mime_content_type($imagePath),
            ];
        } catch (\Exception $e) {
            // Fallback für SVG oder andere Formate
            return [
                'width' => null,
                'height' => null,
                'mime_type' => mime_content_type($imagePath),
            ];
        }
    }

    /**
     * Generiert eindeutigen Dateinamen
     */
    protected function generateUniqueFilename(string $extension): string
    {
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Löscht Bild und alle Thumbnails
     */
    public function deleteImage(string $filepath, ?array $thumbnails = null): void
    {
        // Original löschen
        if (Storage::disk('public')->exists($filepath)) {
            Storage::disk('public')->delete($filepath);
        }

        // Thumbnails löschen
        if ($thumbnails) {
            foreach ($thumbnails as $size => $data) {
                $thumbnailPath = str_replace(Storage::disk('public')->url(''), '', $data['url']);
                if (Storage::disk('public')->exists($thumbnailPath)) {
                    Storage::disk('public')->delete($thumbnailPath);
                }
            }
        }

        // WebP löschen wenn vorhanden
        $pathInfo = pathinfo($filepath);
        $webpPath = 'media/webp/' . $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
        if (Storage::disk('public')->exists($webpPath)) {
            Storage::disk('public')->delete($webpPath);
        }
    }

    /**
     * Gibt die URL für eine bestimmte Thumbnail-Größe zurück
     */
    public function getThumbnailUrl(?array $thumbnails, string $size = 'medium'): ?string
    {
        if (!$thumbnails || !isset($thumbnails[$size])) {
            return null;
        }

        return $thumbnails[$size]['url'];
    }
}
