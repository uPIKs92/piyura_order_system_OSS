<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('n8n_sync_outbox') && ! Schema::hasTable('sheets_sync_outbox')) {
            Schema::rename('n8n_sync_outbox', 'sheets_sync_outbox');
        }

        if (! Schema::hasTable('sheets_sync_outbox')) {
            Schema::create('sheets_sync_outbox', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->uuid('idempotency_key')->unique();
                $table->json('payload');
                $table->string('status')->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sheets_sync_outbox') && ! Schema::hasTable('n8n_sync_outbox')) {
            Schema::rename('sheets_sync_outbox', 'n8n_sync_outbox');
        }
    }
};
