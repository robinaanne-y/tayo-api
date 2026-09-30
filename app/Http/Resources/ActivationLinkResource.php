<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one-time response to generating an activation link — the only place
 * the plaintext token is ever exposed.
 */
class ActivationLinkResource extends JsonResource
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
            'link' => "tayo://activate/{$this->plainToken}",
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
