<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    public function test_owner_can_list_staff_users(): void
    {
        User::factory(3)->create();

        $response = $this->withToken($this->token)->getJson('/api/users');

        $response->assertOk();
        $this->assertCount(4, $response->json()); // owner + 3 staff
    }

    public function test_owner_can_create_staff_user(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/users', [
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJson(['name' => 'Staff User', 'email' => 'staff@example.com', 'role' => 'staff']);
    }

    public function test_owner_can_update_staff_user(): void
    {
        $staff = User::factory()->create();

        $response = $this->withToken($this->token)->putJson('/api/users/' . $staff->id, [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Name', $response->json('name'));
    }

    public function test_owner_can_delete_staff_user(): void
    {
        $staff = User::factory()->create();

        $this->withToken($this->token)->deleteJson('/api/users/' . $staff->id)->assertOk();

        $this->assertModelMissing($staff);
    }

    public function test_staff_cannot_access_user_crud(): void
    {
        $staff = User::factory()->create();
        $staffToken = $staff->createToken('test')->plainTextToken;

        $this->withToken($staffToken)->getJson('/api/users')->assertForbidden();
        $this->withToken($staffToken)->postJson('/api/users', [
            'name' => 'Test', 'email' => 'test@example.com', 'password' => 'password',
        ])->assertForbidden();
        $this->withToken($staffToken)->deleteJson('/api/users/' . $staff->id)->assertForbidden();
    }
}
