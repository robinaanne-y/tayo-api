<?php

namespace App\Events;

use App\Models\MealRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Extension point for Phase 9 — mirrors PermissionRequestApproved.
 */
class MealRequestApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public MealRequest $mealRequest)
    {
    }
}
