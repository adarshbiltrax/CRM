<?php

namespace App\Http\Controllers;

use App\Models\Orgnization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgnizationController extends Controller
{
    public function publicIndex(): JsonResponse
    {
        $orgnizations = Orgnization::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['organizations' => $orgnizations]);
    }

    public function index(): JsonResponse
    {
        $orgnizations = Orgnization::query()
            ->withCount(['users as client_admins_count' => fn ($query) => $query->where('role_id', 2)])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['organizations' => $orgnizations]);
    }

    public function trash(): JsonResponse
    {
        $orgnizations = Orgnization::onlyTrashed()
            ->withCount(['users as client_admins_count' => fn ($query) => $query->withTrashed()->where('role_id', 2)])
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json(['organizations' => $orgnizations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:orgnizations,email'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $organization = Orgnization::create($validated);

        return response()->json([
            'message' => 'Organization created successfully.',
            'organization' => $organization->loadCount([
                'users as client_admins_count' => fn ($query) => $query->where('role_id', 2),
            ]),
        ], 201);
    }

    public function update(Request $request, Orgnization $orgnization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('orgnizations', 'email')->ignore($orgnization->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $orgnization->update($validated);

        return response()->json([
            'message' => 'Organization updated successfully.',
            'organization' => $orgnization->fresh()->loadCount([
                'users as client_admins_count' => fn ($query) => $query->where('role_id', 2),
            ]),
        ]);
    }

    public function destroy(Orgnization $orgnization): JsonResponse
    {
        if ($orgnization->users()->exists()) {
            return response()->json([
                'message' => 'Move all assigned Client Admins, Managers, and Executives to trash before deleting this organization.',
            ], 409);
        }

        $orgnization->delete();

        return response()->json(['message' => 'Organization moved to trash successfully.']);
    }

    public function restore(int $organizationId): JsonResponse
    {
        $organization = Orgnization::onlyTrashed()->findOrFail($organizationId);
        $organization->restore();

        return response()->json([
            'message' => 'Organization restored successfully.',
            'organization' => $organization->fresh()->loadCount([
                'users as client_admins_count' => fn ($query) => $query->where('role_id', 2),
            ]),
        ]);
    }
}
