<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_index_paginates_within_tenant_scope(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        User::factory(25)->create(['tenant_id' => $owner->tenant_id]);

        $otherTenant = Tenant::factory()->create();
        User::factory(3)->create(['tenant_id' => $otherTenant->id]);

        $this->withToken($token)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 26)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('per_page', 20);

        $this->withToken($token)
            ->getJson('/api/users?page=2')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('current_page', 2);
    }

    public function test_user_index_caps_per_page_at_100(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/users?per_page=101')
            ->assertStatus(422);
    }
}
