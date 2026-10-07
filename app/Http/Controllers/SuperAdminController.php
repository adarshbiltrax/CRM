<?php

namespace App\Http\Controllers;

use App\Models\Orgnization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 422);
        }

        $user = Auth::user();

        if (! $user || $user->role_id !== 1) {
            Auth::logout();

            return response()->json([
                'message' => 'Only a Super Admin can access this dashboard.',
            ], 403);
        }

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->only('id', 'name', 'email', 'role_id', 'status'),
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'user' => $user?->only('id', 'name', 'email', 'role_id', 'status'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (filled($validated['password'] ?? null) && ! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $emailChanged = $user->email !== $validated['email'];
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if (filled($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->only('id', 'name', 'email', 'role_id', 'orgnization_id', 'status'),
        ]);
    }

    public function clients(): JsonResponse
    {
        $clients = User::query()
            ->where('role_id', 2)
            ->with('orgnization:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'status', 'role_id', 'orgnization_id', 'created_at']);

        return response()->json([
            'clients' => $clients,
        ]);
    }

    public function trashedClients(): JsonResponse
    {
        $clients = User::onlyTrashed()
            ->where('role_id', 2)
            ->with('orgnization:id,name')
            ->orderByDesc('deleted_at')
            ->get(['id', 'name', 'email', 'status', 'role_id', 'orgnization_id', 'created_at', 'deleted_at']);

        return response()->json(['clients' => $clients]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'orgnization_id' => ['required', 'integer', Rule::exists('orgnizations', 'id')->whereNull('deleted_at')],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $client = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => 2,
            'status' => $validated['status'],
            'orgnization_id' => $validated['orgnization_id'],
        ]);

        return response()->json([
            'message' => 'Client Admin added successfully.',
            'client' => $client->load('orgnization:id,name')
                ->makeHidden(['password', 'remember_token']),
        ], 201);
    }

    public function updateClient(Request $request, User $client): JsonResponse
    {
        abort_unless($client->role_id === 2, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($client->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'orgnization_id' => ['required', 'integer', Rule::exists('orgnizations', 'id')->whereNull('deleted_at')],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $client->update($validated);

        return response()->json([
            'message' => 'Client Admin updated successfully.',
            'client' => $client->fresh()->load('orgnization:id,name')
                ->makeHidden(['password', 'remember_token']),
        ]);
    }

    public function destroyClient(User $client): JsonResponse
    {
        abort_unless($client->role_id === 2, 404);

        $client->delete();

        return response()->json(['message' => 'Client Admin moved to trash successfully.']);
    }

    public function restoreClient(int $clientId): JsonResponse
    {
        $client = User::onlyTrashed()
            ->where('role_id', 2)
            ->findOrFail($clientId);

        if ($client->orgnization_id && ! Orgnization::query()->whereKey($client->orgnization_id)->exists()) {
            return response()->json([
                'message' => 'Restore the assigned organization before restoring this Client Admin.',
            ], 409);
        }

        $client->restore();

        return response()->json([
            'message' => 'Client Admin restored successfully.',
            'client' => $client->fresh()->load('orgnization:id,name'),
        ]);
    }

    public function staff(Request $request, int $roleId): JsonResponse
    {
        abort_unless(in_array($roleId, [3, 4], true), 404);

        $staff = User::query()
            ->where('role_id', $roleId)
            ->with([
                'orgnization:id,name',
                'orgnization.clientAdmins:id,name,email,orgnization_id',
                'manager:id,name',
            ])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'status', 'role_id', 'orgnization_id', 'manager_id', 'created_at']);

        return response()->json(['users' => $staff]);
    }

    public function trashedStaff(int $roleId): JsonResponse
    {
        abort_unless(in_array($roleId, [3, 4], true), 404);

        $staff = User::onlyTrashed()
            ->where('role_id', $roleId)
            ->with([
                'orgnization:id,name',
                'orgnization.clientAdmins:id,name,email,orgnization_id',
                'manager:id,name',
            ])
            ->orderByDesc('deleted_at')
            ->get(['id', 'name', 'email', 'status', 'role_id', 'orgnization_id', 'manager_id', 'created_at', 'deleted_at']);

        return response()->json(['users' => $staff]);
    }

    public function storeStaff(Request $request, int $roleId): JsonResponse
    {
        abort_unless(in_array($roleId, [3, 4], true), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'orgnization_id' => ['required', 'integer', Rule::exists('orgnizations', 'id')->whereNull('deleted_at')],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $user = User::create([
            ...$validated,
            'role_id' => $roleId,
        ]);

        return response()->json([
            'message' => $this->staffRoleName($roleId).' created successfully.',
            'user' => $user->load([
                'orgnization:id,name',
                'orgnization.clientAdmins:id,name,email,orgnization_id',
            ]),
        ], 201);
    }

    public function updateStaff(Request $request, int $roleId, User $user): JsonResponse
    {
        abort_unless(in_array($roleId, [3, 4], true) && $user->role_id === $roleId, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'orgnization_id' => ['required', 'integer', Rule::exists('orgnizations', 'id')->whereNull('deleted_at')],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if ($roleId === 3 && (int) $validated['status'] === 0 && $user->executives()->where('role_id', 4)->exists()) {
            return response()->json([
                'message' => 'Reassign or unassign this Manager’s Executives before deactivating the Manager.',
            ], 409);
        }

        $user->update($validated);
        if ($roleId === 4 && $user->manager_id && ! User::query()
            ->whereKey($user->manager_id)
            ->where('role_id', 3)
            ->where('orgnization_id', $user->orgnization_id)
            ->where('status', 1)
            ->exists()) {
            $user->update(['manager_id' => null]);
        }

        return response()->json([
            'message' => $this->staffRoleName($roleId).' updated successfully.',
            'user' => $user->fresh()->load([
                'orgnization:id,name',
                'orgnization.clientAdmins:id,name,email,orgnization_id',
            ]),
        ]);
    }

    public function destroyStaff(int $roleId, User $user): JsonResponse
    {
        abort_unless(in_array($roleId, [3, 4], true) && $user->role_id === $roleId, 404);

        if ($roleId === 3 && $user->executives()->where('role_id', 4)->exists()) {
            return response()->json([
                'message' => 'Reassign or unassign this Manager’s Executives before moving the Manager to trash.',
            ], 409);
        }

        $user->delete();

        return response()->json(['message' => $this->staffRoleName($roleId).' moved to trash.']);
    }

    public function restoreStaff(int $roleId, int $userId): JsonResponse
    {
        abort_unless(in_array($roleId, [3, 4], true), 404);

        $user = User::onlyTrashed()
            ->where('role_id', $roleId)
            ->findOrFail($userId);

        if ($user->orgnization_id && ! Orgnization::query()->whereKey($user->orgnization_id)->exists()) {
            return response()->json([
                'message' => 'Restore the assigned organization before restoring this '.$this->staffRoleName($roleId).'.',
            ], 409);
        }

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
            'message' => $this->staffRoleName($roleId).' restored successfully.',
            'user' => $user->fresh()->load([
                'orgnization:id,name',
                'orgnization.clientAdmins:id,name,email,orgnization_id',
            ]),
        ]);
    }

    public function promoteExecutive(User $user): JsonResponse
    {
        abort_unless($user->role_id === 4, 404);

        $user->update(['role_id' => 3, 'manager_id' => null]);

        return response()->json([
            'message' => 'Executive promoted to Manager successfully.',
            'user' => $user->fresh()->load([
                'orgnization:id,name',
                'orgnization.clientAdmins:id,name,email,orgnization_id',
            ]),
        ]);
    }

    private function staffRoleName(int $roleId): string
    {
        return $roleId === 3 ? 'Manager' : 'Executive';
    }
}
