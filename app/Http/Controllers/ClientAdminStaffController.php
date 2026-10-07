<?php

namespace App\Http\Controllers;

use App\Models\Orgnization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientAdminStaffController extends Controller
{
    public function index(Request $request, int $roleId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $this->validateRole($roleId);

        $users = User::query()
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', $roleId)
            ->with('manager:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'status', 'role_id', 'orgnization_id', 'manager_id', 'created_at']);

        return response()->json(['users' => $users]);
    }

    public function trash(Request $request, int $roleId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $this->validateRole($roleId);

        $users = User::onlyTrashed()
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', $roleId)
            ->with('manager:id,name')
            ->orderByDesc('deleted_at')
            ->get(['id', 'name', 'email', 'status', 'role_id', 'orgnization_id', 'manager_id', 'created_at', 'deleted_at']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request, int $roleId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $this->validateRole($roleId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', 'integer', 'in:0,1'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role_id', 3)
                    ->where('orgnization_id', $admin->orgnization_id)
                    ->where('status', 1)
                    ->whereNull('deleted_at')),
            ],
        ]);

        $user = User::query()->create([
            ...$validated,
            'role_id' => $roleId,
            'orgnization_id' => $admin->orgnization_id,
            'manager_id' => $roleId === 4 ? ($validated['manager_id'] ?? null) : null,
        ]);

        return response()->json([
            'message' => $this->roleName($roleId).' created successfully.',
            'user' => $user->load('manager:id,name')->only('id', 'name', 'email', 'role_id', 'orgnization_id', 'manager_id', 'manager', 'status', 'created_at'),
        ], 201);
    }

    public function update(Request $request, int $roleId, int $userId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $this->validateRole($roleId);

        $user = User::query()
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', $roleId)
            ->findOrFail($userId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', 'integer', 'in:0,1'],
            'manager_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role_id', 3)
                    ->where('orgnization_id', $admin->orgnization_id)
                    ->where('status', 1)
                    ->whereNull('deleted_at')),
            ],
        ]);

        if ($roleId === 3 && (int) $validated['status'] === 0 && $user->executives()->where('role_id', 4)->exists()) {
            return response()->json([
                'message' => 'Reassign or unassign this Manager’s Executives before deactivating the Manager.',
            ], 409);
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if ($roleId === 3) {
            unset($validated['manager_id']);
        }

        $user->update($validated);

        return response()->json([
            'message' => $this->roleName($roleId).' updated successfully.',
            'user' => $user->fresh()->load('manager:id,name')->only('id', 'name', 'email', 'role_id', 'orgnization_id', 'manager_id', 'manager', 'status', 'created_at'),
        ]);
    }

    public function destroy(Request $request, int $roleId, int $userId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $this->validateRole($roleId);

        $user = User::query()
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', $roleId)
            ->findOrFail($userId);

        if ($roleId === 3 && $user->executives()->where('role_id', 4)->exists()) {
            return response()->json([
                'message' => 'Reassign or unassign this Manager’s Executives before moving the Manager to trash.',
            ], 409);
        }

        $user->delete();

        return response()->json(['message' => $this->roleName($roleId).' moved to trash.']);
    }

    public function restore(Request $request, int $roleId, int $userId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $this->validateRole($roleId);

        $user = User::onlyTrashed()
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', $roleId)
            ->findOrFail($userId);

        if ($roleId === 4 && $user->manager_id && ! User::query()
            ->whereKey($user->manager_id)
            ->where('role_id', 3)
            ->where('orgnization_id', $user->orgnization_id)
            ->where('status', 1)
            ->exists()) {
            $user->manager_id = null;
            $user->save();
        }

        $user->restore();

        return response()->json([
            'message' => $this->roleName($roleId).' restored successfully.',
            'user' => $user->fresh()->only('id', 'name', 'email', 'role_id', 'orgnization_id', 'status', 'created_at'),
        ]);
    }

    public function promote(Request $request, int $userId): JsonResponse
    {
        $admin = $this->clientAdmin($request);
        $user = User::query()
            ->where('orgnization_id', $admin->orgnization_id)
            ->where('role_id', 4)
            ->findOrFail($userId);

        $user->update(['role_id' => 3, 'manager_id' => null]);

        return response()->json([
            'message' => 'Executive promoted to Manager successfully.',
            'user' => $user->fresh()->only('id', 'name', 'email', 'role_id', 'orgnization_id', 'status'),
        ]);
    }

    private function clientAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->role_id === 2, 403);
        abort_unless(
            $user->orgnization_id && Orgnization::query()->whereKey($user->orgnization_id)->exists(),
            403,
            'An active organization assignment is required to manage staff.'
        );

        return $user;
    }

    private function validateRole(int $roleId): void
    {
        abort_unless(in_array($roleId, [3, 4], true), 404);
    }

    private function roleName(int $roleId): string
    {
        return $roleId === 3 ? 'Manager' : 'Executive';
    }
}
