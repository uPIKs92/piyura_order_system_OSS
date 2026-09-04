<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public static function backfill(): void
    {
        DB::statement("
            INSERT INTO customers (tenant_id, name, phone, address, created_at, updated_at)
            SELECT o.tenant_id,
                   o.customer_name,
                   (SELECT o2.customer_phone
                      FROM orders o2
                     WHERE o2.tenant_id = o.tenant_id
                       AND o2.customer_name = o.customer_name
                       AND o2.customer_phone IS NOT NULL
                       AND o2.customer_phone != ''
                       AND o2.deleted_at IS NULL
                     ORDER BY o2.order_date DESC, o2.id DESC
                     LIMIT 1),
                   (SELECT o3.customer_address
                      FROM orders o3
                     WHERE o3.tenant_id = o.tenant_id
                       AND o3.customer_name = o.customer_name
                       AND o3.customer_address IS NOT NULL
                       AND o3.customer_address != ''
                       AND o3.deleted_at IS NULL
                     ORDER BY o3.order_date DESC, o3.id DESC
                     LIMIT 1),
                   CURRENT_TIMESTAMP,
                   CURRENT_TIMESTAMP
              FROM orders o
             WHERE o.customer_name IS NOT NULL
               AND o.customer_name != ''
               AND o.deleted_at IS NULL
             GROUP BY o.tenant_id, o.customer_name
        ");
    }

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        static::backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
