<?php

namespace App\Models;

use Database\Factories\TripItineraryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'title', 'description', 'scheduled_at', 'created_by_member_id'])]
class TripItineraryItem extends Model
{
    /** @use HasFactory<TripItineraryItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by_member_id');
    }
}
