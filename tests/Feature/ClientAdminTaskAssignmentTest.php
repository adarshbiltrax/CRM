<?php

namespace Tests\Feature;

use App\Models\Orgnization;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientAdminTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Orgnization $organization;

    private User $clientAdmin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->organization = $this->createOrganization('Task Assignment Organization');
        $this->clientAdmin = $this->createUser('Client Admin', 'client-admin@example.com', 2, $this->organization);
        $this->manager = $this->createUser('Manager', 'manager@example.com', 3, $this->organization);
        Sanctum::actingAs($this->clientAdmin);
    }

    public function test_client_admin_can_assign_multiple_free_tasks_to_one_local_manager(): void
    {
        $this->postJson("/api/client-admin/tasks/managers/{$this->manager->id}/assign", [
            'tasks' => [
                ['template_id' => 12, 'title' => 'Prepare weekly report', 'completed' => false],
                ['template_id' => 13, 'title' => 'Review pipeline', 'completed' => true],
            ],
        ])->assertCreated()
            ->assertJsonPath('message', '2 tasks assigned to Manager.')
            ->assertJsonCount(2, 'tasks')
            ->assertJsonPath('tasks.0.manager_id', $this->manager->id)
            ->assertJsonPath('tasks.0.executive_id', null)
            ->assertJsonPath('tasks.0.client_admin_id', $this->clientAdmin->id)
            ->assertJsonPath('tasks.0.orgnization_id', $this->organization->id)
            ->assertJsonPath('tasks.0.status', 'pending');

        $this->getJson('/api/client-admin/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'tasks')
            ->assertJsonPath('tasks.0.manager.id', $this->manager->id)
            ->assertJsonPath('tasks.0.client_admin.id', $this->clientAdmin->id);

        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_a_task_template_is_disabled_after_assignment_and_cannot_be_assigned_to_another_manager(): void
    {
        $otherManager = $this->createUser('Other Manager', 'other-manager@example.com', 3, $this->organization);
        $payload = [
            'tasks' => [
                ['template_id' => 21, 'title' => 'Create a project plan', 'completed' => false],
            ],
        ];

        $this->postJson("/api/client-admin/tasks/managers/{$this->manager->id}/assign", $payload)
            ->assertCreated();

        $this->postJson("/api/client-admin/tasks/managers/{$otherManager->id}/assign", $payload)
            ->assertConflict()
            ->assertJsonPath('assigned_template_ids.0', 21);

        $this->getJson('/api/client-admin/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'tasks');

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_client_admin_cannot_assign_tasks_to_other_organizations_or_duplicate_template_ids(): void
    {
        $foreignOrganization = $this->createOrganization('Other Organization');
        $foreignManager = $this->createUser('Foreign Manager', 'foreign-manager@example.com', 3, $foreignOrganization);

        $this->postJson("/api/client-admin/tasks/managers/{$foreignManager->id}/assign", [
            'tasks' => [
                ['template_id' => 31, 'title' => 'Foreign task', 'completed' => false],
            ],
        ])->assertNotFound();

        $this->postJson("/api/client-admin/tasks/managers/{$this->manager->id}/assign", [
            'tasks' => [
                ['template_id' => 32, 'title' => 'First copy', 'completed' => false],
                ['template_id' => 32, 'title' => 'Duplicate copy', 'completed' => false],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tasks.1.template_id']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_another_organization_cannot_see_task_assignments(): void
    {
        Task::query()->create([
            'orgnization_id' => $this->organization->id,
            'client_admin_id' => $this->clientAdmin->id,
            'template_id' => 41,
            'manager_id' => $this->manager->id,
            'title' => 'Private organization task',
        ]);

        $otherOrganization = $this->createOrganization('Other Organization');
        $otherAdmin = $this->createUser('Other Admin', 'other-admin@example.com', 2, $otherOrganization);
        Sanctum::actingAs($otherAdmin);

        $this->getJson('/api/client-admin/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');
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

    private function createUser(string $name, string $email, int $roleId, Orgnization $organization): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret-password',
            'role_id' => $roleId,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);
    }
}
