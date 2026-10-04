<?php

namespace App\Models;

use App\Enums\TripStatus;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'household_id',
    'title',
    'destination',
    'start_at',
    'end_at',
    'notes',
    'status',
    'thumbnail_path',
    'created_by_member_id',
])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_at' => 'date',
            'end_at' => 'date',
            'status' => TripStatus::class,
        ];
    }

    /**
     * A relative path, not an absolute URL -- mirrors Member::avatarUrl()
     * so the mobile client doesn't bake in APP_URL.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->thumbnail_path ? '/storage/'.$this->thumbnail_path : null,
        );
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by_member_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'trip_participants')->withTimestamps();
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(TripItineraryItem::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function groceryItems(): HasMany
    {
        return $this->hasMany(GroceryItem::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(TripMemory::class);
    }
}
