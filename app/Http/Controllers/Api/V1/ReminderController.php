<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Support\ReminderComputer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $reminders = (new ReminderComputer)->forMember($household, $request->user()->member);

        return response()->json(['data' => $reminders]);
    }
}
