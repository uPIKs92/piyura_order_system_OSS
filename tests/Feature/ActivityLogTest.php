<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_activity_log_lists_status_change(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = Order::factory()->create(['user_id' => $owner->id, 'version' => 1]);

        $this->withToken($token)
            ->putJson("/api/orders/{$order->id}", [
                'version' => 1,
                'status' => 'cancelled',
            ])
            ->assertOk();

        $response = $this->withToken($token)
            ->getJson("/api/orders/{$order->id}/activity");

        $response->assertOk();
        $this->assertTrue(
            collect($response->json())->pluck('description')->contains('status_changed')
        );
    }

    public function test_soft_delete_writes_order_deleted_activity(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->withToken($token)
            ->deleteJson("/api/orders/{$order->id}")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'description' => 'order_deleted',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_staff_cannot_view_other_staff_order_activity(): void
    {
        $staffA = User::factory()->create();
        $staffB = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $staffA->id]);
        $token = $staffB->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/orders/{$order->id}/activity")
            ->assertForbidden();
    }
}
