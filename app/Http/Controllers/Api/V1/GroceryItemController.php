<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroceryItems\StoreGroceryItemRequest;
use App\Http\Requests\GroceryItems\UpdateGroceryItemRequest;
use App\Http\Resources\GroceryItemResource;
use App\Models\GroceryItem;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroceryItemController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $query = $household->groceryItems()->with(['addedBy', 'purchasedBy']);

        if ($request->filled('purchased')) {
            $request->boolean('purchased')
                ? $query->whereNotNull('purchased_at')
                : $query->whereNull('purchased_at');
        }

        $items = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => GroceryItemResource::collection($items),
        ]);
    }

    public function store(StoreGroceryItemRequest $request, Household $household): JsonResponse
    {
        $item = $household->groceryItems()->create([
            'name' => $request->validated('name'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'category' => $request->validated('category'),
            'added_by_member_id' => $request->user()->member->id,
        ]);

        $item->load(['addedBy', 'purchasedBy']);

        return response()->json([
            'data' => GroceryItemResource::make($item),
        ], 201);
    }

    public function update(
        UpdateGroceryItemRequest $request,
        Household $household,
        GroceryItem $groceryItem,
    ): JsonResponse {
        $groceryItem = $this->itemFor($household, $groceryItem);

        $groceryItem->update([
            'name' => $request->validated('name'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'category' => $request->validated('category'),
        ]);

        $groceryItem->load(['addedBy', 'purchasedBy']);

        return response()->json([
            'data' => GroceryItemResource::make($groceryItem),
        ]);
    }

    public function purchase(Request $request, Household $household, GroceryItem $groceryItem): JsonResponse
    {
        $groceryItem = $this->itemFor($household, $groceryItem);

        $this->authorize('manageGroceryItem', $household);

        $groceryItem->update([
            'purchased_at' => now(),
            'purchased_by_member_id' => $request->user()->member->id,
        ]);

        return response()->json([
            'data' => GroceryItemResource::make($groceryItem->fresh(['addedBy', 'purchasedBy'])),
        ]);
    }

    public function unpurchase(Household $household, GroceryItem $groceryItem): JsonResponse
    {
        $groceryItem = $this->itemFor($household, $groceryItem);

        $this->authorize('manageGroceryItem', $household);

        $groceryItem->update([
            'purchased_at' => null,
            'purchased_by_member_id' => null,
        ]);

        return response()->json([
            'data' => GroceryItemResource::make($groceryItem->fresh(['addedBy', 'purchasedBy'])),
        ]);
    }

    public function destroy(Household $household, GroceryItem $groceryItem): JsonResponse
    {
        $groceryItem = $this->itemFor($household, $groceryItem);

        $this->authorize('manageGroceryItem', $household);

        $groceryItem->delete();

        return response()->json(null, 204);
    }

    private function itemFor(Household $household, GroceryItem $groceryItem): GroceryItem
    {
        abort_if($groceryItem->household_id !== $household->id, 404);

        return $groceryItem;
    }
}
