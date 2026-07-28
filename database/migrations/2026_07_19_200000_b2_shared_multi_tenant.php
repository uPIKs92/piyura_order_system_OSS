<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('invoice_footer_text');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::table('n8n_sync_outbox', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $this->backfillTenantIds();
        $this->replaceGlobalUniquesWithTenantScoped();
        $this->seedDefaultTenantSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('is_suspended');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->unique('email');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropUnique(['tenant_id', 'sku']);
            $table->unique('slug');
            $table->unique('sku');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_no']);
            $table->unique('invoice_no');
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'receipt_no']);
            $table->unique('receipt_no');
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'return_no']);
            $table->unique('return_no');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('n8n_sync_outbox', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }

    private function backfillTenantIds(): void
    {
        foreach (DB::table('n8n_sync_outbox')->select('id', 'order_id')->get() as $row) {
            $tenantId = DB::table('orders')->where('id', $row->order_id)->value('tenant_id');
            if ($tenantId) {
                DB::table('n8n_sync_outbox')->where('id', $row->id)->update(['tenant_id' => $tenantId]);
            }
        }

        foreach (DB::table('returns')->select('id', 'order_id')->get() as $row) {
            $tenantId = DB::table('orders')->where('id', $row->order_id)->value('tenant_id');
            if ($tenantId) {
                DB::table('returns')->where('id', $row->id)->update(['tenant_id' => $tenantId]);
            }
        }
    }

    private function replaceGlobalUniquesWithTenantScoped(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropUnique(['sku']);
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'sku']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_no']);
            $table->unique(['tenant_id', 'invoice_no']);
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropUnique(['receipt_no']);
            $table->unique(['tenant_id', 'receipt_no']);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->dropUnique(['return_no']);
            $table->unique(['tenant_id', 'return_no']);
        });
    }

    private function seedDefaultTenantSettings(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');
        $now = now();

        foreach ($tenantIds as $tenantId) {
            if (DB::table('tenant_settings')->where('tenant_id', $tenantId)->exists()) {
                continue;
            }

            DB::table('tenant_settings')->insert([
                ['tenant_id' => $tenantId, 'key' => 'ppn.enabled', 'value' => json_encode((bool) config('ppn.enabled', true)), 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenantId, 'key' => 'ppn.percentage', 'value' => json_encode((float) config('ppn.percentage', 11)), 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenantId, 'key' => 'n8n.enabled', 'value' => json_encode((bool) config('n8n.webhook_url')), 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenantId, 'key' => 'n8n.webhook_url', 'value' => json_encode(config('n8n.webhook_url')), 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenantId, 'key' => 'sheets.spreadsheet_id', 'value' => json_encode(null), 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }
};
