<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PermissionRequestApproved;
use App\Events\PermissionRequestCreated;
use App\Events\PermissionRequestDeclined;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequests\AddRequestConditionRequest;
use App\Http\Requests\PermissionRequests\ApprovePermissionRequestRequest;
use App\Http\Requests\PermissionRequests\DeclinePermissionRequestRequest;
use App\Http\Requests\PermissionRequests\StorePermissionRequestRequest;
use App\Http\Requests\PermissionRequests\UpdatePermissionRequestRequest;
use App\Http\Resources\PermissionRequestResource;
use App\Enums\RequestStatus;
use App\Models\Household;
use App\Models\PermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionRequestController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('viewRequest', $household);

        $query = $household->requests()->with(['requester', 'respondedBy', 'conditions.createdBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $requests = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => PermissionRequestResource::collection($requests),
        ]);
    }

    public function store(StorePermissionRequestRequest $request, Household $household): JsonResponse
    {
        $permissionRequest = $household->requests()->create([
            'requester_member_id' => $request->user()->member->id,
            'type' => $request->validated('type'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'requested_start_at' => $request->validated('requested_start_at'),
            'requested_end_at' => $request->validated('requested_end_at'),
            'status' => RequestStatus::Pending,
        ]);

        $permissionRequest->load(['requester', 'respondedBy', 'conditions.createdBy']);

        event(new PermissionRequestCreated($permissionRequest));

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest),
        ], 201);
    }

    public function show(Household $household, PermissionRequest $permissionRequest): JsonResponse
    {
        $permissionRequest = $this->requestFor($household, $permissionRequest);

        $this->authorize('viewRequest', $household);

        $permissionRequest->load(['requester', 'respondedBy', 'conditions.createdBy']);

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest),
        ]);
    }

    public function update(
        UpdatePermissionRequestRequest $request,
        Household $household,
        PermissionRequest $permissionRequest,
    ): JsonResponse {
        $permissionRequest = $this->requestFor($household, $permissionRequest);

        $permissionRequest->update([
            'type' => $request->validated('type'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'requested_start_at' => $request->validated('requested_start_at'),
            'requested_end_at' => $request->validated('requested_end_at'),
        ]);

        $permissionRequest->load(['requester', 'respondedBy', 'conditions.createdBy']);

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest),
        ]);
    }

    public function cancel(Request $request, Household $household, PermissionRequest $permissionRequest): JsonResponse
    {
        $permissionRequest = $this->requestFor($household, $permissionRequest);

        $this->authorize('cancelRequest', [$household, $permissionRequest]);

        $permissionRequest->cancel();

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest->fresh(['requester', 'respondedBy', 'conditions.createdBy'])),
        ]);
    }

    public function approve(
        ApprovePermissionRequestRequest $request,
        Household $household,
        PermissionRequest $permissionRequest,
    ): JsonResponse {
        $permissionRequest = $this->requestFor($household, $permissionRequest);
        $responder = $request->user()->member;

        DB::transaction(function () use ($request, $household, $permissionRequest, $responder) {
            $permissionRequest->approve($responder, $request->validated('response_note'));

            foreach ($request->validated('conditions', []) as $description) {
                $permissionRequest->conditions()->create([
                    'created_by_member_id' => $responder->id,
                    'description' => $description,
                ]);
            }

            if ($request->boolean('create_event')) {
                $event = $household->events()->create([
                    'creator_member_id' => $permissionRequest->requester_member_id,
                    'title' => $permissionRequest->title,
                    'description' => $permissionRequest->description,
                    'start_at' => $permissionRequest->requested_start_at,
                    'end_at' => $permissionRequest->requested_end_at,
                    'visibility' => 'household',
                ]);
                $event->participants()->sync([$permissionRequest->requester_member_id]);

                $permissionRequest->forceFill(['promoted_event_id' => $event->id])->save();
            }
        });

        $permissionRequest->load(['requester', 'respondedBy', 'conditions.createdBy']);

        event(new PermissionRequestApproved($permissionRequest));

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest),
        ]);
    }

    public function decline(
        DeclinePermissionRequestRequest $request,
        Household $household,
        PermissionRequest $permissionRequest,
    ): JsonResponse {
        $permissionRequest = $this->requestFor($household, $permissionRequest);

        $permissionRequest->decline($request->user()->member, $request->validated('response_note'));
        $permissionRequest->load(['requester', 'respondedBy', 'conditions.createdBy']);

        event(new PermissionRequestDeclined($permissionRequest));

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest),
        ]);
    }

    public function addCondition(
        AddRequestConditionRequest $request,
        Household $household,
        PermissionRequest $permissionRequest,
    ): JsonResponse {
        $permissionRequest = $this->requestFor($household, $permissionRequest);

        $permissionRequest->conditions()->create([
            'created_by_member_id' => $request->user()->member->id,
            'description' => $request->validated('description'),
        ]);

        $permissionRequest->load(['requester', 'respondedBy', 'conditions.createdBy']);

        return response()->json([
            'data' => PermissionRequestResource::make($permissionRequest),
        ], 201);
    }

    private function requestFor(Household $household, PermissionRequest $permissionRequest): PermissionRequest
    {
        abort_if($permissionRequest->household_id !== $household->id, 404);

        return $permissionRequest;
    }
}
