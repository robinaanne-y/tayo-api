<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HouseholdRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Households\StoreHouseholdRequest;
use App\Http\Requests\Households\UpdateHouseholdRequest;
use App\Http\Resources\HouseholdResource;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseholdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        $households = $member
            ? $member->households()->withCount('members')->get()
            : collect();

        return response()->json([
            'data' => HouseholdResource::collection($households),
        ]);
    }

    public function store(StoreHouseholdRequest $request): JsonResponse
    {
        $user = $request->user();

        $household = DB::transaction(function () use ($request, $user) {
            $household = Household::create([
                'name' => $request->validated('name'),
                'created_by_user_id' => $user->id,
            ]);

            $member = Member::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $user->name],
            );

            $household->memberships()->create([
                'member_id' => $member->id,
                'role' => HouseholdRole::Owner,
            ]);

            return $household;
        });

        return response()->json([
            'data' => HouseholdResource::make($household),
        ], 201);
    }

    public function show(Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        return response()->json([
            'data' => HouseholdResource::make($household),
        ]);
    }

    public function update(UpdateHouseholdRequest $request, Household $household): JsonResponse
    {
        $household->update($request->validated());

        return response()->json([
            'data' => HouseholdResource::make($household),
        ]);
    }
}
