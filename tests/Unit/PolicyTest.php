<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_enum(): void
    {
        $this->assertEquals('owner', UserRole::Owner->value);
        $this->assertEquals('staff', UserRole::Staff->value);
    }

    public function test_user_is_owner_and_is_staff(): void
    {
        $owner = User::factory()->owner()->make();
        $staff = User::factory()->make();

        $this->assertTrue($owner->isOwner());
        $this->assertFalse($owner->isStaff());
        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isOwner());
    }

    public function test_user_policy_owner_full_access(): void
    {
        $owner = User::factory()->owner()->make(['tenant_id' => 1]);
        $policy = app(UserPolicy::class);

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->update($owner, User::factory()->make(['tenant_id' => 1])));
        $this->assertTrue($policy->delete($owner, User::factory()->make(['tenant_id' => 1])));
    }

    public function test_user_policy_staff_read_only(): void
    {
        $staff = User::factory()->make();
        $otherStaff = User::factory()->make();
        $policy = app(UserPolicy::class);

        $this->assertFalse($policy->viewAny($staff));
        $this->assertFalse($policy->create($staff));
        $this->assertFalse($policy->update($staff, $otherStaff));
        $this->assertFalse($policy->delete($staff, $otherStaff));
        $this->assertTrue($policy->view($staff, $staff)); // can view own
    }

    public function test_category_policy_owner_crud_staff_readonly(): void
    {
        $owner = User::factory()->owner()->make(['tenant_id' => 1]);
        $staff = User::factory()->make(['tenant_id' => 1]);
        $policy = app(CategoryPolicy::class);
        $category = Category::factory()->make(['tenant_id' => 1]);

        // Owner
        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->view($owner, $category));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->update($owner, $category));
        $this->assertTrue($policy->delete($owner, $category));

        // Staff read-only
        $this->assertTrue($policy->viewAny($staff));
        $this->assertTrue($policy->view($staff, $category));
        $this->assertFalse($policy->create($staff));
        $this->assertFalse($policy->update($staff, $category));
        $this->assertFalse($policy->delete($staff, $category));
    }

    public function test_product_policy_owner_crud_staff_readonly(): void
    {
        $owner = User::factory()->owner()->make(['tenant_id' => 1]);
        $staff = User::factory()->make(['tenant_id' => 1]);
        $policy = app(ProductPolicy::class);
        $product = Product::factory()->make(['tenant_id' => 1]);

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->view($owner, $product));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->update($owner, $product));
        $this->assertTrue($policy->delete($owner, $product));

        $this->assertTrue($policy->viewAny($staff));
        $this->assertTrue($policy->view($staff, $product));
        $this->assertFalse($policy->create($staff));
        $this->assertFalse($policy->update($staff, $product));
        $this->assertFalse($policy->delete($staff, $product));
    }

    public function test_order_policy_force_delete_same_tenant_allowed(): void
    {
        $owner = User::factory()->owner()->make(['tenant_id' => 1]);
        $order = Order::factory()->make(['tenant_id' => 1]);
        $policy = app(OrderPolicy::class);

        $this->assertTrue($policy->forceDelete($owner, $order));
    }

    public function test_order_policy_force_delete_cross_tenant_denied(): void
    {
        $ownerA = User::factory()->owner()->make(['tenant_id' => 1]);
        $orderB = Order::factory()->make(['tenant_id' => 2]);
        $policy = app(OrderPolicy::class);

        $this->assertFalse($policy->forceDelete($ownerA, $orderB));
    }
}
