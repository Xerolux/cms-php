<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image_id' => 'nullable|exists:media,id',
            'status' => 'in:draft,scheduled,published,archived',
            'published_at' => 'nullable|date',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'array',
            'tags.*' => 'exists:tags,id',
            'downloads' => 'array',
            'downloads.*' => 'exists:downloads,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ];
    }
}
