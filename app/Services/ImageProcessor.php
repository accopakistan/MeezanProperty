<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Listing photo pipeline, built on PHP's GD extension (no extra package).
 *
 * Every upload is decoded, turned upright from its EXIF orientation, scaled
 * down and re-encoded as WebP. Re-encoding drops all metadata, including
 * EXIF GPS coordinates that could reveal where a photo was taken. Two files
 * are written: a full image (longest side ≤ 1600 px) and a card thumbnail
 * (≤ 640 px). Originals are not kept.
 */
class ImageProcessor
{
    public const FULL_MAX = 1600;

    public const THUMB_MAX = 640;

    public const QUALITY = 82;

    /** Refuse anything bigger, so decoding cannot exhaust memory. */
    public const MAX_PIXELS = 40_000_000;

    /**
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function storeListingPhoto(string $sourcePath, string $directory, string $disk = 'public'): array
    {
        $image = $this->load($sourcePath);

        $full = $this->fit($image, self::FULL_MAX);
        $thumb = $this->fit($full, self::THUMB_MAX);

        $name = $directory.'/'.Str::random(32);
        $paths = ['path' => $name.'.webp', 'thumb_path' => $name.'-thumb.webp'];

        Storage::disk($disk)->put($paths['path'], $this->encode($full));
        Storage::disk($disk)->put($paths['thumb_path'], $this->encode($thumb));

        return $paths + ['width' => imagesx($full), 'height' => imagesy($full)];
    }

    protected function load(string $path): GdImage
    {
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Unsupported image type.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Image is too large to process.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Image could not be decoded.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->orient($image, $path);
        }

        imagepalettetotruecolor($image);

        return $image;
    }

    /** Phone photos are often stored sideways with an EXIF "rotate me" flag. */
    protected function orient(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return match ((int) $orientation) {
            2 => $this->flipped($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => $this->flipped($image, IMG_FLIP_VERTICAL),
            5 => $this->flipped(imagerotate($image, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => $this->flipped(imagerotate($image, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    protected function flipped(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    /** Scale down (never up) so the longest side is at most $max pixels. */
    protected function fit(GdImage $image, int $max): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $max / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        return $target;
    }

    protected function encode(GdImage $image): string
    {
        imagesavealpha($image, true);
        ob_start();
        imagewebp($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
