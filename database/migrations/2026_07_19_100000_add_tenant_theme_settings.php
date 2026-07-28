<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('theme_mode', 10)->default('system')->after('invoice_footer_text');
            $table->string('theme_palette', 20)->default('neutral')->after('theme_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['theme_mode', 'theme_palette']);
        });
    }
};
