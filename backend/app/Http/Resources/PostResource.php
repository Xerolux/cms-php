<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'view_count' => $this->view_count,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'language' => $this->language,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'author' => new UserResource($this->whenLoaded('author')),
            'featured_image' => new MediaResource($this->whenLoaded('featuredImage')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'downloads' => DownloadResource::collection($this->whenLoaded('downloads')),
        ];
    }
}
