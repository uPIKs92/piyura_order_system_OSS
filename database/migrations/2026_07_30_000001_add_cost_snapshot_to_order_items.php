<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('cost_snapshot', 15, 2)->default(0)->after('price_snapshot');
        });

        $rows = DB::table('order_items')
            ->leftJoin('product_units', 'product_units.id', '=', 'order_items.product_unit_id')
            ->whereNull('order_items.deleted_at')
            ->select(
                'order_items.id',
                DB::raw('COALESCE(product_units.harga_beli, 0) as cost'),
            )
            ->get();

        foreach ($rows as $row) {
            DB::table('order_items')
                ->where('id', $row->id)
                ->update(['cost_snapshot' => $row->cost]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('cost_snapshot');
        });
    }
};
