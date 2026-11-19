<?php

namespace App\Support;

class PublicStorage
{
    public static function pointsTo(string $publicPath, string $storagePath): bool
    {
        $publicTarget = realpath($publicPath);
        $storageTarget = realpath($storagePath);

        return $publicTarget !== false && $storageTarget !== false && $publicTarget === $storageTarget;
    }

    /** True when the "public" disk writes straight into public/storage (MEEZAN_UPLOADS_IN_PUBLIC). */
    public static function writesDirectly(): bool
    {
        return self::pointsTo((string) config('filesystems.disks.public.root'), public_path('storage'));
    }

    /**
     * Uploaded photos are reachable over HTTP: either public/storage links to the
     * public disk, or the public disk is public/storage itself and is writable.
     */
    public static function ready(): bool
    {
        $root = (string) config('filesystems.disks.public.root');

        return self::pointsTo(public_path('storage'), $root) && is_dir($root) && is_writable($root);
    }

    /** What to do when ready() is false. */
    public static function fixHint(): string
    {
        return self::writesDirectly() || config('filesystems.links') === []
            ? 'create public/storage and make it writable by PHP'
            : 'run php artisan storage:link; if public/storage is a plain folder (common on Hostinger), set MEEZAN_UPLOADS_IN_PUBLIC=true and run php artisan meezan:sync-public-storage --commit';
    }
}
