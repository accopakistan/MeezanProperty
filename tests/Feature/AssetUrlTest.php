<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use Illuminate\Support\Facades\URL;

// Hosting with the document root above public/ sets ASSET_URL=https://…/public/.
// Every image, stylesheet and script must then be served from under it.
beforeEach(fn () => URL::useAssetOrigin('https://meezanproperty.com/public/'));

it('serves uploaded photos and placeholders from ASSET_URL', function () {
    $property = Property::factory()->status(PropertyStatus::Published)->create();
    $image = $property->images()->create(['path' => 'properties/1/a.webp', 'thumb_path' => 'properties/1/a-thumb.webp', 'sort_order' => 0]);

    expect($image->url())->toBe('https://meezanproperty.com/public/storage/properties/1/a.webp')
        ->and($image->thumbUrl())->toBe('https://meezanproperty.com/public/storage/properties/1/a-thumb.webp')
        ->and($property->placeholderUrl())->toStartWith('https://meezanproperty.com/public/images/placeholders/');
});

it('renders pages without root-relative asset paths', function (string $uri) {
    $property = Property::factory()->status(PropertyStatus::Published)->create();
    $property->images()->create(['path' => 'properties/1/a.webp', 'sort_order' => 0]);

    $html = $this->get(str_replace('{slug}', $property->slug, $uri))->assertOk()->getContent();

    // The site logo is on every page (public/images/logo).
    expect($html)->toContain('https://meezanproperty.com/public/images/logo/meezan-logo.png')
        ->not->toMatch('#(src|href|srcset)="/(images|storage|build|favicon)#');
})->with(['/', '/properties', '/properties/{slug}', '/ai-property-finder']);
