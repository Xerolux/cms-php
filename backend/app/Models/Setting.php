<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'display_name',
        'description',
        'options',
        'is_public',
        'sort_order',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'value' => 'string',
        'options' => 'array',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope a query to only include public settings.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to filter by group.
     */
    public function scopeGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the user who last updated the setting.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the typed value.
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'number', 'integer' => (int) $this->value,
            'json', 'array' => json_decode($this->value, true),
            'image', 'file' => $this->value ? url('storage/' . $this->value) : null,
            default => $this->value,
        };
    }

    /**
     * Set the value with type casting.
     */
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value),
            default => $value,
        };
    }

    /**
     * Get a setting value by key (static helper).
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return $setting->typed_value;
    }

    /**
     * Set a setting value by key (static helper).
     */
    public static function set(string $key, $value): void
    {
        $setting = static::where('key', $key)->first();

        if ($setting) {
            $setting->update([
                'value' => $value,
                'updated_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Get all settings as key-value array.
     */
    public static function getAll(): array
    {
        return static::all()->pluck('typed_value', 'key')->toArray();
    }

    /**
     * Get all public settings as key-value array.
     */
    public static function getPublic(): array
    {
        return static::public()->get()->pluck('typed_value', 'key')->toArray();
    }

    /**
     * Get settings grouped by group name.
     */
    public static function getGrouped(): array
    {
        return static::ordered()
            ->get()
            ->groupBy('group')
            ->toArray();
    }

    /**
     * Get available groups.
     */
    public static function getGroups(): array
    {
        return [
            'general' => 'General',
            'seo' => 'SEO',
            'media' => 'Media',
            'email' => 'Email',
            'security' => 'Security',
            'performance' => 'Performance',
        ];
    }

    /**
     * Get available types.
     */
    public static function getTypes(): array
    {
        return [
            'text' => 'Text',
            'textarea' => 'Text Area',
            'number' => 'Number',
            'boolean' => 'Boolean (Checkbox)',
            'select' => 'Select Dropdown',
            'json' => 'JSON',
            'image' => 'Image Upload',
            'file' => 'File Upload',
        ];
    }

    /**
     * Check if setting is valid for type.
     */
    public function validateValue($value): bool
    {
        return match ($this->type) {
            'boolean' => is_bool($value) || in_array($value, ['0', '1', 0, 1], true),
            'number', 'integer' => is_numeric($value),
            'json', 'array' => is_string($value) && json_validate($value) || is_array($value),
            'select' => is_string($value) && in_array($value, array_keys($this->options ?? [])),
            'text', 'textarea' => is_string($value),
            'image', 'file' => is_string($value) || is_null($value),
            default => true,
        };
    }

    /**
     * Get validation rules for setting.
     */
    public function getValidationRules(): array
    {
        return match ($this->type) {
            'boolean' => ['required', 'boolean'],
            'number', 'integer' => ['required', 'numeric'],
            'json', 'array' => ['required', 'json'],
            'select' => ['required', 'in:' . implode(',', array_keys($this->options ?? []))],
            'text' => ['required', 'string', 'max:255'],
            'textarea' => ['required', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'file' => ['nullable', 'string', 'max:500'],
            default => ['required'],
        };
    }
}
