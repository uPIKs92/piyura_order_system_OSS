<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_deletes_old_logs_but_keeps_important(): void
    {
        $order = Order::factory()->create();
        $old = now()->subDays(91);

        $deleted = Activity::create([
            'log_name' => 'default',
            'description' => 'routine_update',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'properties' => ['foo' => 'bar'],
            'created_at' => $old,
            'updated_at' => $old,
        ]);

        $kept = Activity::create([
            'log_name' => 'default',
            'description' => 'status_changed',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'properties' => ['important' => true],
            'created_at' => $old,
            'updated_at' => $old,
        ]);

        $recent = Activity::create([
            'log_name' => 'default',
            'description' => 'recent',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'properties' => [],
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->artisan('activitylog:clean-retention')->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['id' => $deleted->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $kept->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $recent->id]);
    }
}
