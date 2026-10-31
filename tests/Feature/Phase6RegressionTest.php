<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;

// Regressions for issues found during Phase 6 browser QA.

it('never re-saves a listing when Remove is pressed after the heart already removed it', function () {
    $buyer = User::factory()->create();
    $property = Property::factory()->create();
    $buyer->savedProperties()->attach($property->id);

    // Heart (toggle) removes it, then the page's Remove button is pressed.
    $this->actingAs($buyer)->postJson(route('properties.save', $property))->assertJson(['saved' => false]);
    $this->actingAs($buyer)->post(route('properties.save', $property), ['saved' => '0'])->assertSessionHas('status', 'Property removed from saved.');
    expect($buyer->savedProperties()->count())->toBe(0);

    // An explicit save is idempotent too.
    $this->actingAs($buyer)->postJson(route('properties.save', $property), ['saved' => '1'])->assertJson(['saved' => true]);
    $this->actingAs($buyer)->postJson(route('properties.save', $property), ['saved' => '1'])->assertJson(['saved' => true]);
    expect($buyer->savedProperties()->count())->toBe(1);

    $this->actingAs($buyer)->get(route('account.saved'))->assertSee('name="saved" value="0"', false);
});

it('only ever changes the signed-in buyer’s own saved list', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $property = Property::factory()->create();
    $bob->savedProperties()->attach($property->id);

    // There is no way to name another user: the request always acts on the session user.
    $this->actingAs($alice)->post(route('properties.save', $property), ['saved' => '0', 'user_id' => $bob->id]);

    expect($bob->savedProperties()->count())->toBe(1);
});

it('uses singular wording for a single search result', function () {
    Property::factory()->create(['purpose' => 'sale', 'area_id' => area('Gulberg')->id]);

    $this->get(route('properties.index', ['area' => 'gulberg-lahore', 'purpose' => 'sale']))->assertSee('1 Property for Sale in Gulberg, Lahore')->assertDontSee('1 Properties');
    $this->get(route('properties.map', ['area' => 'gulberg-lahore', 'purpose' => 'sale']))->assertSee('1 Property Found');
});

it('explains rejected photo uploads in plain words', function () {
    $dealer = User::factory()->dealer()->create();
    // A real file (not UploadedFile::fake(), which trusts the name), so the
    // type is detected from the content as it is for real uploads.
    $path = tempnam(sys_get_temp_dir(), 'fake');
    file_put_contents($path, '<html><script>alert(1)</script></html>');
    $disguised = new UploadedFile($path, 'holiday-photo.jpg', 'image/jpeg', null, true);

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['images' => [$disguised]]))
        ->assertSessionHasErrors(['images.0' => 'Each photo must be a JPG, PNG or WebP image.']);
    expect(Property::count())->toBe(0);
});
