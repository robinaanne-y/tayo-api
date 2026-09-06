<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one-time response to creating an invitation — the only place the
 * plaintext token is ever exposed.
 */
class InvitationResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly string $plainToken,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainToken,
            'link' => "tayo://invite/{$this->plainToken}",
            'role' => $this->role->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
