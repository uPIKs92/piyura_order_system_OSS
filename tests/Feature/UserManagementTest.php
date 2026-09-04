<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $this->assertCount(4, $response->json('data')); // owner + 3 staff
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

    private function createSessionRow(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_password_change_invalidates_target_user_sessions(): void
    {
        $staff = User::factory()->create();
        $this->createSessionRow($staff, 'sess-password-change');

        $this->withToken($this->token)
            ->putJson('/api/users/' . $staff->id, ['password' => 'newpassword123'])
            ->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $staff->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $staff->id)->count());
    }

    public function test_deactivating_user_invalidates_their_sessions(): void
    {
        $staff = User::factory()->create();
        $this->createSessionRow($staff, 'sess-deactivate');

        $this->withToken($this->token)
            ->putJson('/api/users/' . $staff->id, ['is_active' => false])
            ->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $staff->id)->count());
    }

    public function test_name_change_keeps_target_user_sessions(): void
    {
        $staff = User::factory()->create();
        $this->createSessionRow($staff, 'sess-name-change');

        $this->withToken($this->token)
            ->putJson('/api/users/' . $staff->id, ['name' => 'Renamed'])
            ->assertOk();

        $this->assertSame(1, DB::table('sessions')->where('user_id', $staff->id)->count());
    }

    public function test_owner_cannot_change_own_role_or_active_status(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/users/' . $this->owner->id, ['role' => 'staff'])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->putJson('/api/users/' . $this->owner->id, ['is_active' => false])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $this->owner->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    public function test_last_active_owner_cannot_be_demoted_or_deactivated(): void
    {
        $tenant = Tenant::factory()->create();
        $inactiveOwner = User::factory()->owner()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);
        $loneActiveOwner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        $inactiveOwnerToken = $inactiveOwner->createToken('test')->plainTextToken;

        $this->withToken($inactiveOwnerToken)
            ->putJson('/api/users/' . $loneActiveOwner->id, ['role' => 'staff'])
            ->assertStatus(422);

        $this->withToken($inactiveOwnerToken)
            ->putJson('/api/users/' . $loneActiveOwner->id, ['is_active' => false])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $loneActiveOwner->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    public function test_owner_can_be_demoted_when_another_active_owner_exists(): void
    {
        $secondOwner = User::factory()->owner()->create(['tenant_id' => $this->owner->tenant_id]);

        $this->withToken($this->token)
            ->putJson('/api/users/' . $secondOwner->id, ['role' => 'staff'])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $secondOwner->id,
            'role' => 'staff',
        ]);
    }

    public function test_owner_can_change_own_password_and_is_logged_out(): void
    {
        $this->createSessionRow($this->owner, 'sess-self-password');

        $this->withToken($this->token)
            ->putJson('/api/users/' . $this->owner->id, ['password' => 'newpassword123'])
            ->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $this->owner->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->owner->id)->count());
    }
}
