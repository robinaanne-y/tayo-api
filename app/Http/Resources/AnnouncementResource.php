<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'author_member_id' => $this->author_member_id,
            'author_name' => $this->author?->name,
            'created_at' => $this->created_at,
        ];
    }
}
