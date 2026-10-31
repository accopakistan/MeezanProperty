<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A real JPEG with an EXIF APP1 segment spliced in after the SOI marker.
 * $orientation sets the EXIF Orientation tag; $marker is extra text that
 * must not survive processing (stands in for GPS and camera metadata).
 */
function jpegWithExif(int $width, int $height, int $orientation = 1, string $marker = 'SECRET-GPS-31.5204N'): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 75, 46, 131));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = ob_get_clean();

    // Minimal little-endian TIFF: one IFD entry, Orientation (0x0112), SHORT.
    $tiff = "II\x2A\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00\x00\x00\x00\x00".$marker;
    $app1 = "Exif\x00\x00".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

    return new UploadedFile($path, 'phone-photo.jpg', 'image/jpeg', null, true);
}

it('stores new photos as resized WebP with a thumbnail and without EXIF or GPS data', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();
    $upload = jpegWithExif(3000, 2000);
    expect(file_get_contents($upload->getRealPath()))->toContain('SECRET-GPS');

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'images' => [$upload]]))
        ->assertSessionHasNoErrors();

    $image = PropertyImage::sole();
    $full = Storage::disk('public')->get($image->path);
    $thumb = Storage::disk('public')->get($image->thumb_path);

    expect($image->path)->toEndWith('.webp')
        ->and($image->thumb_path)->toEndWith('-thumb.webp')
        ->and(substr($full, 0, 4).substr($full, 8, 4))->toBe('RIFFWEBP')
        ->and([$image->width, $image->height])->toBe([1600, 1067])
        ->and(getimagesizefromstring($thumb)[0])->toBe(640)
        ->and($full)->not->toContain('SECRET-GPS')->not->toContain('Exif')
        ->and($thumb)->not->toContain('SECRET-GPS');
});

it('turns sideways phone photos upright from their EXIF orientation', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'images' => [jpegWithExif(800, 400, orientation: 6)]]));

    expect(PropertyImage::sole())->width->toBe(400)->height->toBe(800);
});

it('keeps small photos at their own size', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'images' => [UploadedFile::fake()->image('small.png', 640, 480)]]));

    expect(PropertyImage::sole())->width->toBe(640)->height->toBe(480);
});

it('rolls back the listing and removes written files when a photo cannot be processed', function () {
    Storage::fake('public');
    $this->mock(ImageProcessor::class)->shouldReceive('storeListingPhoto')->andThrow(new RuntimeException('Image could not be decoded.'));
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'images' => [UploadedFile::fake()->image('broken.jpg', 800, 600)]]))
        ->assertSessionHasErrors('images');

    expect(Property::count())->toBe(0)->and(PropertyImage::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects photos that are too small or too large to process', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['images' => [UploadedFile::fake()->image('tiny.jpg', 300, 200)]]))
        ->assertSessionHasErrors('images.0');
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['images' => [UploadedFile::fake()->image('huge.jpg', 9000, 3000)]]))
        ->assertSessionHasErrors('images.0');
});

it('lets the dealer choose the cover and reorder photos without sending a live listing back to review', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();
    // Create through the form so resubmitting the same fields changes nothing else.
    $form = listingPayload(['action' => 'draft']);
    $this->actingAs($dealer)->post(route('dealer.properties.store'), $form + ['images' => array_map(fn ($n) => UploadedFile::fake()->image("p{$n}.jpg", 800, 600), [1, 2, 3])]);
    $property = Property::sole();
    $property->forceFill(['status' => PropertyStatus::Published, 'published_at' => now(), 'expires_at' => now()->addDays(90)])->save();
    [$a, $b, $c] = $property->images()->get()->all();
    $foreign = Property::factory()->create()->images()->create(['path' => 'x.webp', 'sort_order' => 1]);

    $this->actingAs($dealer)
        ->put(route('dealer.properties.update', $property), $form + ['image_order' => [$c->id, $foreign->id, $a->id, $b->id], 'cover_image' => $b->id])
        ->assertSessionHasNoErrors();

    expect($property->images()->pluck('id')->all())->toBe([$b->id, $c->id, $a->id])
        ->and($foreign->fresh()->sort_order)->toBe(1)
        ->and($property->fresh()->status)->toBe(PropertyStatus::Published);
});

it('deletes both the photo and its thumbnail when a photo is removed', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'images' => [UploadedFile::fake()->image('a.jpg', 800, 600)]]));
    $property = Property::sole();
    $image = $property->images()->sole();

    $this->actingAs($dealer)->put(route('dealer.properties.update', $property), listingPayload(['action' => 'draft', 'remove_images' => [$image->id]]))->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($image->path);
    Storage::disk('public')->assertMissing($image->thumb_path);
});

it('uses thumbnails on cards and falls back to the full photo for older uploads', function () {
    $property = Property::factory()->create(['title' => 'Thumbnail Card House']);
    $property->images()->create(['path' => 'properties/1/full.webp', 'thumb_path' => 'properties/1/full-thumb.webp', 'sort_order' => 1]);
    $legacy = Property::factory()->create(['title' => 'Legacy Photo House']);
    $legacy->images()->create(['path' => 'properties/2/old.jpg', 'sort_order' => 1]);

    $this->get(route('properties.index'))
        ->assertSee('full-thumb.webp', false)
        ->assertSee('properties/2/old.jpg', false);
    $this->get(route('properties.show', $property))->assertSee('properties/1/full.webp', false);
});

it('converts older photos with the optimise command, keeping originals unless asked', function () {
    Storage::fake('public');
    $property = Property::factory()->create();
    Storage::disk('public')->put('properties/1/old.jpg', file_get_contents(jpegWithExif(2000, 1500)->getRealPath()));
    $image = $property->images()->create(['path' => 'properties/1/old.jpg', 'sort_order' => 1]);

    $this->artisan('meezan:optimize-images', ['--dry-run' => true])->expectsOutputToContain('would convert 1')->assertSuccessful();
    expect($image->fresh()->thumb_path)->toBeNull();

    $this->artisan('meezan:optimize-images')->assertSuccessful();
    $fresh = $image->fresh();
    expect($fresh->path)->toEndWith('.webp')->and($fresh->width)->toBe(1600);
    Storage::disk('public')->assertExists('properties/1/old.jpg');
    expect(Storage::disk('public')->get($fresh->path))->not->toContain('SECRET-GPS');
});
