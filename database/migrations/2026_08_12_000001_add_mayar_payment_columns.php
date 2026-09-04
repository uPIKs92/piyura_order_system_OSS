<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('mayar_qr_url')->nullable()->after('version');
            $table->decimal('mayar_amount', 15, 2)->nullable()->after('mayar_qr_url');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('external_reference')->nullable()->after('notes');
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['external_reference']);
            $table->dropColumn('external_reference');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['mayar_qr_url', 'mayar_amount']);
        });
    }
};
