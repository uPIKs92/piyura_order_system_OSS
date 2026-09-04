<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 20)->default('diambil')->after('mayar_amount');
            $table->decimal('delivery_fee', 15, 2)->default(0)->after('delivery_method');
            $table->decimal('delivery_distance_km', 6, 2)->nullable()->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'delivery_fee', 'delivery_distance_km']);
        });
    }
};
