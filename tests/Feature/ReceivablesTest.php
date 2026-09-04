<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivablesTest extends TestCase
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

    private function createDebtorOrder(array $attributes = []): Order
    {
        return Order::factory()->pending()->create(array_merge([
            'grand_total' => 50000,
            'total_paid' => 20000,
        ], $attributes));
    }

    public function test_groups_orders_by_customer_phone_across_registered_and_adhoc(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi Toko', 'phone' => '081234567890']);
        $staff = User::factory()->create();

        // Same registered phone, two orders -> one group linked to the customer.
        $this->createDebtorOrder([
            'user_id' => $staff->id,
            'customer_name' => 'Budi Toko',
            'customer_phone' => '081234567890',
            'order_date' => now()->subDays(2)->toDateString(),
            'grand_total' => 30000,
            'total_paid' => 10000,
        ]);
        $this->createDebtorOrder([
            'user_id' => $staff->id,
            'customer_name' => 'Budi Toko',
            'customer_phone' => '081234567890',
            'order_date' => now()->subDays(1)->toDateString(),
            'grand_total' => 40000,
            'total_paid' => 0,
        ]);

        // Ad-hoc debtor without customer record.
        $this->createDebtorOrder([
            'customer_name' => 'Warung Sari',
            'customer_phone' => '089876543210',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/receivables');

        $response->assertOk()
            ->assertJsonPath('totals.unpaid_amount', 90000)
            ->assertJsonPath('totals.unpaid_count', 3)
            ->assertJsonPath('totals.debtor_count', 2);

        $groups = $response->json('groups');

        $this->assertSame('Budi Toko', $groups[0]['name']);
        $this->assertSame($customer->id, $groups[0]['customer_id']);
        $this->assertSame('081234567890', $groups[0]['phone']);
        $this->assertSame(60000.0, (float) $groups[0]['unpaid_amount']);
        $this->assertCount(2, $groups[0]['orders']);

        $this->assertSame('Warung Sari', $groups[1]['name']);
        $this->assertNull($groups[1]['customer_id']);
        $this->assertSame(30000.0, (float) $groups[1]['unpaid_amount']);
    }

    public function test_adhoc_orders_without_phone_group_by_normalized_name(): void
    {
        $this->createDebtorOrder([
            'customer_name' => 'Siti Aminah',
            'customer_phone' => null,
            'grand_total' => 20000,
            'total_paid' => 5000,
        ]);
        $this->createDebtorOrder([
            'customer_name' => 'siti   aminah',
            'customer_phone' => null,
            'grand_total' => 10000,
            'total_paid' => 0,
        ]);
        // Different name -> separate group.
        $this->createDebtorOrder([
            'customer_name' => 'Joko',
            'customer_phone' => null,
            'grand_total' => 8000,
            'total_paid' => 0,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/receivables')
            ->assertOk()
            ->assertJsonPath('totals.unpaid_amount', 33000)
            ->assertJsonPath('totals.unpaid_count', 3)
            ->assertJsonPath('totals.debtor_count', 2);
    }

    public function test_groups_sorted_by_unpaid_amount_desc(): void
    {
        $this->createDebtorOrder(['customer_name' => 'Kecil', 'customer_phone' => '081111111111', 'grand_total' => 10000, 'total_paid' => 9000]);
        $this->createDebtorOrder(['customer_name' => 'Besar', 'customer_phone' => '082222222222', 'grand_total' => 90000, 'total_paid' => 0]);
        $this->createDebtorOrder(['customer_name' => 'Sedang', 'customer_phone' => '083333333333', 'grand_total' => 40000, 'total_paid' => 10000]);

        $response = $this->withToken($this->token)->getJson('/api/receivables');

        $response->assertOk();

        $this->assertSame(['Besar', 'Sedang', 'Kecil'], array_column($response->json('groups'), 'name'));
    }

    public function test_aging_buckets_from_oldest_order_date(): void
    {
        $this->createDebtorOrder(['customer_name' => 'Fresh', 'customer_phone' => '081111111111', 'order_date' => now()->subDays(3)->toDateString()]);
        $this->createDebtorOrder(['customer_name' => 'TwoWeeks', 'customer_phone' => '082222222222', 'order_date' => now()->subDays(10)->toDateString()]);
        $this->createDebtorOrder(['customer_name' => 'HalfMonth', 'customer_phone' => '083333333333', 'order_date' => now()->subDays(20)->toDateString()]);
        $this->createDebtorOrder(['customer_name' => 'Ancient', 'customer_phone' => '084444444444', 'order_date' => now()->subDays(40)->toDateString()]);
        // Same group with an older order -> bucket from oldest.
        $this->createDebtorOrder(['customer_name' => 'Ancient', 'customer_phone' => '084444444444', 'order_date' => now()->subDays(5)->toDateString()]);

        $response = $this->withToken($this->token)->getJson('/api/receivables');

        $response->assertOk();

        $buckets = array_column($response->json('groups'), 'aging_bucket', 'name');

        $this->assertSame('<=7', $buckets['Fresh']);
        $this->assertSame('8-14', $buckets['TwoWeeks']);
        $this->assertSame('15-30', $buckets['HalfMonth']);
        $this->assertSame('>30', $buckets['Ancient']);

        $ancient = collect($response->json('groups'))->firstWhere('name', 'Ancient');
        $this->assertSame(now()->subDays(40)->toDateString(), $ancient['oldest_order_date']);
        $this->assertSame(40, $ancient['oldest_days']);
    }

    public function test_excludes_draft_cancelled_fully_paid_overpaid_and_soft_deleted(): void
    {
        $this->createDebtorOrder(['customer_name' => 'Real', 'customer_phone' => '081111111111']);

        Order::factory()->create(['customer_name' => 'Draft', 'customer_phone' => '082222222222', 'status' => OrderStatus::Draft, 'grand_total' => 50000, 'total_paid' => 0]);
        Order::factory()->create(['customer_name' => 'Cancelled', 'customer_phone' => '083333333333', 'status' => OrderStatus::Cancelled, 'grand_total' => 50000, 'total_paid' => 0]);
        Order::factory()->pending()->create(['customer_name' => 'PaidOff', 'customer_phone' => '084444444444', 'grand_total' => 50000, 'total_paid' => 50000]);
        Order::factory()->pending()->create(['customer_name' => 'Overpaid', 'customer_phone' => '085555555555', 'grand_total' => 40000, 'total_paid' => 45000]);
        $deleted = $this->createDebtorOrder(['customer_name' => 'Deleted', 'customer_phone' => '086666666666']);
        $deleted->delete();

        $response = $this->withToken($this->token)->getJson('/api/receivables');

        $response->assertOk()
            ->assertJsonPath('totals.unpaid_amount', 30000)
            ->assertJsonPath('totals.unpaid_count', 1)
            ->assertJsonPath('totals.debtor_count', 1)
            ->assertJsonPath('groups.0.name', 'Real');
    }

    public function test_totals_match_orders_summary_unpaid_math(): void
    {
        $staff = User::factory()->create();
        $this->createDebtorOrder(['user_id' => $staff->id, 'customer_name' => 'A', 'customer_phone' => '081111111111', 'grand_total' => 30000, 'total_paid' => 10000]);
        $this->createDebtorOrder(['user_id' => $staff->id, 'customer_name' => 'B', 'customer_phone' => '082222222222', 'grand_total' => 20000, 'total_paid' => 0]);
        Order::factory()->pending()->create(['user_id' => $staff->id, 'customer_name' => 'C', 'customer_phone' => '083333333333', 'grand_total' => 50000, 'total_paid' => 50000]);
        Order::factory()->create(['user_id' => $staff->id, 'customer_name' => 'D', 'customer_phone' => '084444444444', 'status' => OrderStatus::Draft, 'grand_total' => 99999, 'total_paid' => 0]);

        $receivables = $this->withToken($this->token)->getJson('/api/receivables')->json('totals');
        $summary = $this->withToken($this->token)->getJson('/api/orders/summary')->json();

        $this->assertSame($summary['unpaid_amount'], $receivables['unpaid_amount']);
        $this->assertSame($summary['unpaid_count'], $receivables['unpaid_count']);
        $this->assertSame(40000.0, (float) $receivables['unpaid_amount']);
        $this->assertSame(2, $receivables['debtor_count']);
    }

    public function test_staff_can_view_receivables(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $this->createDebtorOrder(['user_id' => $staff->id, 'customer_name' => 'Budi', 'customer_phone' => '081234567890']);

        $this->withToken($token)
            ->getJson('/api/receivables')
            ->assertOk()
            ->assertJsonPath('totals.debtor_count', 1);
    }

}
