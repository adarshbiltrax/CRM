<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_frontend_routes_render_the_vue_application(): void
    {
        $this->get('/super-admin/organizations')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_unknown_api_routes_are_not_served_by_the_frontend_fallback(): void
    {
        $this->getJson('/api/not-a-real-route')
            ->assertNotFound();
    }

    public function test_session_endpoint_returns_an_empty_user_for_guests(): void
    {
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson([
                'user' => null,
            ]);
    }

    public function test_unauthenticated_api_requests_receive_a_json_response(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}
