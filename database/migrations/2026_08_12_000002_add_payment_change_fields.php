<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('change_due', 15, 2)->default(0)->after('total_paid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tendered', 15, 2)->nullable()->after('amount');
            $table->decimal('change_amount', 15, 2)->default(0)->after('tendered');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['change_amount', 'tendered']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('change_due');
        });
    }
};
