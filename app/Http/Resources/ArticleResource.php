<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Article */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'og_image_alt' => $this->og_image_alt,
            'no_index' => (bool) $this->no_index,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->resolvedThumbnailUrl(),
            'sdg_goals' => $this->sdg_goals,
            'featured' => (bool) $this->featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', function () {
                if ($this->author === null) {
                    return null;
                }

                return (new AuthorResource($this->author))->resolve();
            }),
        ];
    }
}
