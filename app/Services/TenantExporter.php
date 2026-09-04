<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantSettings;

class TenantExporter
{
    public function export(Tenant $tenant): array
    {
        app()->instance('currentTenantId', $tenant->id);

        $settings = TenantSettings::for($tenant->id);

        return [
            'exported_at' => now()->toIso8601String(),
            'tenant' => $tenant->only([
                'id', 'slug', 'name', 'tagline', 'address', 'phone', 'email', 'invoice_footer_text',
            ]),
            'settings' => [
                'ppn' => $settings->ppnToArray(),
                'integrations' => $settings->integrationsToArray(),
            ],
            'users' => User::query()
                ->where('tenant_id', $tenant->id)
                ->get(['id', 'name', 'email', 'role', 'is_active'])
                ->all(),
            'categories' => Category::query()
                ->where('tenant_id', $tenant->id)
                ->get()
                ->all(),
            'products' => Product::query()
                ->where('tenant_id', $tenant->id)
                ->with('units')
                ->get()
                ->all(),
            'orders' => Order::query()
                ->where('tenant_id', $tenant->id)
                ->with(['items', 'payments'])
                ->get()
                ->all(),
            'expenses' => Expense::query()
                ->where('tenant_id', $tenant->id)
                ->get()
                ->all(),
        ];
    }
}
