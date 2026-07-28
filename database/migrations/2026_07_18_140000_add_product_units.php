<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('satuan', 50);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->unsignedInteger('stok')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'satuan']);
        });

        Product::query()->each(function (Product $product) {
            ProductUnit::create([
                'product_id' => $product->id,
                'satuan' => $product->satuan ?: 'pcs',
                'harga_jual' => $product->harga_jual,
                'harga_beli' => $product->harga_beli,
                'stok' => $product->stok,
                'is_default' => true,
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('satuan', 50)->nullable()->after('product_name');
        });

        OrderItem::with('product')->each(function (OrderItem $item) {
            if (! $item->product) {
                return;
            }

            $unit = $item->product->units()->where('is_default', true)->first()
                ?? $item->product->units()->first();

            if ($unit) {
                $item->update([
                    'product_unit_id' => $unit->id,
                    'satuan' => $unit->satuan,
                ]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['harga_jual', 'harga_beli', 'stok', 'satuan']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->integer('stok')->default(0);
            $table->string('satuan')->default('pcs');
        });

        Product::query()->each(function (Product $product) {
            $unit = $product->units()->where('is_default', true)->first()
                ?? $product->units()->first();

            if ($unit) {
                $product->update([
                    'harga_jual' => $unit->harga_jual,
                    'harga_beli' => $unit->harga_beli,
                    'stok' => $unit->stok,
                    'satuan' => $unit->satuan,
                ]);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn('satuan');
        });

        Schema::dropIfExists('product_units');
    }
};
