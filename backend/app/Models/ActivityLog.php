<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'tags',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related model (polymorphic).
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by model.
     */
    public function scopeForModel($query, $modelType, $modelId = null)
    {
        $query->where('model_type', $modelType);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope a query to filter by tags.
     */
    public function scopeWithTag($query, $tag)
    {
        return $query->where('tags', 'like', "%{$tag}%");
    }

    /**
     * Scope a query for security-related logs.
     */
    public function scopeSecurity($query)
    {
        return $query->where('tags', 'like', '%security%');
    }

    /**
     * Scope a query for critical logs.
     */
    public function scopeCritical($query)
    {
        return $query->where('tags', 'like', '%critical%');
    }

    /**
     * Scope a query for recent logs.
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Log an activity.
     */
    public static function log(array $data): self
    {
        return static::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'action' => $data['action'],
            'model_type' => $data['model_type'] ?? null,
            'model_id' => $data['model_id'] ?? null,
            'description' => $data['description'] ?? null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'tags' => $data['tags'] ?? null,
        ]);
    }

    /**
     * Get available actions.
     */
    public static function getActions(): array
    {
        return [
            'login' => 'Login',
            'logout' => 'Logout',
            'failed_login' => 'Failed Login',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'view' => 'View',
            'export' => 'Export',
            'import' => 'Import',
            'download' => 'Download',
            'upload' => 'Upload',
            'restore' => 'Restore',
            'backup' => 'Backup',
            'settings_update' => 'Settings Update',
            'password_change' => 'Password Change',
            '2fa_enabled' => '2FA Enabled',
            '2fa_disabled' => '2FA Disabled',
            'permission_change' => 'Permission Change',
        ];
    }

    /**
     * Get available tags.
     */
    public static function getTags(): array
    {
        return [
            'security' => 'Security',
            'admin' => 'Admin',
            'critical' => 'Critical',
            'content' => 'Content',
            'media' => 'Media',
            'user' => 'User',
            'system' => 'System',
        ];
    }

    /**
     * Get action badge color.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create', 'login', 'upload', 'import' => 'success',
            'update', 'view', 'download', 'export' => 'processing',
            'delete', 'logout', '2fa_disabled' => 'error',
            'failed_login', 'password_change', 'permission_change' => 'warning',
            'restore', 'backup', 'settings_update' => 'default',
            default => 'default',
        };
    }

    /**
     * Get action icon.
     */
    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'login', 'logout' => 'LoginOutlined',
            'create' => 'PlusOutlined',
            'update' => 'EditOutlined',
            'delete' => 'DeleteOutlined',
            'view' => 'EyeOutlined',
            'download' => 'DownloadOutlined',
            'upload' => 'UploadOutlined',
            'export' => 'ExportOutlined',
            'import' => 'ImportOutlined',
            'backup' => 'CloudDownloadOutlined',
            'restore' => 'CloudUploadOutlined',
            'settings_update' => 'SettingOutlined',
            'password_change' => 'LockOutlined',
            '2fa_enabled' => 'SafetyOutlined',
            '2fa_disabled' => 'SafetyCertificateOutlined',
            default => 'InfoCircleOutlined',
        };
    }
}
