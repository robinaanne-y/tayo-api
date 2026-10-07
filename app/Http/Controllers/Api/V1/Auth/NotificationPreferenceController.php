<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\ReminderCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        $overrides = $member->notificationPreferences()
            ->get()
            ->keyBy(fn ($pref) => $pref->category->value)
            ->map(fn ($pref) => $pref->enabled);

        $data = [];
        foreach (ReminderCategory::cases() as $category) {
            $data[] = [
                'category' => $category->value,
                // Unset categories default to enabled -- see
                // ReminderComputer for why "always notify" is the
                // implicit default here.
                'enabled' => $overrides->get($category->value, true),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $member = $request->user()->member;

        NotificationPreference::updateOrCreate(
            ['member_id' => $member->id, 'category' => $request->validated('category')],
            ['enabled' => $request->validated('enabled')],
        );

        return $this->index($request);
    }
}
