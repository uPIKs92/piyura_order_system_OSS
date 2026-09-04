<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconcileCustomersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
    }

    private function reconcile(): void
    {
        $this->artisan('customers:reconcile')->assertSuccessful();
    }

    public function test_collapsed_name_only_customer_splits_into_one_row_per_phone(): void
    {
        $collapsed = CustomerFactory::new()->create([
            'name' => 'Budi Santoso',
            'phone' => '0822222222',
            'orders_count' => 0,
            'last_ordered_at' => null,
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-001',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0811111111',
            'order_date' => '2026-01-10',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-002',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0822222222',
            'order_date' => '2026-02-20',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-003',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0811111111',
            'order_date' => '2026-03-05',
        ]);

        $this->reconcile();

        $rows = Customer::query()->orderBy('phone')->get();

        $this->assertSame(2, $rows->count());

        $phoneA = $rows->firstWhere('phone', '0811111111');
        $phoneB = $rows->firstWhere('phone', '0822222222');

        $this->assertNotNull($phoneA);
        $this->assertNotNull($phoneB);
        $this->assertSame($collapsed->id, $phoneB->id);
        $this->assertSame('Budi Santoso', $phoneA->name);
        $this->assertSame(2, $phoneA->orders_count);
        $this->assertSame('2026-03-05', $phoneA->last_ordered_at->toDateString());
        $this->assertSame(1, $phoneB->orders_count);
        $this->assertSame('2026-02-20', $phoneB->last_ordered_at->toDateString());
    }

    public function test_guest_derived_phoneless_customer_is_pruned_and_manual_row_survives(): void
    {
        $guest = CustomerFactory::new()->create([
            'name' => 'Walk In Customer',
            'phone' => null,
        ]);

        $manual = CustomerFactory::new()->create([
            'name' => 'Catatan Manual',
            'phone' => null,
            'orders_count' => 0,
            'last_ordered_at' => '2020-01-01 00:00:00',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-010',
            'customer_name' => 'Walk In Customer',
            'customer_phone' => null,
            'order_date' => '2026-04-01',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-011',
            'customer_name' => 'Pelanggan Datang',
            'customer_phone' => '   ',
            'order_date' => '2026-04-02',
        ]);

        $this->reconcile();

        $this->assertSoftDeleted('customers', ['id' => $guest->id]);
        $this->assertDatabaseHas('customers', ['id' => $manual->id, 'deleted_at' => null]);

        $manual->refresh();
        $this->assertSame(0, $manual->orders_count);
        $this->assertSame('2020-01-01', $manual->last_ordered_at->toDateString());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_counters_are_zero_and_null_when_only_trashed_orders_match(): void
    {
        $customer = CustomerFactory::new()->create([
            'name' => 'Rina Melati',
            'phone' => '0899000111',
            'orders_count' => 5,
            'last_ordered_at' => '2025-12-31 10:00:00',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-020',
            'customer_name' => 'Rina Melati',
            'customer_phone' => '0899000111',
            'order_date' => '2026-02-02',
        ])->delete();

        Order::factory()->create([
            'invoice_no' => 'INV-RC-021',
            'customer_name' => 'Rina Melati',
            'customer_phone' => ' 0899000111 ',
            'order_date' => '2026-01-15',
        ])->delete();

        $this->reconcile();

        $customer->refresh();
        $this->assertSame(0, $customer->orders_count);
        $this->assertNull($customer->last_ordered_at);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_rerunning_the_command_is_idempotent(): void
    {
        CustomerFactory::new()->create([
            'name' => 'Budi Santoso',
            'phone' => null,
        ]);
        CustomerFactory::new()->create([
            'name' => 'Walk In Customer',
            'phone' => null,
        ]);
        CustomerFactory::new()->create([
            'name' => 'Catatan Manual',
            'phone' => null,
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-030',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0811111111',
            'order_date' => '2026-01-10',
        ]);
        Order::factory()->create([
            'invoice_no' => 'INV-RC-031',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0811111111',
            'order_date' => '2026-03-05',
        ]);
        Order::factory()->create([
            'invoice_no' => 'INV-RC-032',
            'customer_name' => 'Walk In Customer',
            'customer_phone' => null,
            'order_date' => '2026-02-01',
        ]);

        $this->reconcile();

        $snapshot = fn () => DB::table('customers')->orderBy('id')->get()->toJson();

        $afterFirstRun = $snapshot();

        $this->reconcile();

        $this->assertSame($afterFirstRun, $snapshot());

        $this->assertSame(2, DB::table('customers')->whereNull('deleted_at')->count());
        $this->assertSame(
            1,
            DB::table('customers')
                ->whereNull('deleted_at')
                ->where('phone', '0811111111')
                ->count()
        );
        $this->assertSame(1, DB::table('customers')->whereNotNull('deleted_at')->count());
    }

    public function test_command_is_scoped_per_tenant(): void
    {
        $guestA = CustomerFactory::new()->create([
            'name' => 'Walk In Customer',
            'phone' => null,
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-RC-040',
            'customer_name' => 'Walk In Customer',
            'customer_phone' => null,
            'order_date' => '2026-04-01',
        ]);
        Order::factory()->create([
            'invoice_no' => 'INV-RC-041',
            'customer_name' => 'Ani',
            'customer_phone' => '0822222222',
            'order_date' => '2026-04-05',
        ]);

        $tenantB = Tenant::factory()->create(['slug' => 'toko-b']);

        $guestB = CustomerFactory::new()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Walk In Customer',
            'phone' => null,
        ]);

        $aniB = CustomerFactory::new()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Ani',
            'phone' => '0822222222',
            'orders_count' => 3,
            'last_ordered_at' => '2025-06-01 08:00:00',
        ]);

        $this->reconcile();

        $this->assertSoftDeleted('customers', ['id' => $guestA->id]);
        $this->assertDatabaseHas('customers', ['id' => $guestB->id, 'deleted_at' => null]);

        $aniB->refresh();
        $this->assertSame(0, $aniB->orders_count);
        $this->assertNull($aniB->last_ordered_at);

        $tenantACustomer = Customer::query()
            ->where('tenant_id', $this->owner->tenant_id)
            ->where('phone', '0822222222')
            ->first();
        $this->assertNotNull($tenantACustomer);
        $this->assertSame(1, $tenantACustomer->orders_count);
        $this->assertSame('2026-04-05', $tenantACustomer->last_ordered_at->toDateString());
    }
}
