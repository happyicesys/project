<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * List tasks, optionally filtered by assigned agent or status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->latest();

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'tasks' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    /**
     * Create a new task (Manager or CEO can assign tasks to agents).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|string|exists:agents,agent_id',
            'created_by' => 'required|string',
            'priority' => 'nullable|in:low,medium,high,critical',
            'payload' => 'nullable|array',
            'deadline_at' => 'nullable|date',
        ]);

        $task = Task::create($validated);

        ActivityLog::record(
            $validated['created_by'],
            'task.created',
            'task',
            $task->getKey(),
            ['title' => $task->title, 'assigned_to' => $task->assigned_to],
        );

        rescue(fn () => Log::channel('agents')->info('TASK_CREATED', [
            'task_uuid'   => $task->getKey(),
            'title'       => $task->title,
            'assigned_to' => $task->assigned_to,
            'created_by'  => $task->created_by,
            'priority'    => $task->priority,
        ]));

        return response()->json(['task' => $task], 201);
    }

    /**
     * Update task status (agent reports progress/completion).
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $task = Task::findOrFail($uuid);

        $validated = $request->validate([
            'status' => 'nullable|in:pending,in_progress,completed,failed,cancelled',
            'result' => 'nullable|array',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'in_progress' && ! $task->started_at) {
            $validated['started_at'] = now();
        }

        if (isset($validated['status']) && in_array($validated['status'], ['completed', 'failed'])) {
            $validated['completed_at'] = now();
        }

        $previousStatus = $task->status;
        $task->update($validated);
        $fresh = $task->fresh();

        $actor = $request->authenticated_agent?->agent_id ?? 'manager';

        ActivityLog::record(
            $actor,
            "task.{$fresh->status}",
            'task',
            $fresh->getKey(),
        );

        // Calculate how long the task spent in its previous state
        $durationSec = null;
        if ($fresh->started_at && in_array($fresh->status, ['completed', 'failed'])) {
            $durationSec = (int) $fresh->started_at->diffInSeconds($fresh->completed_at);
        }

        $logContext = [
            'task_uuid'      => $fresh->getKey(),
            'title'          => $fresh->title,
            'actor'          => $actor,
            'status_from'    => $previousStatus,
            'status_to'      => $fresh->status,
            'duration_sec'   => $durationSec,
        ];

        $logLevel = $fresh->status === 'failed' ? 'warning' : 'info';
        Log::channel('agents')->$logLevel('TASK_UPDATED', $logContext);

        return response()->json(['task' => $fresh]);
    }
}
