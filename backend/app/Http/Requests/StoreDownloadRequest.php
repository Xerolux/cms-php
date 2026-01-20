<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,zip,doc,docx,xls,xlsx|max:102400', // 100MB
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'access_level' => 'in:public,registered,premium',
            'expires_at' => 'nullable|date',
        ];
    }
}
