<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Orgnization;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_admin_login_returns_the_nested_user_and_access_token_payload(): void
    {
        $this->seed(RoleSeeder::class);

        $organization = Orgnization::query()->create([
            'name' => 'Client Admin Organization',
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 1,
        ]);

        User::query()->create([
            'name' => 'Client Admin',
            'email' => 'client-admin@example.com',
            'password' => 'secret-password',
            'role_id' => 2,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);

        $this->postJson('/api/execative/login', [
            'email' => 'client-admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.data.role_id', 2)
            ->assertJsonPath('data.data.orgnization_id', $organization->id)
            ->assertJsonStructure([
                'data' => [
                    'data' => ['id', 'name', 'email', 'role_id', 'orgnization_id'],
                    'access_token',
                ],
            ]);
    }
}
