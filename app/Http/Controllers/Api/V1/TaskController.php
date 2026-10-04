<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Household;
use App\Models\RecurringRule;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $query = $household->tasks()->with(['assignee', 'createdBy', 'completedBy', 'recurringRule']);

        if ($request->filled('assignee_member_id')) {
            $query->where('assigned_member_id', $request->integer('assignee_member_id'));
        }

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'completed' => $query->whereNotNull('completed_at'),
                'pending' => $query->whereNull('completed_at'),
                'overdue' => $query->whereNull('completed_at')->whereDate('due_at', '<', now()->toDateString()),
                default => null,
            };
        }

        if ($request->filled('due_before')) {
            $query->whereDate('due_at', '<=', $request->date('due_before'));
        }

        if ($request->filled('due_after')) {
            $query->whereDate('due_at', '>=', $request->date('due_after'));
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->integer('trip_id'));
        }

        $tasks = $query->orderByRaw('due_at IS NULL')->orderBy('due_at')->get();

        return response()->json([
            'data' => TaskResource::collection($tasks),
        ]);
    }

    public function store(StoreTaskRequest $request, Household $household): JsonResponse
    {
        $recurrence = $request->validated('recurrence');

        $task = DB::transaction(function () use ($request, $household, $recurrence) {
            $recurringRuleId = null;

            if ($recurrence !== null) {
                $rule = RecurringRule::create([
                    'frequency' => $recurrence['frequency'],
                    'interval' => $recurrence['interval'] ?? 1,
                    'by_day' => $recurrence['by_day'] ?? null,
                    'ends_at' => $recurrence['ends_at'] ?? null,
                    'occurrence_count' => $recurrence['occurrence_count'] ?? null,
                ]);
                $recurringRuleId = $rule->id;
            }

            // Unlike Events, recurrence never expands eagerly here -- only
            // the one occurrence the user actually created is materialized.
            // Future occurrences are generated one at a time by the
            // tasks:generate-occurrences scheduled command.
            return $household->tasks()->create([
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'due_at' => $request->validated('due_at'),
                'created_by_member_id' => $request->user()->member->id,
                'assigned_member_id' => $request->validated('assigned_member_id'),
                'recurring_rule_id' => $recurringRuleId,
                'trip_id' => $request->validated('trip_id'),
            ]);
        });

        $task->load(['assignee', 'createdBy', 'completedBy', 'recurringRule']);

        return response()->json([
            'data' => TaskResource::make($task),
        ], 201);
    }

    public function show(Household $household, Task $task): JsonResponse
    {
        $task = $this->taskFor($household, $task);

        $this->authorize('view', $household);

        $task->load(['assignee', 'createdBy', 'completedBy', 'recurringRule']);

        return response()->json([
            'data' => TaskResource::make($task),
        ]);
    }

    public function update(UpdateTaskRequest $request, Household $household, Task $task): JsonResponse
    {
        $task = $this->taskFor($household, $task);

        $task->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'due_at' => $request->validated('due_at'),
            'assigned_member_id' => $request->validated('assigned_member_id'),
        ]);

        $task->load(['assignee', 'createdBy', 'completedBy', 'recurringRule']);

        return response()->json([
            'data' => TaskResource::make($task),
        ]);
    }

    public function complete(Request $request, Household $household, Task $task): JsonResponse
    {
        $task = $this->taskFor($household, $task);

        $this->authorize('toggleTask', [$household, $task]);

        $task->update([
            'completed_at' => now(),
            'completed_by_member_id' => $request->user()->member->id,
        ]);

        return response()->json([
            'data' => TaskResource::make($task->fresh(['assignee', 'createdBy', 'completedBy', 'recurringRule'])),
        ]);
    }

    public function uncomplete(Household $household, Task $task): JsonResponse
    {
        $task = $this->taskFor($household, $task);

        $this->authorize('toggleTask', [$household, $task]);

        $task->update([
            'completed_at' => null,
            'completed_by_member_id' => null,
        ]);

        return response()->json([
            'data' => TaskResource::make($task->fresh(['assignee', 'createdBy', 'completedBy', 'recurringRule'])),
        ]);
    }

    public function destroy(Household $household, Task $task): JsonResponse
    {
        $task = $this->taskFor($household, $task);

        $this->authorize('manageTask', [$household, $task]);

        $task->delete();

        return response()->json(null, 204);
    }

    private function taskFor(Household $household, Task $task): Task
    {
        abort_if($task->household_id !== $household->id, 404);

        return $task;
    }
}
