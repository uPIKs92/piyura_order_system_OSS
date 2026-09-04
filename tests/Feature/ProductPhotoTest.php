<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $ownerToken;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_owner_can_upload_product_photo(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $response = $this->withToken($this->ownerToken)->post(
            "/api/products/{$this->product->id}/photo",
            ['photo' => $file],
        );

        $response->assertOk()
            ->assertJsonPath('photo_path', "tenants/{$this->owner->tenant_id}/products/{$this->product->id}/photo.jpg");

        Storage::disk('public')->assertExists("tenants/{$this->owner->tenant_id}/products/{$this->product->id}/photo.jpg");

        $this->assertNotNull($response->json('photo_url'));
    }

    public function test_uploading_new_photo_replaces_old(): void
    {
        $jpg = UploadedFile::fake()->image('old.jpg', 100, 100);
        $this->withToken($this->ownerToken)->post(
            "/api/products/{$this->product->id}/photo",
            ['photo' => $jpg],
        );

        $oldPath = "tenants/{$this->owner->tenant_id}/products/{$this->product->id}/photo.jpg";
        Storage::disk('public')->assertExists($oldPath);

        $png = UploadedFile::fake()->image('new.png', 100, 100);
        $this->withToken($this->ownerToken)->post(
            "/api/products/{$this->product->id}/photo",
            ['photo' => $png],
        );

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists("tenants/{$this->owner->tenant_id}/products/{$this->product->id}/photo.png");
    }

    public function test_rejects_non_image_file(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->withToken($this->ownerToken)->postJson(
            "/api/products/{$this->product->id}/photo",
            ['photo' => $file],
        );

        $response->assertStatus(422);
    }

    public function test_owner_can_delete_photo(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
        $this->withToken($this->ownerToken)->post(
            "/api/products/{$this->product->id}/photo",
            ['photo' => $file],
        );

        $path = "tenants/{$this->owner->tenant_id}/products/{$this->product->id}/photo.jpg";
        Storage::disk('public')->assertExists($path);

        $response = $this->withToken($this->ownerToken)->deleteJson(
            "/api/products/{$this->product->id}/photo",
        );

        $response->assertOk()
            ->assertJsonPath('photo_path', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_product_removes_photo_file(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
        $this->withToken($this->ownerToken)->post(
            "/api/products/{$this->product->id}/photo",
            ['photo' => $file],
        );

        $path = "tenants/{$this->owner->tenant_id}/products/{$this->product->id}/photo.jpg";
        Storage::disk('public')->assertExists($path);

        $this->withToken($this->ownerToken)->deleteJson("/api/products/{$this->product->id}");

        Storage::disk('public')->assertMissing($path);
    }
}
