<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\ExpirySettings;
use Database\Factories\ProductBatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $token;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
    }

    public function test_expiry_alerts_buckets_counts_and_days_left(): void
    {
        $unit = $this->product->units()->first();

        $expired = ProductBatchFactory::new()->for($unit)->withBatchNo('B-EXP')->expired()->create(['qty' => 5]);
        $todayBatch = ProductBatchFactory::new()->for($unit)->withBatchNo('B-TODAY')->expiringIn(0)->create(['qty' => 2]);
        $near = ProductBatchFactory::new()->for($unit)->withBatchNo('B-NEAR')->expiringIn(3)->create(['qty' => 7]);
        ProductBatchFactory::new()->for($unit)->expiringIn(60)->create(['qty' => 9]);
        ProductBatchFactory::new()->for($unit)->create(['qty' => 11]);
        ProductBatchFactory::new()->for($unit)->expiringIn(2)->create(['qty' => 0]);

        $this->withToken($this->token)
            ->getJson('/api/inventory/expiry-alerts')
            ->assertOk()
            ->assertJsonPath('alert_days', 30)
            ->assertJsonPath('expired_count', 1)
            ->assertJsonPath('near_expiry_count', 2)
            ->assertJsonPath('expired.0.id', $expired->id)
            ->assertJsonPath('expired.0.product_unit_id', $unit->id)
            ->assertJsonPath('expired.0.product_id', $this->product->id)
            ->assertJsonPath('expired.0.product_name', $this->product->nama)
            ->assertJsonPath('expired.0.category_name', $this->product->category?->nama)
            ->assertJsonPath('expired.0.satuan', $unit->satuan)
            ->assertJsonPath('expired.0.batch_no', 'B-EXP')
            ->assertJsonPath('expired.0.qty', 5)
            ->assertJsonPath('expired.0.days_left', -1)
            ->assertJsonPath('near_expiry.0.id', $todayBatch->id)
            ->assertJsonPath('near_expiry.0.days_left', 0)
            ->assertJsonPath('near_expiry.1.id', $near->id)
            ->assertJsonPath('near_expiry.1.days_left', 3);
    }

    public function test_alert_days_respects_tenant_setting(): void
    {
        $unit = $this->product->units()->first();
        ProductBatchFactory::new()->for($unit)->expiringIn(60)->create(['qty' => 4]);

        app()->instance('currentTenantId', $this->owner->tenant_id);
        ExpirySettings::update(70);

        $this->withToken($this->token)
            ->getJson('/api/inventory/expiry-alerts')
            ->assertOk()
            ->assertJsonPath('alert_days', 70)
            ->assertJsonPath('near_expiry_count', 1)
            ->assertJsonPath('near_expiry.0.days_left', 60);
    }

    public function test_staff_cannot_view_expiry_alerts(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/inventory/expiry-alerts')
            ->assertForbidden();
    }

    public function test_owner_can_read_and_update_expiry_settings(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/settings/expiry')
            ->assertOk()
            ->assertJson(['alert_days' => 30]);

        $this->withToken($this->token)
            ->patchJson('/api/settings/expiry', ['alert_days' => 14])
            ->assertOk()
            ->assertJson(['alert_days' => 14]);

        $this->withToken($this->token)
            ->getJson('/api/settings/expiry')
            ->assertOk()
            ->assertJson(['alert_days' => 14]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->owner->tenant_id,
            'key' => 'expiry.alert_days',
            'value' => '14',
        ]);
    }

    public function test_expiry_settings_validation(): void
    {
        foreach ([0, 366, 'abc', null] as $bad) {
            $this->withToken($this->token)
                ->patchJson('/api/settings/expiry', ['alert_days' => $bad])
                ->assertStatus(422);
        }
    }

    public function test_staff_cannot_access_expiry_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/settings/expiry')->assertForbidden();
        $this->withToken($token)->patchJson('/api/settings/expiry', ['alert_days' => 14])->assertForbidden();
    }
}
