<?php

namespace App\Http\Controllers;

use App\Models\Orgnization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientAdminTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $tasks = Task::query()
            ->where('orgnization_id', $admin->orgnization_id)
            ->with(['manager:id,name,email', 'clientAdmin:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    public function assign(Request $request, int $managerId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $manager = User::query()
            ->whereKey($managerId)
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', 3)
            ->where('status', 1)
            ->firstOrFail();

        $validated = $request->validate([
            'tasks' => ['required', 'array', 'min:1', 'max:30'],
            'tasks.*.template_id' => ['required', 'integer', 'distinct', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:150'],
        ]);

        $templateIds = collect($validated['tasks'])->pluck('template_id');
        $alreadyAssigned = Task::query()
            ->withTrashed()
            ->where('orgnization_id', $admin->orgnization_id)
            ->whereIn('template_id', $templateIds)
            ->pluck('template_id');

        if ($alreadyAssigned->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more selected task templates have already been assigned in this organization.',
                'assigned_template_ids' => $alreadyAssigned,
            ], 409);
        }

        $tasks = DB::transaction(function () use ($validated, $admin, $manager): array {
            return collect($validated['tasks'])
                ->map(fn (array $item): Task => Task::query()->create([
                    'orgnization_id' => $admin->orgnization_id,
                    'client_admin_id' => $admin->id,
                    'template_id' => $item['template_id'],
                    'manager_id' => $manager->id,
                    'executive_id' => null,
                    'title' => $item['title'],
                    'description' => 'Assigned from free task template #'.$item['template_id'].'.',
                    'status' => 'pending',
                ]))
                ->all();
        });

        return response()->json([
            'message' => count($tasks).' task'.(count($tasks) === 1 ? '' : 's').' assigned to '.$manager->name.'.',
            'tasks' => $tasks,
        ], 201);
    }

    private function clientAdmin(Request $request): User
    {
        $admin = $request->user();
        abort_unless($admin && $admin->role_id === 2, 403);
        abort_unless(
            $admin->orgnization_id && Orgnization::query()->whereKey($admin->orgnization_id)->exists(),
            403,
            'An active organization assignment is required to manage tasks.'
        );

        return $admin;
    }
}
