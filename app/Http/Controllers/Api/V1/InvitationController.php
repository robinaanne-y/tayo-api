<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Http\Resources\InvitationPreviewResource;
use App\Http\Resources\InvitationResource;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, Household $household): JsonResponse
    {
        $plain = HouseholdInvitation::newPlainToken();

        $invitation = $household->invitations()->create([
            'created_by_member_id' => $request->user()->member->id,
            'role' => $request->validated('role'),
            'token_hash' => HouseholdInvitation::hashToken($plain),
            'expires_at' => now()->addDays(HouseholdInvitation::DEFAULT_TTL_DAYS),
        ]);

        return response()->json([
            'data' => new InvitationResource($invitation, $plain),
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = HouseholdInvitation::query()
            ->where('token_hash', HouseholdInvitation::hashToken($token))
            ->with('household')
            ->firstOrFail();

        return response()->json([
            'data' => InvitationPreviewResource::make($invitation),
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = HouseholdInvitation::query()
            ->where('token_hash', HouseholdInvitation::hashToken($token))
            ->with('household')
            ->firstOrFail();

        if (! $invitation->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation has expired or already been used.'],
            ]);
        }

        $user = $request->user();

        if ($user->membershipFor($invitation->household)) {
            throw ValidationException::withMessages([
                'token' => ['You are already a member of this household.'],
            ]);
        }

        DB::transaction(function () use ($user, $invitation) {
            $member = Member::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $user->name],
            );

            $invitation->household->memberships()->create([
                'member_id' => $member->id,
                'role' => $invitation->role,
            ]);

            $invitation->forceFill([
                'used_at' => now(),
                'used_by_member_id' => $member->id,
            ])->save();
        });

        return response()->json([
            'message' => 'Joined household.',
        ]);
    }
}
