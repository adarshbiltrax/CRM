<?php

namespace Tests\Feature;

use App\Models\Orgnization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_all_account_roles_can_edit_only_their_own_profile(): void
    {
        $organization = Orgnization::query()->create([
            'name' => 'Profile Organization',
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 1,
        ]);

        foreach ([1, 2, 3, 4] as $roleId) {
            $user = User::query()->create([
                'name' => "User {$roleId}",
                'email' => "user{$roleId}@example.com",
                'password' => 'current-password',
                'role_id' => $roleId,
                'orgnization_id' => $roleId === 1 ? null : $organization->id,
                'status' => 1,
            ]);

            Sanctum::actingAs($user);

            $this->putJson('/api/auth/profile', [
                'name' => "Updated User {$roleId}",
                'email' => "updated{$roleId}@example.com",
            ])->assertOk()
                ->assertJsonPath('user.id', $user->id)
                ->assertJsonPath('user.name', "Updated User {$roleId}")
                ->assertJsonPath('user.email', "updated{$roleId}@example.com")
                ->assertJsonPath('user.role_id', $roleId)
                ->assertJsonPath('user.orgnization_id', $user->orgnization_id)
                ->assertJsonPath('user.status', 1)
                ->assertJsonMissingPath('user.password');

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => "Updated User {$roleId}",
                'email' => "updated{$roleId}@example.com",
                'role_id' => $roleId,
                'orgnization_id' => $user->orgnization_id,
            ]);
        }
    }

    public function test_profile_password_change_requires_current_password_and_confirmation(): void
    {
        $user = User::query()->create([
            'name' => 'Profile User',
            'email' => 'profile-user@example.com',
            'password' => 'current-password',
            'role_id' => 4,
            'status' => 1,
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->putJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->putJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->putJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_profile_update_rejects_duplicate_email_and_unauthenticated_requests(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'secret-password',
            'role_id' => 2,
            'status' => 1,
        ]);
        $user = User::query()->create([
            'name' => 'Profile User',
            'email' => 'profile@example.com',
            'password' => 'secret-password',
            'role_id' => 3,
            'status' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->putJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => 'existing@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/auth/profile', [
            'name' => 'Unauthorized',
            'email' => 'unauthorized@example.com',
        ])->assertUnauthorized();
    }
}
