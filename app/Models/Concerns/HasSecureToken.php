<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Shared behavior for single-use, expiring, hashed tokens (household
 * invitations, member activation links). Only the hash is ever persisted;
 * the plaintext token is returned to the caller once, at generation time.
 */
trait HasSecureToken
{
    public const DEFAULT_TTL_DAYS = 7;

    public static function newPlainToken(): string
    {
        return Str::random(40);
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function markUsed(): void
    {
        $this->forceFill(['used_at' => now()])->save();
    }

    public function isValid(): bool
    {
        return $this->used_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
