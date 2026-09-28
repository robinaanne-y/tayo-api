<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['household_id', 'creator_member_id', 'title', 'description', 'location', 'start_at', 'end_at', 'visibility'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'creator_member_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'event_participants')->withTimestamps();
    }

    /**
     * Additional households a `selected_households` event is shared into,
     * beyond its own home `household_id`.
     */
    public function sharedHouseholds(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'event_households')->withTimestamps();
    }
}
