<?php

namespace App\Models;

use Database\Factories\TripMemoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'member_id', 'content'])]
class TripMemory extends Model
{
    /** @use HasFactory<TripMemoryFactory> */
    use HasFactory;

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
