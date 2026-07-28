<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {filename} {--disk=} {--force : Skip confirmation}';

    protected $description = 'Restore database from a spatie backup zip file';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will overwrite the current database. Continue?')) {
            $this->info('Restore cancelled.');

            return self::SUCCESS;
        }

        $diskName = $this->option('disk')
            ?? config('backup.backup.destination.disks')[0]
            ?? env('BACKUP_DISK', 'backups');
        $backupName = config('backup.backup.name');
        $filename = $this->argument('filename');
        $remotePath = "{$backupName}/{$filename}";

        if (! Storage::disk($diskName)->exists($remotePath)) {
            $this->error("Backup not found: {$remotePath} on disk {$diskName}");

            return self::FAILURE;
        }

        $tempDir = storage_path('app/backup-restore-'.uniqid());
        File::ensureDirectoryExists($tempDir);

        $localZip = "{$tempDir}/{$filename}";
        file_put_contents($localZip, Storage::disk($diskName)->get($remotePath));

        $zip = new ZipArchive;
        if ($zip->open($localZip) !== true) {
            File::deleteDirectory($tempDir);
            $this->error('Could not open backup zip.');

            return self::FAILURE;
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $dumpFile = $this->findDatabaseDump($tempDir);
        if (! $dumpFile) {
            File::deleteDirectory($tempDir);
            $this->error('No database dump found inside backup.');

            return self::FAILURE;
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            $target = database_path('database.sqlite');
            if (File::exists($target)) {
                File::copy($target, "{$target}.pre-restore");
            }
            File::copy($dumpFile, $target);
        } else {
            $sql = file_get_contents($dumpFile);
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            $tables = DB::select('SHOW TABLES');
            $dbName = config('database.connections.mysql.database');
            $key = "Tables_in_{$dbName}";
            foreach ($tables as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table->$key.'`');
            }
            foreach (array_filter(explode(";\n", $sql)) as $statement) {
                $trimmed = trim($statement);
                if ($trimmed !== '') {
                    DB::unprepared($trimmed);
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        File::deleteDirectory($tempDir);
        $this->info("Database restored from {$filename}.");

        return self::SUCCESS;
    }

    private function findDatabaseDump(string $directory): ?string
    {
        foreach (File::allFiles($directory) as $file) {
            $path = $file->getPathname();
            if (str_ends_with($path, '.sqlite') || str_ends_with($path, '.sql')) {
                return $path;
            }
        }

        return null;
    }
}
