<?php

namespace App\Models;

use Database\Factories\GroceryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'household_id',
    'name',
    'quantity',
    'unit',
    'category',
    'added_by_member_id',
    'purchased_at',
    'purchased_by_member_id',
])]
class GroceryItem extends Model
{
    /** @use HasFactory<GroceryItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'added_by_member_id');
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'purchased_by_member_id');
    }
}
