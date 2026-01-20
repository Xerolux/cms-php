<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,pdf|max:51200', // 50MB
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
        ];
    }
}
