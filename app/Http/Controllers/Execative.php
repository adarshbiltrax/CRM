<?php

namespace App\Http\Controllers;

use App\Http\Halper\Responce;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\HasApiTokens;

class Execative extends Controller
{
    //
    use HasApiTokens;

    public function index()
    {
        return response()->json([
            'message' => 'Execative Controller is working.',
        ]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'orgnization_id' => [
                'required',
                'integer',
                Rule::exists('orgnizations', 'id')
                    ->where('status', 1)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $executive = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => 4,
            'status' => 1,
            'orgnization_id' => $validated['orgnization_id'],
        ]);

        $token = $executive->createToken('auth_token')->plainTextToken;

        return Responce::success([
            'data' => $executive,
            'access_token' => $token,
        ], 'Executive account created successfully.', 201);
    }

    // Login function for execative
    public function Login(Request $request)
    {
        try {
            if ($request->isMethod('post')) {
                $validated = $request->validate([
                    'email' => ['required', 'string', 'email', 'max:255'],
                    'password' => ['required', 'string', 'min:8'],
                ]);
                // Process the validated data
                // $credentials = [
                //     'email' => $validated['email'],
                //     'password' => $validated['password'],
                // ];
                $user = User::where('email', $validated['email'])->where('status', 1)->first();
                if (! $user) {
                    return Responce::error('User not found or inactive.', 404);
                }
                if (! Hash::check($validated['password'], $user->password)) {
                    return Responce::error('Invalid credentials.', 401);
                }

                $token = $user->createToken('auth_token')->plainTextToken;

                return Responce::success(['data' => $user, 'access_token' => $token], 'Login successful.', 200);

            } else {
                return Responce::error('Invalid request method. Please use POST.', 405);
            }
        } catch (\Exception $e) {
            return Responce::error('Internal servver issue: '.$e->getMessage(), 422);
        }

    }
}
