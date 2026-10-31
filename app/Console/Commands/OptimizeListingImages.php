<?php

namespace App\Console\Commands;

use App\Models\PropertyImage;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Brings photos uploaded before the image pipeline existed in line with new
 * uploads: WebP, resized, metadata stripped, with a thumbnail.
 *
 * Only rows without a thumbnail are touched. Original files are kept unless
 * --delete-originals is given, so the change can be undone by restoring the
 * old paths. --dry-run lists what would change.
 */
class OptimizeListingImages extends Command
{
    protected $signature = 'meezan:optimize-images {--dry-run : Report only} {--delete-originals : Remove the old files after converting}';

    protected $description = 'Convert existing listing photos to resized WebP with thumbnails';

    public function handle(ImageProcessor $processor): int
    {
        $disk = Storage::disk('public');
        $dry = (bool) $this->option('dry-run');
        $converted = 0;
        $skipped = 0;

        PropertyImage::whereNull('thumb_path')->chunkById(50, function ($images) use ($processor, $disk, $dry, &$converted, &$skipped) {
            foreach ($images as $image) {
                if (! $disk->exists($image->path)) {
                    $this->warn("Missing file for image {$image->id}: {$image->path}");
                    $skipped++;

                    continue;
                }

                if ($dry) {
                    $this->line("[dry run] Would convert image {$image->id}: {$image->path}");
                    $converted++;

                    continue;
                }

                try {
                    $stored = $processor->storeListingPhoto($disk->path($image->path), dirname($image->path));
                } catch (\RuntimeException $e) {
                    $this->warn("Skipped image {$image->id}: {$e->getMessage()}");
                    $skipped++;

                    continue;
                }

                $original = $image->path;
                $image->forceFill($stored)->save();
                if ($this->option('delete-originals')) {
                    $disk->delete($original);
                }
                $this->line("Converted image {$image->id}: {$original} → {$stored['path']}");
                $converted++;
            }
        });

        $this->info(($dry ? 'Dry run: would convert ' : 'Converted ').$converted.' image(s); skipped '.$skipped.'.');

        return self::SUCCESS;
    }
}
