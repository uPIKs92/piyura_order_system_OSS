<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 100)->nullable();
            $table->date('expired_at')->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();

            $table->index(['product_unit_id', 'expired_at']);
        });

        Schema::create('order_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('order_item_id');
        });

        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->date('expired_at')->nullable()->after('unit_cost');
            $table->string('batch_no', 100)->nullable()->after('expired_at');
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete()->after('supplier_name');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->constrained()->nullOnDelete()->after('product_unit_id');
        });

        DB::statement("
            INSERT INTO product_batches (tenant_id, product_unit_id, batch_no, expired_at, qty, created_at, updated_at)
            SELECT p.tenant_id, pu.id, NULL, NULL, pu.stok, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM product_units pu
            INNER JOIN products p ON p.id = pu.product_id
            WHERE pu.stok > 0
              AND NOT EXISTS (SELECT 1 FROM product_batches pb WHERE pb.product_unit_id = pu.id)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_batches');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_batch_id']);
            $table->dropColumn('product_batch_id');
        });

        Schema::dropIfExists('product_batches');

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::dropIfExists('suppliers');

        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('expired_at');
        });

        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('batch_no');
        });
    }
};
