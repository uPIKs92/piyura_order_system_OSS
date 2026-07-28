<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = database_path('database.sqlite');
        if (File::exists($this->dbPath)) {
            File::delete($this->dbPath);
        }

        config(['database.connections.sqlite.database' => $this->dbPath]);
        DB::purge('sqlite');
        $this->artisan('migrate:fresh');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->dbPath)) {
            File::delete($this->dbPath);
        }
        if (File::exists($this->dbPath.'.pre-restore')) {
            File::delete($this->dbPath.'.pre-restore');
        }

        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_backup_restore_replaces_sqlite_database(): void
    {
        User::factory()->owner()->create(['email' => 'before@example.com']);

        $backupName = config('backup.backup.name');
        $filename = 'restore-test.zip';
        $zipPath = storage_path('app/'.$filename);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($this->dbPath, 'db-dumps/sqlite-database.sqlite');
        $zip->close();

        Storage::fake('backups');
        Storage::disk('backups')->put("{$backupName}/{$filename}", file_get_contents($zipPath));
        File::delete($zipPath);

        User::query()->delete();
        User::factory()->owner()->create(['email' => 'after@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'before@example.com']);

        $this->artisan('backup:restore', [
            'filename' => $filename,
            '--disk' => 'backups',
            '--force' => true,
        ])->assertSuccessful();

        DB::purge('sqlite');
        $this->assertDatabaseHas('users', ['email' => 'before@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'after@example.com']);
    }
}
