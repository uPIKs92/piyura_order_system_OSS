<?php

namespace Tests\Feature;

use App\Models\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupWasSuccessful;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_success_event_records_database_row(): void
    {
        Storage::fake('backups');

        $backupName = config('backup.backup.name');
        $filename = '2026-07-18-test.zip';
        Storage::disk('backups')->put("{$backupName}/{$filename}", 'backup-zip-content');

        event(new BackupWasSuccessful('backups', $backupName));

        $this->assertDatabaseHas('backups', [
            'disk' => 'backups',
            'name' => $filename,
            'status' => 'success',
        ]);

        $backup = Backup::first();
        $this->assertNotNull($backup);
        $this->assertGreaterThan(0, $backup->size);
        $this->assertNotNull($backup->checksum);
    }
}
