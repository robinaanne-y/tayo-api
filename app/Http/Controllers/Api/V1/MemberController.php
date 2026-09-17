<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HouseholdRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Requests\Members\UpdateMemberAvatarRequest;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Household;
use App\Models\HouseholdMembership;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    public function index(Household $household): JsonResponse
    {
        $this->authorize('viewMembers', $household);

        $members = $household->members()->get();

        return response()->json([
            'data' => MemberResource::collection($members),
        ]);
    }

    public function store(StoreMemberRequest $request, Household $household): JsonResponse
    {
        $member = DB::transaction(function () use ($request, $household) {
            $member = Member::create([
                'name' => $request->validated('name'),
                'birth_date' => $request->validated('birth_date'),
            ]);

            $household->memberships()->create([
                'member_id' => $member->id,
                'role' => $request->validated('role'),
            ]);

            return $member;
        });

        $member->setRelation('pivot', $household->memberships()
            ->where('member_id', $member->id)
            ->first());

        return response()->json([
            'data' => MemberResource::make($member),
        ], 201);
    }

    private function membershipFor(Household $household, Member $member): HouseholdMembership
    {
        $membership = $household->memberships()->where('member_id', $member->id)->first();
        abort_if($membership === null, 404);

        return $membership;
    }

    public function update(UpdateMemberRequest $request, Household $household, Member $member): JsonResponse
    {
        $membership = $this->membershipFor($household, $member);

        DB::transaction(function () use ($request, $membership, $member) {
            $member->update([
                'name' => $request->validated('name'),
                'birth_date' => $request->validated('birth_date'),
            ]);

            // The sole Owner's role is immutable here — changing it belongs
            // to a dedicated ownership-transfer flow, not a general edit.
            // `role` is optional on this request specifically so the client
            // can omit it for that case instead of resending the current
            // value (which validation rejects outright — see
            // UpdateMemberRequest).
            if ($membership->role !== HouseholdRole::Owner && $request->has('role')) {
                $membership->update(['role' => $request->validated('role')]);
            }
        });

        $member->setRelation('pivot', $membership->fresh());

        return response()->json([
            'data' => MemberResource::make($member),
        ]);
    }

    public function updateAvatar(UpdateMemberAvatarRequest $request, Household $household, Member $member): JsonResponse
    {
        $this->membershipFor($household, $member);

        $oldPath = $member->avatar_path;
        $newPath = $request->file('avatar')->store('avatars', 'public');

        $member->update(['avatar_path' => $newPath]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'data' => MemberResource::make($member),
        ]);
    }
}
