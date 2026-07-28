<?php

namespace Database\Factories\Concerns;

use App\Models\Tenant;

trait UsesDefaultTenant
{
    protected static function defaultTenantId(): int
    {
        $tenantId = app()->bound('test.default_tenant_id')
            ? app('test.default_tenant_id')
            : null;

        if (is_int($tenantId) && Tenant::query()->whereKey($tenantId)->exists()) {
            return $tenantId;
        }

        $tenantId = Tenant::factory()->create()->id;
        app()->instance('test.default_tenant_id', $tenantId);

        return $tenantId;
    }
}
