<?php

namespace Tests\Feature;

use App\Models\Orgnization;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagerTaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private Orgnization $organization;

    private User $manager;

    private User $executive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->organization = $this->createOrganization('Task Organization');
        $this->manager = $this->createUser('Manager', 3, $this->organization);
        $this->executive = $this->createUser('Assigned Executive', 4, $this->organization, $this->manager);
        Sanctum::actingAs($this->manager);
    }

    public function test_manager_can_only_view_executives_assigned_to_them(): void
    {
        $otherManager = $this->createUser('Other Manager', 3, $this->organization);
        $otherExecutive = $this->createUser('Other Executive', 4, $this->organization, $otherManager);
        $foreignOrganization = $this->createOrganization('Foreign Organization');
        $foreignManager = $this->createUser('Foreign Manager', 3, $foreignOrganization);
        $this->createUser('Foreign Executive', 4, $foreignOrganization, $foreignManager);

        $this->getJson('/api/manager/executives')
            ->assertOk()
            ->assertJsonCount(1, 'executives')
            ->assertJsonPath('executives.0.id', $this->executive->id);

        $this->getJson("/api/manager/executives/{$otherExecutive->id}/tasks")
            ->assertNotFound();
    }

    public function test_manager_can_create_read_update_and_soft_delete_tasks_for_assigned_executive(): void
    {
        $taskResponse = $this->postJson("/api/manager/executives/{$this->executive->id}/tasks", [
            'title' => 'Prepare weekly report',
            'description' => 'Summarize this week’s pipeline.',
            'due_date' => '2026-10-12',
        ])->assertCreated()
            ->assertJsonPath('task.title', 'Prepare weekly report')
            ->assertJsonPath('task.status', 'pending')
            ->assertJsonPath('task.manager_id', $this->manager->id)
            ->assertJsonPath('task.executive_id', $this->executive->id);

        $taskId = $taskResponse->json('task.id');

        $this->getJson("/api/manager/executives/{$this->executive->id}/tasks")
            ->assertOk()
            ->assertJsonPath('tasks.0.id', $taskId)
            ->assertJsonPath('tasks.0.due_date', '2026-10-12T00:00:00.000000Z');

        $this->putJson("/api/manager/tasks/{$taskId}", [
            'title' => 'Prepare monthly report',
            'description' => 'Updated task description.',
            'status' => 'in_progress',
            'due_date' => '2026-10-13',
        ])->assertOk()
            ->assertJsonPath('task.title', 'Prepare monthly report')
            ->assertJsonPath('task.status', 'in_progress');

        $this->actingAs($this->executive)
            ->getJson('/api/executive/tasks')
            ->assertOk()
            ->assertJsonPath('tasks.0.id', $taskId)
            ->assertJsonPath('tasks.0.manager.id', $this->manager->id);

        $this->actingAs($this->manager)
            ->deleteJson("/api/manager/tasks/{$taskId}")
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $taskId]);
        $this->getJson("/api/manager/executives/{$this->executive->id}/tasks")
            ->assertOk()
            ->assertJsonCount(0, 'tasks');
    }

    public function test_manager_cannot_update_or_delete_another_managers_task(): void
    {
        $otherManager = $this->createUser('Other Manager', 3, $this->organization);
        $otherExecutive = $this->createUser('Other Executive', 4, $this->organization, $otherManager);
        $task = Task::query()->create([
            'orgnization_id' => $this->organization->id,
            'manager_id' => $otherManager->id,
            'executive_id' => $otherExecutive->id,
            'title' => 'Private task',
        ]);

        $this->putJson("/api/manager/tasks/{$task->id}", ['title' => 'Changed'])
            ->assertNotFound();
        $this->deleteJson("/api/manager/tasks/{$task->id}")
            ->assertNotFound();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Private task']);
    }

    public function test_manager_can_multi_assign_client_admin_tasks_only_to_their_own_executive(): void
    {
        $tasks = collect([71, 72])->map(fn (int $templateId): Task => Task::query()->create([
            'orgnization_id' => $this->organization->id,
            'client_admin_id' => User::query()->create([
                'name' => "Client Admin {$templateId}",
                'email' => "admin{$templateId}@example.com",
                'password' => 'secret-password',
                'role_id' => 2,
                'orgnization_id' => $this->organization->id,
                'status' => 1,
            ])->id,
            'template_id' => $templateId,
            'manager_id' => $this->manager->id,
            'title' => "Task {$templateId}",
        ]));

        $this->getJson('/api/manager/tasks/available')
            ->assertOk()
            ->assertJsonCount(2, 'tasks');

        $this->postJson('/api/manager/tasks/assign', [
            'executive_id' => $this->executive->id,
            'task_ids' => $tasks->pluck('id')->all(),
        ])->assertOk()
            ->assertJsonPath('message', '2 tasks assigned successfully.')
            ->assertJsonCount(2, 'tasks');

        $this->getJson('/api/manager/tasks/available')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');

        $this->actingAs($this->executive)
            ->getJson('/api/executive/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'tasks');
    }

    public function test_manager_cannot_assign_tasks_belonging_to_another_manager(): void
    {
        $otherManager = $this->createUser('Other Manager', 3, $this->organization);
        $otherExecutive = $this->createUser('Other Executive', 4, $this->organization, $otherManager);
        $task = Task::query()->create([
            'orgnization_id' => $this->organization->id,
            'manager_id' => $otherManager->id,
            'executive_id' => null,
            'title' => 'Another manager task',
        ]);

        $this->postJson('/api/manager/tasks/assign', [
            'executive_id' => $this->executive->id,
            'task_ids' => [$task->id],
        ])->assertConflict();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'executive_id' => null,
            'manager_id' => $otherManager->id,
        ]);

        $this->postJson('/api/manager/tasks/assign', [
            'executive_id' => $otherExecutive->id,
            'task_ids' => [],
        ])->assertUnprocessable();
    }

    public function test_only_managers_and_executives_can_access_their_task_endpoints(): void
    {
        $this->actingAs($this->executive);
        $this->getJson('/api/manager/executives')
            ->assertForbidden();

        $this->getJson('/api/executive/tasks')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/manager/executives')
            ->assertUnauthorized();
        $this->getJson('/api/executive/tasks')
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

    private function createUser(string $name, int $roleId, Orgnization $organization, ?User $manager = null): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)).'-'.$roleId.'@example.com',
            'password' => 'secret-password',
            'role_id' => $roleId,
            'orgnization_id' => $organization->id,
            'manager_id' => $manager?->id,
            'status' => 1,
        ]);
    }
}
