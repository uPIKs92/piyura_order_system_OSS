<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasSubjectIndex = collect(Schema::getIndexes('activity_log'))
            ->contains(fn (array $index): bool => empty(array_diff(['subject_type', 'subject_id'], $index['columns'])));

        if (! $hasSubjectIndex) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        $hasDefaultIndex = collect(Schema::getIndexes('activity_log'))
            ->pluck('name')
            ->contains('activity_log_subject_type_subject_id_index');

        if ($hasDefaultIndex) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropIndex(['subject_type', 'subject_id']);
            });
        }
    }
};
