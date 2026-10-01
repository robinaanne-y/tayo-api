<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MealPlanItems\StoreMealPlanItemRequest;
use App\Http\Resources\MealPlanItemResource;
use App\Models\Household;
use App\Models\MealPlanItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanItemController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $query = $household->mealPlanItems()->with('addedBy');

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->date('from')->toDateString());
        }

        if ($request->filled('to')) {
            $query->where('date', '<=', $request->date('to')->toDateString());
        }

        $items = $query->orderBy('date')->get();

        return response()->json([
            'data' => MealPlanItemResource::collection($items),
        ]);
    }

    /**
     * Upsert, not create-only: there's no separate update endpoint since
     * "set Tuesday's dinner" and "change Tuesday's dinner" are the same
     * action from the user's perspective, keyed on (date, slot).
     */
    public function store(StoreMealPlanItemRequest $request, Household $household): JsonResponse
    {
        $item = MealPlanItem::upsertFor(
            $household,
            $request->validated('date'),
            $request->validated('slot'),
            $request->validated('title'),
            $request->user()->member->id,
        );

        $item->load('addedBy');

        return response()->json([
            'data' => MealPlanItemResource::make($item),
        ], $item->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Household $household, MealPlanItem $mealPlanItem): JsonResponse
    {
        $mealPlanItem = $this->itemFor($household, $mealPlanItem);

        $this->authorize('manageMealPlanItem', $household);

        $mealPlanItem->delete();

        return response()->json(null, 204);
    }

    private function itemFor(Household $household, MealPlanItem $mealPlanItem): MealPlanItem
    {
        abort_if($mealPlanItem->household_id !== $household->id, 404);

        return $mealPlanItem;
    }
}
