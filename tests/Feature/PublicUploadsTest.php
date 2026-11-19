<?php

use App\Support\PublicStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Live (Hostinger): public/storage is a plain folder, not a link to storage/app/public,
// so photos written to storage/app/public returned 403. These cover the fix.

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uploads-'.Str::uuid();
    $this->source = $this->root.'/storage/app/public';
    $this->target = $this->root.'/public/storage';
    File::ensureDirectoryExists($this->source.'/properties/28');
    File::ensureDirectoryExists($this->target.'/properties/20');
    app()->usePublicPath($this->root.'/public');
    app()->useStoragePath($this->root.'/storage');
});

afterEach(fn () => File::deleteDirectory($this->root));

it('writes uploads straight into public/storage when MEEZAN_UPLOADS_IN_PUBLIC is on', function () {
    config(['filesystems.disks.public.root' => public_path('storage')]);
    Storage::forgetDisk('public');

    Storage::disk('public')->put('properties/29/a.webp', 'x');

    expect(File::exists($this->target.'/properties/29/a.webp'))->toBeTrue()
        ->and(PublicStorage::writesDirectly())->toBeTrue()
        ->and(PublicStorage::ready())->toBeTrue();
});

it('reports photos as unreachable when public/storage is a plain folder beside the disk', function () {
    config(['filesystems.disks.public.root' => $this->source]);

    expect(PublicStorage::ready())->toBeFalse()
        ->and(PublicStorage::fixHint())->toContain('MEEZAN_UPLOADS_IN_PUBLIC=true');
});

it('copies stranded photos into public/storage without overwriting', function () {
    File::put($this->source.'/properties/28/new.webp', 'new');
    File::ensureDirectoryExists($this->source.'/properties/20');
    File::put($this->source.'/properties/20/old.webp', 'from-storage');
    File::put($this->target.'/properties/20/old.webp', 'already-served');

    $this->artisan('meezan:sync-public-storage')->expectsOutputToContain('Dry run')->assertSuccessful();
    expect(File::exists($this->target.'/properties/28/new.webp'))->toBeFalse();

    $this->artisan('meezan:sync-public-storage --commit')->expectsOutputToContain('1 file(s) copied')->assertSuccessful();

    expect(File::get($this->target.'/properties/28/new.webp'))->toBe('new')
        ->and(File::get($this->target.'/properties/20/old.webp'))->toBe('already-served');
});

it('never copies anything but images from the upload folders', function () {
    File::put($this->source.'/.env', 'APP_KEY=secret');
    File::ensureDirectoryExists($this->source.'/bootstrap/cache');
    File::put($this->source.'/bootstrap/cache/config.php', '<?php // secrets');
    File::put($this->source.'/properties/28/notes.php', '<?php');
    File::put($this->source.'/properties/28/photo.jpg', 'jpg');

    $this->artisan('meezan:sync-public-storage --commit')->expectsOutputToContain('1 file(s) copied')->assertSuccessful();

    expect(File::exists($this->target.'/properties/28/photo.jpg'))->toBeTrue()
        ->and(File::exists($this->target.'/.env'))->toBeFalse()
        ->and(File::exists($this->target.'/bootstrap'))->toBeFalse()
        ->and(File::exists($this->target.'/properties/28/notes.php'))->toBeFalse();
});
