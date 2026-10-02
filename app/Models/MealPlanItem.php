<?php

namespace App\Models;

use App\Enums\MealSlot;
use Database\Factories\MealPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['household_id', 'date', 'slot', 'title', 'added_by_member_id'])]
class MealPlanItem extends Model
{
    /** @use HasFactory<MealPlanItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'slot' => MealSlot::class,
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

    /**
     * Explicit find-then-write rather than updateOrCreate(): the `slot`
     * enum cast interferes with updateOrCreate()'s raw attribute-array
     * WHERE matching (it never finds the existing row, then fails the
     * unique(household_id, date, slot) constraint on insert instead of
     * updating). Used by both MealPlanItemController::store() and
     * MealRequestController::approve(), which both need "set this
     * household's meal for this date/slot" with identical semantics.
     */
    public static function upsertFor(
        Household $household,
        string $date,
        string $slot,
        string $title,
        int $addedByMemberId,
    ): self {
        $item = $household->mealPlanItems()
            ->whereDate('date', $date)
            ->where('slot', $slot)
            ->first();

        if ($item === null) {
            return $household->mealPlanItems()->create([
                'date' => $date,
                'slot' => $slot,
                'title' => $title,
                'added_by_member_id' => $addedByMemberId,
            ]);
        }

        $item->update([
            'title' => $title,
            'added_by_member_id' => $addedByMemberId,
        ]);

        return $item;
    }
}
