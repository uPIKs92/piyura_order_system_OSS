<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('disk');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('path');
            $table->string('checksum', 64)->nullable();
            $table->string('status', 20)->default('success');
            $table->timestamp('created_at')->useCurrent();

            $table->index('disk');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
