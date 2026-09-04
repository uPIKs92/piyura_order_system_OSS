<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_restore_returns_data_once_then_marks_draft_consumed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/drafts', ['draft_data' => ['customer' => 'Budi']])
            ->assertOk()
            ->assertJsonPath('draft_data.customer', 'Budi');

        $this->withToken($token)
            ->getJson('/api/drafts/restore')
            ->assertOk()
            ->assertJson(['draft_data' => ['customer' => 'Budi']]);

        $this->assertDatabaseHas('draft_autosaves', [
            'user_id' => $user->id,
            'is_restored' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/drafts/restore')
            ->assertOk()
            ->assertJson(['draft_data' => null]);
    }

    public function test_owner_can_clear_their_drafts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/drafts', ['draft_data' => ['items' => []]])
            ->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/drafts')
            ->assertOk();

        $this->assertDatabaseMissing('draft_autosaves', ['user_id' => $user->id]);
    }
}
