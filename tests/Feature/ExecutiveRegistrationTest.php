<?php

namespace Tests\Feature;

use App\Models\Orgnization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExecutiveRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_executive_can_register_to_an_active_organization_and_receive_a_login_token(): void
    {
        $organization = $this->createOrganization('Active Organization');

        $response = $this->postJson('/api/execative/register', [
            'name' => 'New Executive',
            'email' => 'new-executive@example.com',
            'orgnization_id' => $organization->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role_id' => 1,
        ])->assertCreated()
            ->assertJsonPath('message', 'Executive account created successfully.')
            ->assertJsonPath('data.data.role_id', 4)
            ->assertJsonPath('data.data.orgnization_id', $organization->id)
            ->assertJsonStructure([
                'data' => [
                    'data' => ['id', 'name', 'email', 'role_id', 'orgnization_id'],
                    'access_token',
                ],
            ]);

        $user = User::query()->findOrFail($response->json('data.data.id'));
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => 4,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
        ]);
    }

    public function test_registration_organization_list_contains_only_active_organizations(): void
    {
        $active = $this->createOrganization('Available Organization');
        $this->createOrganization('Inactive Organization', 0);
        $this->createOrganization('Deleted Organization')->delete();

        $this->getJson('/api/public/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'organizations')
            ->assertJsonPath('organizations.0.id', $active->id)
            ->assertJsonPath('organizations.0.name', 'Available Organization');
    }

    public function test_registration_rejects_missing_or_inactive_organizations_and_unconfirmed_passwords(): void
    {
        $inactive = $this->createOrganization('Inactive Organization', 0);
        $payload = [
            'name' => 'New Executive',
            'email' => 'new-executive@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];

        $this->postJson('/api/execative/register', [...$payload, 'orgnization_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['orgnization_id']);

        $this->postJson('/api/execative/register', [...$payload, 'orgnization_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['orgnization_id']);

        $this->postJson('/api/execative/register', [
            ...$payload,
            'orgnization_id' => $this->createOrganization('Active Organization')->id,
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $organization = $this->createOrganization('Active Organization');
        User::query()->create([
            'name' => 'Existing Executive',
            'email' => 'existing@example.com',
            'password' => 'secret-password',
            'role_id' => 4,
            'orgnization_id' => $organization->id,
            'status' => 1,
        ]);

        $this->postJson('/api/execative/register', [
            'name' => 'Duplicate Executive',
            'email' => 'existing@example.com',
            'orgnization_id' => $organization->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    private function createOrganization(string $name, int $status = 1): Orgnization
    {
        return Orgnization::query()->create([
            'name' => $name,
            'phone' => '5550100',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'status' => $status,
        ]);
    }
}
