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
}
