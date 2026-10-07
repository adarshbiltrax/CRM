<?php

namespace Tests\Feature;

use App\Models\Orgnization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientAdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Orgnization $organization;

    private User $clientAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->organization = $this->createOrganization('Client Admin Organization');
        $this->clientAdmin = User::query()->create([
            'name' => 'Client Admin',
            'email' => 'client-admin@example.com',
            'password' => 'client-password',
            'role_id' => 2,
            'orgnization_id' => $this->organization->id,
            'status' => 1,
        ]);

        Sanctum::actingAs($this->clientAdmin);
    }

    public function test_client_admin_can_crud_only_managers_and_executives_in_their_organization(): void
    {
        foreach ([3 => 'Manager', 4 => 'Executive'] as $roleId => $roleName) {
            $created = $this->postJson("/api/client-admin/staff/{$roleId}", [
                'name' => "{$roleName} One",
                'email' => strtolower($roleName).'@example.com',
                'password' => 'staff-password',
                'status' => 1,
                'orgnization_id' => 999,
            ])->assertCreated()
                ->assertJsonPath('user.role_id', $roleId)
                ->assertJsonPath('user.orgnization_id', $this->organization->id);

            $userId = $created->json('user.id');

            $this->getJson("/api/client-admin/staff/{$roleId}")
                ->assertOk()
                ->assertJsonPath('users.0.id', $userId);

            $this->putJson("/api/client-admin/staff/{$roleId}/{$userId}", [
                'name' => "{$roleName} Updated",
                'email' => strtolower($roleName).'@example.com',
                'status' => 0,
                'orgnization_id' => 999,
            ])->assertOk()
                ->assertJsonPath('user.name', "{$roleName} Updated")
                ->assertJsonPath('user.status', 0)
                ->assertJsonPath('user.orgnization_id', $this->organization->id);

            $this->deleteJson("/api/client-admin/staff/{$roleId}/{$userId}")
                ->assertOk();

            $this->getJson("/api/client-admin/staff/{$roleId}")
                ->assertOk()
                ->assertJsonCount(0, 'users');

            $this->getJson("/api/client-admin/staff/{$roleId}/trash")
                ->assertOk()
                ->assertJsonPath('users.0.id', $userId);

            $this->postJson("/api/client-admin/staff/{$roleId}/{$userId}/restore")
                ->assertOk()
                ->assertJsonPath('user.id', $userId);

            $this->assertDatabaseHas('users', [
                'id' => $userId,
                'orgnization_id' => $this->organization->id,
                'role_id' => $roleId,
            ]);
        }
    }

    public function test_client_admin_can_promote_only_an_executive_in_their_organization(): void
    {
        $executive = $this->createStaff('Executive', 4, $this->organization);
        $otherOrganization = $this->createOrganization('Other Organization');
        $otherExecutive = $this->createStaff('Other Executive', 4, $otherOrganization);

        $this->postJson("/api/client-admin/staff/executives/{$executive->id}/promote")
            ->assertOk()
            ->assertJsonPath('user.role_id', 3)
            ->assertJsonPath('user.orgnization_id', $this->organization->id)
            ->assertJsonPath('user.status', 1);

        $this->getJson('/api/client-admin/staff/4')
            ->assertOk()
            ->assertJsonCount(0, 'users')
            ->assertJsonMissing(['id' => $executive->id]);

        $this->getJson('/api/client-admin/staff/3')
            ->assertOk()
            ->assertJsonPath('users.0.id', $executive->id);

        $this->postJson("/api/client-admin/staff/executives/{$otherExecutive->id}/promote")
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherExecutive->id,
            'role_id' => 4,
            'orgnization_id' => $otherOrganization->id,
        ]);
    }

    public function test_client_admin_can_assign_and_reassign_an_executive_only_to_an_active_local_manager(): void
    {
        $manager = $this->createStaff('Team Manager', 3, $this->organization);
        $executive = $this->postJson('/api/client-admin/staff/4', [
            'name' => 'Assigned Executive',
            'email' => 'assigned-executive@example.com',
            'password' => 'staff-password',
            'status' => 1,
            'manager_id' => $manager->id,
        ])->assertCreated()
            ->assertJsonPath('user.manager_id', $manager->id)
            ->assertJsonPath('user.manager.id', $manager->id);

        $executiveId = $executive->json('user.id');
        $this->getJson('/api/client-admin/staff/4')
            ->assertOk()
            ->assertJsonPath('users.0.manager.id', $manager->id);

        $this->putJson("/api/client-admin/staff/4/{$executiveId}", [
            'name' => 'Assigned Executive',
            'email' => 'assigned-executive@example.com',
            'status' => 1,
            'manager_id' => null,
        ])->assertOk()
            ->assertJsonPath('user.manager_id', null)
            ->assertJsonPath('user.manager', null);

        $this->putJson("/api/client-admin/staff/4/{$executiveId}", [
            'name' => 'Assigned Executive',
            'email' => 'assigned-executive@example.com',
            'status' => 1,
            'manager_id' => $manager->id,
        ])->assertOk()
            ->assertJsonPath('user.manager_id', $manager->id);

        $otherOrganization = $this->createOrganization('Other Organization');
        $otherManager = $this->createStaff('Other Manager', 3, $otherOrganization);
        $this->putJson("/api/client-admin/staff/4/{$executiveId}", [
            'name' => 'Assigned Executive',
            'email' => 'assigned-executive@example.com',
            'status' => 1,
            'manager_id' => $otherManager->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['manager_id']);
    }

    public function test_client_admin_must_reassign_executives_before_disabling_or_trashing_their_manager(): void
    {
        $manager = $this->createStaff('Assigned Manager', 3, $this->organization);
        $executive = $this->createStaff('Assigned Executive', 4, $this->organization);
        $executive->update(['manager_id' => $manager->id]);

        $this->putJson("/api/client-admin/staff/3/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'status' => 0,
        ])->assertConflict();

        $this->deleteJson("/api/client-admin/staff/3/{$manager->id}")
            ->assertConflict();

        $executive->update(['manager_id' => null]);
        $this->deleteJson("/api/client-admin/staff/3/{$manager->id}")
            ->assertOk();
    }

    public function test_client_admin_cannot_view_or_change_staff_from_another_organization(): void
    {
        $otherOrganization = $this->createOrganization('Other Organization');
        $otherManager = $this->createStaff('Other Manager', 3, $otherOrganization);

        $this->getJson('/api/client-admin/staff/3')
            ->assertOk()
            ->assertJsonCount(0, 'users');

        $this->putJson("/api/client-admin/staff/3/{$otherManager->id}", [
            'name' => 'Hijacked Manager',
            'email' => $otherManager->email,
            'status' => 0,
        ])->assertNotFound();

        $this->deleteJson("/api/client-admin/staff/3/{$otherManager->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherManager->id,
            'name' => 'Other Manager',
            'orgnization_id' => $otherOrganization->id,
        ]);
    }

    public function test_only_client_admins_can_use_client_admin_staff_endpoints(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Executive',
            'email' => 'executive@example.com',
            'password' => 'secret-password',
            'role_id' => 4,
            'orgnization_id' => $this->organization->id,
            'status' => 1,
        ]));

        $this->getJson('/api/client-admin/staff/4')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/client-admin/staff/4')
            ->assertUnauthorized();
    }

    private function createOrganization(string $name): Orgnization
    {
        return Orgnization::query()->create([
            'name' => $name,
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 1,
        ]);
    }

    private function createStaff(string $name, int $roleId, Orgnization $organization): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)).'@example.com',
            'password' => 'staff-password',
            'role_id' => $roleId,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);
    }
}
