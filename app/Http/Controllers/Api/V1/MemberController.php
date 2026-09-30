<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
}
