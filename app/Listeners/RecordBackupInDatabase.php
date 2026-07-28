<?php

namespace App\Listeners;

use App\Models\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupWasSuccessful;

class RecordBackupInDatabase
{
    public function handle(BackupWasSuccessful $event): void
    {
        $destination = BackupDestination::create($event->diskName, $event->backupName);
        $newest = $destination->newestBackup();

        if (! $newest) {
            return;
        }

        $path = $newest->path();
        $disk = $destination->disk();

        Backup::create([
            'name' => basename($path),
            'disk' => $event->diskName,
            'size' => $newest->sizeInBytes(),
            'path' => $path,
            'checksum' => $disk->exists($path) ? hash('sha256', $disk->get($path)) : null,
            'status' => 'success',
            'created_at' => now(),
        ]);
    }
}
