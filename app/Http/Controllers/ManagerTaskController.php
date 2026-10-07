<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagerTaskController extends Controller
{
    public function executives(Request $request): JsonResponse
    {
        $manager = $this->manager($request);

        $executives = User::query()
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->where('role_id', 4)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status', 'orgnization_id', 'manager_id']);

        return response()->json(['executives' => $executives]);
    }

    public function tasks(Request $request, int $executiveId): JsonResponse
    {
        $manager = $this->manager($request);
        $executive = $this->assignedExecutive($manager, $executiveId);
        $tasks = Task::query()
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->where('executive_id', $executive->id)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['executive' => $executive, 'tasks' => $tasks]);
    }

    public function availableTasks(Request $request): JsonResponse
    {
        $manager = $this->manager($request);
        $tasks = Task::query()
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->whereNull('executive_id')
            ->with('clientAdmin:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    public function assignTasks(Request $request): JsonResponse
    {
        $manager = $this->manager($request);
        $validated = $request->validate([
            'executive_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('manager_id', $manager->id)
                    ->where('orgnization_id', $manager->orgnization_id)
                    ->where('role_id', 4)
                    ->where('status', 1)
                    ->whereNull('deleted_at')),
            ],
            'task_ids' => ['required', 'array', 'min:1', 'max:30'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $tasks = Task::query()
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->whereNull('executive_id')
            ->whereIn('id', $validated['task_ids'])
            ->get();

        if ($tasks->count() !== count($validated['task_ids'])) {
            return response()->json([
                'message' => 'One or more selected tasks are not available to this Manager.',
            ], 409);
        }

        DB::transaction(function () use ($tasks, $validated): void {
            foreach ($tasks as $task) {
                $task->update(['executive_id' => $validated['executive_id']]);
            }
        });

        return response()->json([
            'message' => $tasks->count().' task'.($tasks->count() === 1 ? '' : 's').' assigned successfully.',
            'tasks' => $tasks->fresh(),
        ]);
    }

    public function storeTask(Request $request, int $executiveId): JsonResponse
    {
        $manager = $this->manager($request);
        $executive = $this->assignedExecutive($manager, $executiveId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = Task::query()->create([
            'orgnization_id' => $manager->orgnization_id,
            ...$validated,
            'manager_id' => $manager->id,
            'executive_id' => $executive->id,
            'status' => $validated['status'] ?? 'pending',
        ]);

        return response()->json([
            'message' => 'Task assigned successfully.',
            'task' => $task,
        ], 201);
    }

    public function updateTask(Request $request, int $taskId): JsonResponse
    {
        $manager = $this->manager($request);
        $task = Task::query()
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->whereHas('executive', fn ($query) => $query
                ->where('manager_id', $manager->id)
                ->where('orgnization_id', $manager->orgnization_id)
                ->where('role_id', 4))
            ->findOrFail($taskId);

        $validated = $request->validate([
            'executive_id' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('manager_id', $manager->id)
                    ->where('orgnization_id', $manager->orgnization_id)
                    ->where('role_id', 4)
                    ->whereNull('deleted_at')),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $task->update($validated);

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task->fresh(),
        ]);
    }

    public function destroyTask(Request $request, int $taskId): JsonResponse
    {
        $manager = $this->manager($request);
        $task = Task::query()
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->whereHas('executive', fn ($query) => $query
                ->where('manager_id', $manager->id)
                ->where('orgnization_id', $manager->orgnization_id)
                ->where('role_id', 4))
            ->findOrFail($taskId);
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully.']);
    }

    public function executiveTasks(Request $request): JsonResponse
    {
        $executive = $request->user();
        abort_unless($executive && $executive->role_id === 4, 403);

        $tasks = Task::query()
            ->where('executive_id', $executive->id)
            ->with('manager:id,name')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    private function manager(Request $request): User
    {
        $manager = $request->user();
        abort_unless($manager && $manager->role_id === 3, 403);

        return $manager;
    }

    private function assignedExecutive(User $manager, int $executiveId): User
    {
        return User::query()
            ->whereKey($executiveId)
            ->where('manager_id', $manager->id)
            ->where('orgnization_id', $manager->orgnization_id)
            ->where('role_id', 4)
            ->firstOrFail();
    }
}
