<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $links = $this->profile_links;
        if (! is_array($links)) {
            $links = [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->is_admin ? 'admin' : 'author',
            'job_title' => $this->job_title,
            'position' => $this->position,
            'bio' => $this->bio,
            'profile_photo_url' => $this->profile_photo_url,
            'cover_photo_url' => $this->cover_photo_url,
            'profile_links' => array_values(array_filter($links, function ($row) {
                return is_array($row)
                    && filled($row['label'] ?? null)
                    && filled($row['url'] ?? null);
            })),
        ];
    }
}
