<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('last_ordered_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->index(['tenant_id', 'phone']);
            $table->index(['last_ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'phone']);
            $table->dropIndex(['last_ordered_at']);
            $table->dropColumn(['last_ordered_at', 'orders_count']);
        });
    }
};
