<?php

namespace App\Support;

class BackupSettings
{
    public static function diskName(): string
    {
        $disks = config('backup.backup.destination.disks', ['backups']);

        return (string) ($disks[0] ?? 'backups');
    }

    public static function toArray(): array
    {
        $diskName = self::diskName();
        $diskConfig = config("filesystems.disks.{$diskName}", []);
        $driver = $diskConfig['driver'] ?? 'unknown';
        $backupName = config('backup.backup.name', 'laravel-backup');

        $folder = match ($driver) {
            's3' => sprintf('s3://%s/%s/', $diskConfig['bucket'] ?? 'bucket', $backupName),
            'local' => rtrim((string) ($diskConfig['root'] ?? storage_path('app/backups')), DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR.$backupName,
            default => $diskName,
        };

        return [
            'disk' => $diskName,
            'driver' => $driver,
            'folder' => $folder,
            'backup_name' => $backupName,
        ];
    }
}
