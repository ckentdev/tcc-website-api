<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CampusEvent */
class CampusEventResource extends JsonResource
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
            'body' => $this->body,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => $this->location,
            'image_url' => $this->image_url,
            'sdg_goals' => $this->sdg_goals,
            'author' => $this->whenLoaded('author', function () {
                if ($this->author === null) {
                    return null;
                }

                return [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                ];
            }),
        ];
    }
}
