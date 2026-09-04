<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersReassignCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigns_orders_within_same_tenant_and_deactivates_from_user(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create(['tenant_id' => $from->tenant_id]);
        $order = Order::factory()->create(['tenant_id' => $from->tenant_id, 'user_id' => $from->id]);

        $this->artisan('orders:reassign', ['--from' => $from->id, '--to' => $to->id])
            ->assertSuccessful()
            ->expectsOutputToContain("Reassigned 1 orders from user {$from->id} to {$to->id}");

        $this->assertSame($to->id, $order->fresh()->user_id);
        $this->assertFalse($from->fresh()->is_active);
        $this->assertTrue($to->fresh()->is_active);
    }

    public function test_aborts_when_users_belong_to_different_tenants(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $order = Order::factory()->create(['tenant_id' => $from->tenant_id, 'user_id' => $from->id]);

        $this->artisan('orders:reassign', ['--from' => $from->id, '--to' => $to->id])
            ->assertFailed()
            ->expectsOutputToContain('Users must belong to the same tenant');

        $this->assertSame($from->id, $order->fresh()->user_id);
        $this->assertTrue($from->fresh()->is_active);
    }

    public function test_aborts_for_unknown_user(): void
    {
        $to = User::factory()->create();

        $this->artisan('orders:reassign', ['--from' => '999999', '--to' => $to->id])
            ->assertFailed()
            ->expectsOutputToContain('Both --from and --to must reference existing users');
    }
}
