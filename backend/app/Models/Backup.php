<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'status',
        'disk',
        'path',
        'file_size',
        'items_count',
        'description',
        'options',
        'completed_at',
        'failed_at',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'file_size' => 'integer',
        'items_count' => 'integer',
    ];

    protected $appends = [
        'file_size_formatted',
        'duration',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get formatted file size
     */
    public function getFileSizeFormattedAttribute(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $this->file_size > 0 ? floor(log($this->file_size, 1024)) : 0;

        return number_format($this->file_size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get backup duration
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->completed_at || !$this->created_at) {
            return null;
        }

        $seconds = $this->created_at->diffInSeconds($this->completed_at);

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes . 'm ' . ($seconds % 60) . 's';
        }

        $hours = floor($minutes / 60);
        return $hours . 'h ' . ($minutes % 60) . 'm';
    }

    /**
     * Get full file path
     */
    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Check if backup file exists
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete backup file
     */
    public function deleteFile(): bool
    {
        if ($this->exists()) {
            return Storage::disk($this->disk)->delete($this->path);
        }
        return false;
    }

    /**
     * Get backup file content
     */
    public function getContent(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(int $fileSize, int $itemsCount = 0): void
    {
        $this->update([
            'status' => 'completed',
            'file_size' => $fileSize,
            'items_count' => $itemsCount,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    /**
     * Generate unique backup filename
     */
    public static function generateFilename(string $type): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);

        return "backup-{$type}-{$timestamp}-{$random}.zip";
    }

    /**
     * Get backup options with defaults
     */
    public function getOptionsWithDefaults(): array
    {
        return array_merge([
            'include_database' => true,
            'include_files' => true,
            'exclude_files' => [],
            'compression' => true,
        ], $this->options ?? []);
    }
}
