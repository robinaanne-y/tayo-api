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
            ? $member->households()->withCount('members')->with('mealApprover')->get()
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
                'color' => $request->validated('color'),
                'emoji' => $request->validated('emoji'),
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

            // The first household someone creates becomes their default
            // automatically -- there's no "unset" state to manage from
            // onboarding. Doesn't override an existing explicit pick if
            // they go on to create further households later.
            if ($user->default_household_id === null) {
                $user->update(['default_household_id' => $household->id]);
            }

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
            'data' => HouseholdResource::make($household->load('mealApprover')),
        ]);
    }

    public function update(UpdateHouseholdRequest $request, Household $household): JsonResponse
    {
        $household->update($request->validated());

        return response()->json([
            'data' => HouseholdResource::make($household->load('mealApprover')),
        ]);
    }
}
