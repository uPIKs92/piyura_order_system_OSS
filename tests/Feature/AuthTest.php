<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_login_with_session(): void
    {
        $owner = User::factory()->owner()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role', 'tenant_id'], 'tenant'])
            ->assertJsonMissing(['token']);
        $this->assertAuthenticatedAs($owner);
        $this->assertEquals('owner', $response->json('user.role'));
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable();
    }

    public function test_authenticated_user_can_access_user_endpoint(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJson(['user' => ['id' => $user->id, 'email' => $user->email]]);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->owner()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_login_rate_limiting_blocks_after_5_failed_attempts(): void
    {
        $owner = User::factory()->owner()->create(['password' => bcrypt('password')]);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $owner->email,
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/login', [
            'email' => $owner->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Too many login attempts',
            $response->json('errors.email.0') ?? $response->json('message') ?? ''
        );
    }

    public function test_login_rate_limiting_allows_after_lockout_period(): void
    {
        $owner = User::factory()->owner()->create(['password' => bcrypt('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $owner->email,
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        $this->travel(61)->seconds();

        $this->postJson('/api/login', [
            'email' => $owner->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_owner_can_send_password_reset_link(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['email' => 'staff@example.com', 'tenant_id' => $owner->tenant_id]);

        Notification::fake();

        $this->actingAs($owner)
            ->postJson('/api/password/email', ['email' => $staff->email])
            ->assertOk();
    }

    public function test_staff_cannot_send_password_reset_link(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->postJson('/api/password/email', ['email' => 'other@example.com'])
            ->assertForbidden();
    }
}
