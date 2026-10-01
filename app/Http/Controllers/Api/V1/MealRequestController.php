<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RequestStatus;
use App\Events\MealRequestApproved;
use App\Events\MealRequestCreated;
use App\Events\MealRequestDeclined;
use App\Http\Controllers\Controller;
use App\Http\Requests\MealRequests\ApproveMealRequestRequest;
use App\Http\Requests\MealRequests\DeclineMealRequestRequest;
use App\Http\Requests\MealRequests\StoreMealRequestRequest;
use App\Http\Requests\MealRequests\UpdateMealRequestRequest;
use App\Http\Resources\MealRequestResource;
use App\Models\Household;
use App\Models\MealPlanItem;
use App\Models\MealRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MealRequestController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $query = $household->mealRequests()->with(['requester', 'respondedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $requests = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => MealRequestResource::collection($requests),
        ]);
    }

    public function store(StoreMealRequestRequest $request, Household $household): JsonResponse
    {
        $mealRequest = $household->mealRequests()->create([
            'requester_member_id' => $request->user()->member->id,
            'requested_date' => $request->validated('requested_date'),
            'requested_slot' => $request->validated('requested_slot'),
            'title' => $request->validated('title'),
            'status' => RequestStatus::Pending,
        ]);

        $mealRequest->load(['requester', 'respondedBy']);

        event(new MealRequestCreated($mealRequest));

        return response()->json([
            'data' => MealRequestResource::make($mealRequest),
        ], 201);
    }

    public function show(Household $household, MealRequest $mealRequest): JsonResponse
    {
        $mealRequest = $this->requestFor($household, $mealRequest);

        $this->authorize('view', $household);

        $mealRequest->load(['requester', 'respondedBy']);

        return response()->json([
            'data' => MealRequestResource::make($mealRequest),
        ]);
    }

    public function update(
        UpdateMealRequestRequest $request,
        Household $household,
        MealRequest $mealRequest,
    ): JsonResponse {
        $mealRequest = $this->requestFor($household, $mealRequest);

        $mealRequest->update([
            'requested_date' => $request->validated('requested_date'),
            'requested_slot' => $request->validated('requested_slot'),
            'title' => $request->validated('title'),
        ]);

        $mealRequest->load(['requester', 'respondedBy']);

        return response()->json([
            'data' => MealRequestResource::make($mealRequest),
        ]);
    }

    public function cancel(Request $request, Household $household, MealRequest $mealRequest): JsonResponse
    {
        $mealRequest = $this->requestFor($household, $mealRequest);

        $this->authorize('cancelMealRequest', [$household, $mealRequest]);

        $mealRequest->cancel();

        return response()->json([
            'data' => MealRequestResource::make($mealRequest->fresh(['requester', 'respondedBy'])),
        ]);
    }

    public function approve(
        ApproveMealRequestRequest $request,
        Household $household,
        MealRequest $mealRequest,
    ): JsonResponse {
        $mealRequest = $this->requestFor($household, $mealRequest);
        $responder = $request->user()->member;

        DB::transaction(function () use ($request, $household, $mealRequest, $responder) {
            $mealRequest->approve($responder, $request->validated('response_note'));

            $date = $request->validated('date') ?? $mealRequest->requested_date->toDateString();
            $slot = $request->validated('slot') ?? $mealRequest->requested_slot->value;

            $item = MealPlanItem::upsertFor($household, $date, $slot, $mealRequest->title, $responder->id);

            $mealRequest->forceFill(['meal_plan_item_id' => $item->id])->save();
        });

        $mealRequest->load(['requester', 'respondedBy']);

        event(new MealRequestApproved($mealRequest));

        return response()->json([
            'data' => MealRequestResource::make($mealRequest),
        ]);
    }

    public function decline(
        DeclineMealRequestRequest $request,
        Household $household,
        MealRequest $mealRequest,
    ): JsonResponse {
        $mealRequest = $this->requestFor($household, $mealRequest);

        $mealRequest->decline($request->user()->member, $request->validated('response_note'));
        $mealRequest->load(['requester', 'respondedBy']);

        event(new MealRequestDeclined($mealRequest));

        return response()->json([
            'data' => MealRequestResource::make($mealRequest),
        ]);
    }

    public function acknowledge(Request $request, Household $household, MealRequest $mealRequest): JsonResponse
    {
        $mealRequest = $this->requestFor($household, $mealRequest);

        $this->authorize('acknowledgeMealRequest', [$household, $mealRequest]);

        $mealRequest->forceFill(['requester_acknowledged_at' => now()])->save();

        return response()->json([
            'data' => MealRequestResource::make($mealRequest->fresh(['requester', 'respondedBy'])),
        ]);
    }

    private function requestFor(Household $household, MealRequest $mealRequest): MealRequest
    {
        abort_if($mealRequest->household_id !== $household->id, 404);

        return $mealRequest;
    }
}
