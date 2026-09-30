<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activation\ClaimActivationRequest;
use App\Http\Resources\ActivationLinkResource;
use App\Http\Resources\ActivationPreviewResource;
use App\Http\Resources\UserResource;
use App\Models\Household;
use App\Models\Member;
use App\Models\MemberActivationToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ActivationController extends Controller
{
    public function store(Household $household, Member $member): JsonResponse
    {
        $this->authorize('addMember', $household);

        if (! $household->members()->where('members.id', $member->id)->exists()) {
            abort(404);
        }

        if (! $member->is_placeholder) {
            throw ValidationException::withMessages([
                'member' => ['This member already has an account.'],
            ]);
        }

        // A member should only ever have one live activation link.
        $member->activationTokens()->valid()->update(['used_at' => now()]);

        $plain = MemberActivationToken::newPlainToken();

        $token = $member->activationTokens()->create([
            'token_hash' => MemberActivationToken::hashToken($plain),
            'expires_at' => now()->addDays(MemberActivationToken::DEFAULT_TTL_DAYS),
        ]);

        return response()->json([
            'data' => new ActivationLinkResource($token, $plain),
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $activation = MemberActivationToken::query()
            ->where('token_hash', MemberActivationToken::hashToken($token))
            ->with('member.households')
            ->firstOrFail();

        return response()->json([
            'data' => ActivationPreviewResource::make($activation),
        ]);
    }

    public function claim(ClaimActivationRequest $request, string $token): JsonResponse
    {
        $activation = MemberActivationToken::query()
            ->where('token_hash', MemberActivationToken::hashToken($token))
            ->with('member')
            ->firstOrFail();

        if (! $activation->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['This activation link has expired or already been used.'],
            ]);
        }

        [$user, $authToken] = DB::transaction(function () use ($request, $activation) {
            $user = User::create([
                'name' => $activation->member->name,
                'email' => $request->validated('email'),
                'password' => Hash::make($request->validated('password')),
            ]);

            $activation->member->update(['user_id' => $user->id]);
            $activation->markUsed();

            $authToken = $user->createToken($request->userAgent() ?? 'mobile')->plainTextToken;

            return [$user, $authToken];
        });

        $user->load('member.households');

        return response()->json([
            'data' => UserResource::make($user),
            'token' => $authToken,
        ], 201);
    }
}
