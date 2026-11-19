<?php

namespace App\Console\Commands;

use App\Support\PublicStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies uploaded photos that were saved to storage/app/public into
 * public/storage, the folder the web server serves, when the two are not
 * linked (Hostinger: public/storage is a plain folder). Existing files are
 * never overwritten and nothing is deleted. Dry run unless --commit.
 */
class SyncPublicStorage extends Command
{
    protected $signature = 'meezan:sync-public-storage {--commit : Copy the files (default: list them only)}';

    protected $description = 'Copy uploaded photos stranded in storage/app/public into public/storage';

    /** Folders the app writes public uploads to (listing photos, project covers and galleries). */
    public const UPLOAD_DIRECTORIES = ['properties', 'projects'];

    public const IMAGE_EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif'];

    public function handle(): int
    {
        $source = storage_path('app/public');
        $target = public_path('storage');

        if (PublicStorage::pointsTo($target, $source)) {
            $this->info('public/storage already links to storage/app/public; nothing to copy.');

            return self::SUCCESS;
        }
        if (! is_dir($source)) {
            $this->info('storage/app/public does not exist; nothing to copy.');

            return self::SUCCESS;
        }
        if (File::exists($target) && ! is_dir($target)) {
            $this->error('public/storage exists but is not a folder.');

            return self::FAILURE;
        }

        // Only the folders uploads are written to, and only images: anything else in
        // storage/app/public (on live once a stray copy of the project, .env included)
        // must never be copied into the web-served folder.
        $missing = collect(self::UPLOAD_DIRECTORIES)
            ->filter(fn (string $dir) => is_dir($source.'/'.$dir))
            ->flatMap(fn (string $dir) => File::allFiles($source.'/'.$dir))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true))
            ->reject(fn ($file) => str_starts_with($file->getFilename(), '.'))
            ->map(fn ($file) => ltrim(str_replace([str_replace('\\', '/', $source), '\\'], ['', '/'], str_replace('\\', '/', $file->getPathname())), '/'))
            ->reject(fn (string $path) => File::exists($target.'/'.$path))
            ->values();

        if ($missing->isEmpty()) {
            $this->info('Every uploaded file is already in public/storage.');

            return self::SUCCESS;
        }

        $this->line($missing->count().' file(s) missing from public/storage:');
        $missing->take(50)->each(fn (string $path) => $this->line('  '.$path));
        if ($missing->count() > 50) {
            $this->line('  … and '.($missing->count() - 50).' more');
        }

        if (! $this->option('commit')) {
            $this->warn('Dry run: nothing copied. Run again with --commit to copy them.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($missing as $path) {
            File::ensureDirectoryExists(dirname($target.'/'.$path));
            if (! File::copy($source.'/'.$path, $target.'/'.$path)) {
                $failed++;
                $this->error('Could not copy '.$path);
            }
        }

        $this->info(($missing->count() - $failed).' file(s) copied.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
