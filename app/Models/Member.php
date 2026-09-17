<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'birth_date', 'avatar_path', 'user_id'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class);
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_memberships')
            ->using(HouseholdMembership::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function activationTokens(): HasMany
    {
        return $this->hasMany(MemberActivationToken::class);
    }

    /**
     * A member without a linked user account is a placeholder
     * (e.g. a child) managed on behalf of by an adult/owner.
     */
    protected function isPlaceholder(): Attribute
    {
        return Attribute::get(fn () => $this->user_id === null);
    }

    /**
     * Deliberately a path relative to the API host (`/storage/...`), not an
     * absolute URL — baking in APP_URL here would break clients that reach
     * the API through a different host than the one configured in .env
     * (e.g. a phone on the LAN using the dev machine's network IP).
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar_path ? '/storage/'.$this->avatar_path : null,
        );
    }
}
