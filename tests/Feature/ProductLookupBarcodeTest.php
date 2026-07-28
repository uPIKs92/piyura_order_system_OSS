<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLookupBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_lookup_product_by_barcode(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $product = Product::factory()->create(['barcode' => '8991234567890']);

        $this->withToken($token)
            ->getJson('/api/products/lookup?barcode=8991234567890')
            ->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('barcode', '8991234567890')
            ->assertJsonStructure(['units']);
    }

    public function test_lookup_returns_404_for_unknown_barcode(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/products/lookup?barcode=unknown')
            ->assertNotFound();
    }
}
