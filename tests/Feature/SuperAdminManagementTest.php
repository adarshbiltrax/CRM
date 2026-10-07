<?php

namespace Tests\Feature;

use App\Models\Orgnization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAs(User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'password' => 'secret-password',
            'role_id' => 1,
            'status' => 1,
        ]));
    }

    public function test_super_admin_can_create_list_update_and_soft_delete_organizations(): void
    {
        $response = $this->postJson('/api/admin/organizations', [
            'name' => 'Northwind',
            'email' => 'hello@northwind.example',
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 1,
        ])->assertCreated()
            ->assertJsonPath('organization.name', 'Northwind')
            ->assertJsonPath('organization.client_admins_count', 0);

        $organizationId = $response->json('organization.id');

        $this->getJson('/api/admin/organizations')
            ->assertOk()
            ->assertJsonPath('organizations.0.id', $organizationId);

        $this->putJson("/api/admin/organizations/{$organizationId}", [
            'name' => 'Northwind Updated',
            'email' => 'hello@northwind.example',
            'phone' => '5550101',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 0,
        ])->assertOk()
            ->assertJsonPath('organization.name', 'Northwind Updated')
            ->assertJsonPath('organization.status', 0);

        $this->deleteJson("/api/admin/organizations/{$organizationId}")
            ->assertOk();

        $this->assertSoftDeleted('orgnizations', ['id' => $organizationId]);
        $this->getJson('/api/admin/organizations')
            ->assertOk()
            ->assertJsonCount(0, 'organizations');

        $this->getJson('/api/admin/organizations/trash')
            ->assertOk()
            ->assertJsonPath('organizations.0.id', $organizationId);

        $this->postJson("/api/admin/organizations/{$organizationId}/restore")
            ->assertOk()
            ->assertJsonPath('organization.id', $organizationId);

        $this->assertNotSoftDeleted('orgnizations', ['id' => $organizationId]);
    }

    public function test_super_admin_can_create_update_list_and_soft_delete_organization_assigned_client_admins(): void
    {
        $organization = $this->createOrganization('Northwind');

        $response = $this->postJson('/api/admin/clients', [
            'name' => 'Client Admin',
            'email' => 'client@example.com',
            'password' => 'client-password',
            'orgnization_id' => $organization->id,
            'status' => 1,
        ])->assertCreated()
            ->assertJsonPath('client.role_id', 2)
            ->assertJsonPath('client.orgnization_id', $organization->id)
            ->assertJsonPath('client.orgnization.name', 'Northwind')
            ->assertJsonMissingPath('client.password');

        $clientId = $response->json('client.id');
        $client = User::query()->findOrFail($clientId);
        $this->assertTrue(Hash::check('client-password', $client->password));

        $this->getJson('/api/admin/clients')
            ->assertOk()
            ->assertJsonPath('clients.0.orgnization_id', $organization->id)
            ->assertJsonPath('clients.0.orgnization.name', 'Northwind');

        $this->putJson("/api/admin/clients/{$clientId}", [
            'name' => 'Updated Client Admin',
            'email' => 'client@example.com',
            'password' => '',
            'orgnization_id' => $organization->id,
            'status' => 0,
        ])->assertOk()
            ->assertJsonPath('client.name', 'Updated Client Admin')
            ->assertJsonPath('client.status', 0)
            ->assertJsonMissingPath('client.password');

        $this->assertTrue(Hash::check('client-password', $client->fresh()->password));

        $this->deleteJson("/api/admin/clients/{$clientId}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $clientId]);
        $this->getJson('/api/admin/clients')
            ->assertOk()
            ->assertJsonCount(0, 'clients');

        $this->getJson('/api/admin/clients/trash')
            ->assertOk()
            ->assertJsonPath('clients.0.id', $clientId)
            ->assertJsonPath('clients.0.orgnization_id', $organization->id);

        $this->postJson("/api/admin/clients/{$clientId}/restore")
            ->assertOk()
            ->assertJsonPath('client.id', $clientId);

        $this->assertNotSoftDeleted('users', ['id' => $clientId]);
    }

    public function test_client_admin_must_have_a_real_organization_and_assigned_organizations_cannot_be_deleted(): void
    {
        $this->postJson('/api/admin/clients', [
            'name' => 'Unassigned Client',
            'email' => 'unassigned@example.com',
            'password' => 'client-password',
            'orgnization_id' => 999,
            'status' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['orgnization_id']);

        $organization = $this->createOrganization('Assigned Org');
        User::query()->create([
            'name' => 'Assigned Client',
            'email' => 'assigned@example.com',
            'password' => 'client-password',
            'role_id' => 2,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);

        $this->deleteJson("/api/admin/organizations/{$organization->id}")
            ->assertConflict();

        $this->assertNotSoftDeleted('orgnizations', ['id' => $organization->id]);
    }

    public function test_organization_and_client_admin_trash_can_be_restored_in_dependency_order(): void
    {
        $organization = $this->createOrganization('Recoverable Organization');
        $clientAdmin = User::query()->create([
            'name' => 'Recoverable Client Admin',
            'email' => 'recoverable-client@example.com',
            'password' => 'client-password',
            'role_id' => 2,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);

        $this->deleteJson("/api/admin/organizations/{$organization->id}")
            ->assertConflict();

        $this->deleteJson("/api/admin/clients/{$clientAdmin->id}")
            ->assertOk();

        $this->deleteJson("/api/admin/organizations/{$organization->id}")
            ->assertOk();

        $this->postJson("/api/admin/clients/{$clientAdmin->id}/restore")
            ->assertConflict()
            ->assertJsonPath('message', 'Restore the assigned organization before restoring this Client Admin.');

        $this->postJson("/api/admin/organizations/{$organization->id}/restore")
            ->assertOk();

        $this->postJson("/api/admin/clients/{$clientAdmin->id}/restore")
            ->assertOk()
            ->assertJsonPath('client.orgnization_id', $organization->id);

        $this->assertNotSoftDeleted('orgnizations', ['id' => $organization->id]);
        $this->assertNotSoftDeleted('users', ['id' => $clientAdmin->id]);
    }

    public function test_super_admin_can_manage_managers_and_executives_and_restore_trashed_accounts(): void
    {
        $organization = $this->createOrganization('Staff Organization');
        $clientAdmin = User::query()->create([
            'name' => 'Staff Organization Client Admin',
            'email' => 'staff-org-client-admin@example.com',
            'password' => 'client-password',
            'role_id' => 2,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);

        foreach ([3 => 'Manager', 4 => 'Executive'] as $roleId => $roleName) {
            $email = strtolower($roleName).'@example.com';

            $created = $this->postJson("/api/admin/staff/{$roleId}", [
                'name' => $roleName.' One',
                'email' => $email,
                'password' => 'staff-password',
                'orgnization_id' => $organization->id,
                'status' => 1,
            ])->assertCreated()
                ->assertJsonPath('user.role_id', $roleId)
                ->assertJsonPath('user.orgnization_id', $organization->id)
                ->assertJsonPath('user.orgnization.name', 'Staff Organization')
                ->assertJsonPath('user.orgnization.client_admins.0.id', $clientAdmin->id);

            $userId = $created->json('user.id');

            $this->getJson("/api/admin/staff/{$roleId}")
                ->assertOk()
                ->assertJsonPath('users.0.id', $userId);

            $this->putJson("/api/admin/staff/{$roleId}/{$userId}", [
                'name' => $roleName.' Updated',
                'email' => $email,
                'password' => '',
                'orgnization_id' => $organization->id,
                'status' => 0,
            ])->assertOk()
                ->assertJsonPath('user.name', $roleName.' Updated')
                ->assertJsonPath('user.status', 0);

            $this->deleteJson("/api/admin/staff/{$roleId}/{$userId}")
                ->assertOk();

            $this->getJson("/api/admin/staff/{$roleId}")
                ->assertOk()
                ->assertJsonCount(0, 'users');

            $this->getJson("/api/admin/staff/{$roleId}/trash")
                ->assertOk()
                ->assertJsonPath('users.0.id', $userId)
                ->assertJsonPath('users.0.name', $roleName.' Updated')
                ->assertJsonPath('users.0.orgnization.client_admins.0.id', $clientAdmin->id);

            $this->postJson("/api/admin/staff/{$roleId}/{$userId}/restore")
                ->assertOk()
                ->assertJsonPath('user.id', $userId);

            $this->assertNotSoftDeleted('users', ['id' => $userId]);
        }
    }

    public function test_staff_management_rejects_invalid_roles_and_role_mismatched_user_ids(): void
    {
        $organization = $this->createOrganization('Role Guard Organization');
        $executive = User::query()->create([
            'name' => 'Executive',
            'email' => 'role-guard-executive@example.com',
            'password' => 'staff-password',
            'role_id' => 4,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);

        $this->getJson('/api/admin/staff/2')->assertNotFound();
        $this->deleteJson("/api/admin/staff/3/{$executive->id}")->assertNotFound();
        $this->getJson('/api/admin/staff/4/trash')->assertOk();
    }

    public function test_super_admin_can_promote_an_executive_to_manager_without_changing_organization_or_account_details(): void
    {
        $organization = $this->createOrganization('Promotion Organization');
        $manager = User::query()->create([
            'name' => 'Current Manager',
            'email' => 'current-manager@example.com',
            'password' => 'manager-password',
            'role_id' => 3,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);
        $executive = User::query()->create([
            'name' => 'Promoted Executive',
            'email' => 'promoted-executive@example.com',
            'password' => 'executive-password',
            'role_id' => 4,
            'orgnization_id' => $organization->id,
            'manager_id' => $manager->id,
            'status' => 0,
        ]);

        $this->postJson("/api/admin/executives/{$executive->id}/promote")
            ->assertOk()
            ->assertJsonPath('message', 'Executive promoted to Manager successfully.')
            ->assertJsonPath('user.id', $executive->id)
            ->assertJsonPath('user.role_id', 3)
            ->assertJsonPath('user.orgnization_id', $organization->id)
            ->assertJsonPath('user.manager_id', null)
            ->assertJsonPath('user.status', 0);

        $this->assertDatabaseHas('users', [
            'id' => $executive->id,
            'role_id' => 3,
            'orgnization_id' => $organization->id,
            'manager_id' => null,
            'status' => 0,
        ]);

        $this->getJson('/api/admin/staff/4')
            ->assertOk()
            ->assertJsonCount(0, 'users');

        $this->getJson('/api/admin/staff/3')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $executive->id,
                'role_id' => 3,
            ]);

        $this->postJson("/api/admin/executives/{$executive->id}/promote")
            ->assertNotFound();
    }

    public function test_management_endpoints_reject_non_super_admin_accounts(): void
    {
        $clientAdmin = User::query()->create([
            'name' => 'Client Admin',
            'email' => 'client-admin@example.com',
            'password' => 'client-password',
            'role_id' => 2,
            'orgnization_id' => null,
            'status' => 1,
        ]);

        $this->actingAs($clientAdmin)
            ->getJson('/api/admin/organizations')
            ->assertForbidden();

        $this->actingAs($clientAdmin)
            ->getJson('/api/admin/clients')
            ->assertForbidden();
    }

    public function test_legacy_role_id_organization_registration_route_is_not_public(): void
    {
        $this->postJson('/api/orgnization/register/1', [
            'name' => 'Unprotected Organization',
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 1,
        ])->assertNotFound();

        $this->assertDatabaseMissing('orgnizations', ['name' => 'Unprotected Organization']);
    }

    private function createOrganization(string $name): Orgnization
    {
        return Orgnization::query()->create([
            'name' => $name,
            'email' => null,
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => 1,
        ]);
    }
}
