<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBoundsTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithToken(): array
    {
        $owner = User::factory()->owner()->create();

        return [$owner, $owner->createToken('test')->plainTextToken];
    }

    public function test_daily_trend_rejects_ranges_longer_than_730_days(): void
    {
        [, $token] = $this->ownerWithToken();

        $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2025-01-01&to=2027-01-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_daily_trend_allows_exactly_730_day_ranges(): void
    {
        [, $token] = $this->ownerWithToken();

        $response = $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2025-01-01&to=2027-01-01');

        $response->assertOk();
        $this->assertSame(731, count($response->json('items')));
        $this->assertSame('2025-01-01', $response->json('period.from'));
        $this->assertSame('2027-01-01', $response->json('period.to'));
    }

    public function test_product_report_rejects_product_ids_from_other_tenant(): void
    {
        [$owner, $token] = $this->ownerWithToken();

        $tenantB = Tenant::factory()->create();
        $foreign = Product::factory()->create(['tenant_id' => $tenantB->id]);

        $this->assertNotSame($owner->tenant_id, $foreign->tenant_id);

        $this->withToken($token)
            ->getJson("/api/reports/product?product_id={$foreign->id}&from=2026-01-01&to=2026-01-31")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }
}
