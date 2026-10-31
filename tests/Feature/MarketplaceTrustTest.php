<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Models\AuditLog;
use App\Models\DealerDocument;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use App\Notifications\DealerVerificationUpdated;
use App\Notifications\ListingReviewed;
use App\Services\DuplicateDetector;
use App\Services\ListingCompleteness;
use App\Support\MapMarkers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

function trustDealer(string $businessType = 'agency'): User
{
    $dealer = User::factory()->dealer()->create();
    $dealer->dealerProfile->forceFill(['business_type' => $businessType])->save();

    return $dealer;
}

function upload(User $dealer, string $type, ?UploadedFile $file = null)
{
    return test()->actingAs($dealer)->post(route('dealer.documents.store'), ['type' => $type, 'files' => [$file ?? UploadedFile::fake()->image($type.'.jpg', 800, 600)]]);
}

function uploadRequired(User $dealer): void
{
    foreach (DealerDocument::requiredFor($dealer->dealerProfile->business_type) as $type) {
        upload($dealer, $type)->assertSessionHasNoErrors();
    }
}

/* Documents -------------------------------------------------------------- */

it('rejects documents that are not really images even with an image name', function () {
    $dealer = trustDealer();

    upload($dealer, 'cnic_front', UploadedFile::fake()->createWithContent('cnic.jpg', 'not an image at all'))->assertSessionHasErrors('files.0');
    upload($dealer, 'cnic_front', UploadedFile::fake()->createWithContent('cnic.png', "\x89PNG\r\n\x1a\nbroken"))->assertSessionHasErrors('files.0');
    expect(DealerDocument::count())->toBe(0);
});

it('stores a content hash and never exposes documents through public storage', function () {
    $dealer = trustDealer();
    upload($dealer, 'cnic_front')->assertSessionHasNoErrors();

    $document = DealerDocument::sole();
    expect($document->sha256)->toHaveLength(64)
        ->and($document->status)->toBe('pending')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    expect($this->get('/storage/'.$document->path)->status())->toBeIn([403, 404]);
    auth()->logout();
    $this->get(route('dealer.documents.show', $document))->assertRedirect(route('login'));
});

it('does not let one dealer upload to or open another dealer\'s documents', function () {
    $owner = trustDealer();
    upload($owner, 'cnic_front');
    $other = trustDealer();

    $this->actingAs($other)->get(route('dealer.documents.show', DealerDocument::sole()))->assertNotFound();
    upload($other, 'cnic_back');
    expect(DealerDocument::where('dealer_profile_id', $owner->dealerProfile->id)->count())->toBe(1);
});

/* Verification workflow ---------------------------------------------------- */

it('runs the full verification workflow with per-document review and audit', function () {
    $dealer = trustDealer('individual');
    $profile = $dealer->dealerProfile;

    // Nothing to submit yet.
    $this->actingAs($dealer)->post(route('dealer.verification.submit'))->assertSessionHas('error');
    expect($profile->fresh()->verificationHeadline()['title'])->toBe('Verification not started');

    uploadRequired($dealer);
    $this->actingAs($dealer)->post(route('dealer.verification.submit'))->assertSessionHas('status');
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Submitted);

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'start_review']);
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::UnderReview);

    [$front, $back] = [DealerDocument::where('type', 'cnic_front')->sole(), DealerDocument::where('type', 'cnic_back')->sole()];
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', $front), ['decision' => 'approved'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', $back), ['decision' => 'rejected'])->assertSessionHasErrors('note');
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', $back), ['decision' => 'rejected', 'note' => 'Photo is blurred']);
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'request_documents', 'reason' => 'Please send a clear CNIC back']);

    $profile->refresh();
    expect($profile->verification_status)->toBe(DealerStatus::Pending)
        ->and($profile->verificationHeadline()['title'])->toBe('Additional documents required');
    Notification::assertSentTo($dealer, DealerVerificationUpdated::class, fn ($n) => $n->event === 'document_rejected' && $n->reason === 'Photo is blurred');
    $this->actingAs($dealer)->get(route('dealer.verification'))->assertSee('Photo is blurred')->assertSee('Please send a clear CNIC back');

    // Resubmission: the rejected document is kept as history, not deleted.
    upload($dealer, 'cnic_back')->assertSessionHasNoErrors();
    expect($back->fresh()->status)->toBe('superseded')->and(DealerDocument::count())->toBe(3);
    $this->actingAs($dealer)->post(route('dealer.verification.submit'))->assertSessionHas('status');

    $new = DealerDocument::where('type', 'cnic_back')->where('status', 'pending')->sole();
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', $new), ['decision' => 'approved']);
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'verify'])->assertSessionHasNoErrors();

    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Verified);
    Notification::assertSentTo($dealer, DealerVerificationUpdated::class, fn ($n) => $n->event === 'verified');

    $actions = AuditLog::where('auditable_id', $profile->id)->pluck('action');
    expect($actions)->toContain('dealer.verification_submitted', 'dealer.review_started', 'dealer.document_approved', 'dealer.document_rejected', 'dealer.documents_requested', 'dealer.verified');
    $verified = AuditLog::where('action', 'dealer.verified')->sole();
    expect($verified->properties)->toMatchArray(['from' => 'submitted', 'to' => 'verified', 'documents_approved' => ['cnic_front', 'cnic_back']])
        ->and($verified->user_id)->toBe($this->admin->id);
});

it('refuses to verify until every required document is approved', function () {
    $dealer = trustDealer('individual');
    $profile = $dealer->dealerProfile;

    // No documents at all, even with a reason.
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'verify', 'reason' => 'CNIC checked in person'])
        ->assertSessionHasErrors(['decision' => 'Approve every required document before verifying. Not yet approved: CNIC (front), CNIC (back).']);

    // Uploaded, but one is rejected.
    uploadRequired($dealer);
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', DealerDocument::where('type', 'cnic_front')->sole()), ['decision' => 'approved']);
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', DealerDocument::where('type', 'cnic_back')->sole()), ['decision' => 'rejected', 'note' => 'Cut off']);
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'verify'])->assertSessionHasErrors('decision');

    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Pending)
        ->and(AuditLog::where('action', 'dealer.verified')->exists())->toBeFalse();
    $this->actingAs($this->admin)->get(route('admin.dealers.show', $profile))->assertDontSee('Verify anyway')->assertSee('Approve every required document to verify this dealer.');
});

it('rejects, suspends and reinstates with reasons, and never deletes reviewed documents', function () {
    $dealer = trustDealer('individual');
    $profile = $dealer->dealerProfile;
    uploadRequired($dealer);
    $this->actingAs($dealer)->post(route('dealer.verification.submit'));

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'reject', 'reason' => 'Name does not match']);
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Rejected);
    Notification::assertSentTo($dealer, DealerVerificationUpdated::class, fn ($n) => $n->event === 'rejected');

    $document = DealerDocument::first();
    $this->actingAs($this->admin)->patch(route('admin.dealers.documents.review', $document), ['decision' => 'approved']);
    $this->actingAs($dealer)->delete(route('dealer.documents.destroy', $document))->assertSessionHas('error');
    expect($document->fresh())->not->toBeNull();

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'suspend', 'reason' => 'Under investigation']);
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Suspended);
    $this->actingAs($dealer)->post(route('dealer.documents.store'), ['type' => 'other', 'files' => [UploadedFile::fake()->image('x.jpg')]])->assertForbidden();

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'reinstate']);
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Pending); // was never verified
});

it('keeps internal notes away from the dealer', function () {
    $dealer = trustDealer();
    $this->actingAs($this->admin)->patch(route('admin.dealers.note', $dealer->dealerProfile), ['internal_note' => 'Called the office, number disconnected']);

    $this->actingAs($dealer)->get(route('dealer.verification'))->assertDontSee('number disconnected');
    $this->actingAs($dealer)->get(route('dealer.dashboard'))->assertDontSee('number disconnected');
});

it('blocks non-admins from every review action', function () {
    $dealer = trustDealer();
    upload($dealer, 'cnic_front');
    $other = User::factory()->dealer()->create();

    $this->actingAs($other)->patch(route('admin.dealers.documents.review', DealerDocument::sole()), ['decision' => 'approved'])->assertForbidden();
    $this->actingAs($other)->patch(route('admin.dealers.verification', $dealer->dealerProfile), ['decision' => 'verify'])->assertForbidden();
    $this->actingAs($other)->get(route('admin.trust'))->assertForbidden();
    expect(DealerDocument::sole()->status)->toBe('pending');
});

it('expires verifications only when an expiry period is configured', function () {
    $profile = User::factory()->dealer(DealerStatus::Verified)->create()->dealerProfile;

    $this->artisan('meezan:expire-verifications')->assertSuccessful();
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Verified);

    $profile->forceFill(['verification_expires_at' => now()->subDay()])->save();
    $this->artisan('meezan:expire-verifications')->assertSuccessful();
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Expired)
        ->and($profile->fresh()->isVerified())->toBeFalse();
});

/* Public trust display --------------------------------------------------- */

it('shows the verified badge only for verified dealers and no private details', function () {
    $pending = trustDealer();
    upload($pending, 'cnic_front');
    $this->actingAs($pending)->post(route('dealer.verification.submit'));
    Property::factory()->forDealer($pending)->create(['title' => 'Pending Dealer Home']);
    $this->actingAs($this->admin)->patch(route('admin.dealers.note', $pending->dealerProfile), ['internal_note' => 'secret staff note']);

    auth()->logout();
    $this->get(route('dealers.show', $pending->dealerProfile))->assertOk()
        ->assertSee('Verification pending')
        ->assertDontSee('Submitted for review')
        ->assertDontSee('secret staff note')
        ->assertDontSee('cnic_front.jpg');
});

/* Map pin, privacy and location quality ---------------------------------- */

it('keeps the area centre out of the exact pin and validates coordinates', function () {
    area('Gulberg')->update(['latitude' => 31.51, 'longitude' => 74.34]);
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['latitude' => '', 'longitude' => '']))->assertSessionHasNoErrors();
    $p = Property::sole();
    expect($p->latitude)->toBeNull()->and($p->public_latitude)->toBeNull()->and($p->location_status)->toBe('approximate');

    // Dubai: outside the Pakistan box. (Border slivers inside the box are flagged by the city check instead.)
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['title' => 'Pin in Dubai house', 'latitude' => '25.2048', 'longitude' => '55.2708']))->assertSessionHasErrors(['longitude']);
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['title' => 'Pin with text house', 'latitude' => 'abc', 'longitude' => '74.3']))->assertSessionHasErrors('latitude');
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['title' => 'Half a pin house here', 'latitude' => '31.5']))->assertSessionHasErrors('longitude');
});

it('flags a pin far from the chosen city for review instead of rejecting it', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();

    // Inside Pakistan, but in Karachi while the listing says Lahore.
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['latitude' => '24.86', 'longitude' => '67.01']))->assertSessionHasNoErrors();

    expect(Property::sole())->location_status->toBe('flagged')->location_flag_reason->toContain('from the centre of Lahore');
});

it('respects the location privacy choice everywhere public', function () {
    $exact = Property::factory()->create(['title' => 'Exact Pin Home', 'area_id' => area('Gulberg')->id, 'latitude' => 31.5123456, 'longitude' => 74.3456789, 'location_privacy' => 'exact']);
    $approx = Property::factory()->create(['title' => 'Approx Pin Home', 'area_id' => area('Gulberg')->id, 'latitude' => 31.5211111, 'longitude' => 74.3522222, 'location_privacy' => 'approximate']);
    area('Model Town')->update(['latitude' => 31.48, 'longitude' => 74.32]);
    $hidden = Property::factory()->create(['title' => 'Area Only Home', 'area_id' => area('Model Town')->id, 'latitude' => 31.4777777, 'longitude' => 74.3233333, 'location_privacy' => 'area']);

    expect($exact->public_latitude)->toBe(31.5123456)
        ->and($approx->public_latitude)->not->toBe(31.5211111)
        ->and(abs($approx->public_latitude - 31.5211111))->toBeLessThan(0.005)
        ->and($hidden->public_latitude)->toBeNull();

    $markers = collect(MapMarkers::for(Property::with(['area', 'coverImage'])->get()));
    expect($markers->firstWhere('id', $exact->id)['kind'])->toBe('pin')
        ->and($markers->firstWhere('id', $approx->id)['kind'])->toBe('approx')
        ->and($markers->firstWhere('id', $hidden->id))->toBeNull()
        ->and(json_encode($markers))->not->toContain('31.5211111')->not->toContain('31.4777777');

    $this->get(route('properties.show', $approx))->assertOk()->assertSee('Approximate location')->assertDontSee('31.5211111')->assertDontSee('google.com/maps');
    $this->get(route('properties.show', $hidden))->assertOk()->assertSee('Area only')->assertDontSee('31.4777777');
    $this->get(route('properties.show', $exact))->assertOk()->assertSee('google.com/maps/search/?api=1&query=31.5123456', false);

    // A drawn shape around the hidden pin finds nothing; around its public point it does.
    $tiny = '31.4775,74.3230;31.4780,74.3230;31.4780,74.3236;31.4775,74.3236';
    $this->get(route('properties.index', ['poly' => $tiny]))->assertDontSee('Area Only Home');
    $this->get(route('properties.nearby', $hidden))->assertNotFound();
});

it('lets reviewers verify or flag a pin, with audit history', function () {
    $p = Property::factory()->create(['area_id' => area('Gulberg')->id, 'latitude' => 31.5123, 'longitude' => 74.3456]);

    $this->actingAs($this->admin)->patch(route('admin.properties.review', $p), ['action' => 'verify_location']);
    expect($p->fresh()->location_status)->toBe('verified');
    $this->get(route('properties.show', $p))->assertSee('Location checked');

    $p->fresh()->update(['latitude' => 31.5200]); // moving the pin needs a fresh check
    expect($p->fresh()->location_status)->toBe('exact');

    $this->actingAs($this->admin)->patch(route('admin.properties.review', $p), ['action' => 'flag_location'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.properties.review', $p), ['action' => 'flag_location', 'reason' => 'Pin is on a road']);
    expect($p->fresh()->location_status)->toBe('flagged')
        ->and(AuditLog::where('action', 'property.review_verify_location')->exists())->toBeTrue();
});

/* Images ----------------------------------------------------------------- */

it('accepts real photos, makes variants, and keeps order and cover', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['images' => [
        UploadedFile::fake()->image('a.jpg', 1600, 1200), UploadedFile::fake()->image('b.jpg', 1200, 900), UploadedFile::fake()->image('c.png', 800, 600),
    ]]))->assertSessionHasNoErrors();

    $p = Property::sole();
    [$a, $b, $c] = $p->images;
    foreach ([$a, $b, $c] as $image) {
        Storage::disk('public')->assertExists([$image->path, $image->thumb_path]);
        expect($image->path)->toEndWith('.webp');
    }

    $this->actingAs($dealer)->put(route('dealer.properties.update', $p), listingPayload(['cover_image' => $c->id, 'image_order' => [$b->id, $a->id, $c->id]]));
    expect($p->fresh()->images->pluck('id')->all())->toBe([$c->id, $b->id, $a->id])
        ->and($p->fresh()->coverImage->id)->toBe($c->id);
});

it('rejects invalid images and refuses to delete another listing\'s photos', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['images' => [UploadedFile::fake()->createWithContent('fake.jpg', 'nope')]]))
        ->assertSessionHasErrors('images.0');

    $theirs = Property::factory()->create();
    $photo = $theirs->images()->create(['path' => 'properties/x.webp', 'sort_order' => 1]);
    $mine = Property::factory()->forDealer($dealer)->create(['status' => PropertyStatus::Draft]);

    $this->actingAs($dealer)->put(route('dealer.properties.update', $theirs), listingPayload(['remove_images' => [$photo->id]]))->assertForbidden();
    $this->actingAs($dealer)->put(route('dealer.properties.update', $mine), listingPayload(['remove_images' => [$photo->id]]));
    expect($photo->fresh())->not->toBeNull();
});

it('hides moderated photos from the public listing without deleting them', function () {
    $p = Property::factory()->create();
    $keep = $p->images()->create(['path' => 'properties/keep.webp', 'sort_order' => 1]);
    $hide = $p->images()->create(['path' => 'properties/hide.webp', 'sort_order' => 2]);

    $this->actingAs($this->admin)->patch(route('admin.properties.images.moderate', $hide), ['status' => 'removed'])->assertSessionHasErrors('note');
    $this->actingAs($this->admin)->patch(route('admin.properties.images.moderate', $hide), ['status' => 'removed', 'note' => 'Shows a different house']);
    auth()->logout();

    $this->get(route('properties.show', $p))->assertSee('keep.webp')->assertDontSee('hide.webp');
    expect(PropertyImage::find($hide->id)->status)->toBe('removed')
        ->and(AuditLog::where('action', 'property.image_removed')->exists())->toBeTrue();
});

/* Completeness ------------------------------------------------------------ */

it('scores completeness from what the listing provides', function () {
    $bare = Property::factory()->make(['title' => 'House', 'description' => 'Nice.', 'bedrooms' => null, 'bathrooms' => null, 'amenities' => [], 'address' => null]);
    $bareScore = ListingCompleteness::for($bare);

    $full = Property::factory()->create(['title' => 'Ten marla double storey house with basement', 'description' => str_repeat('Well kept family home near the park and main road. ', 12), 'latitude' => 31.51, 'longitude' => 74.34, 'address' => 'Street 5']);
    foreach (range(1, 5) as $i) {
        $full->images()->create(['path' => "p{$i}.webp", 'sort_order' => $i, 'width' => 1600, 'height' => 1200]);
    }
    $fullScore = ListingCompleteness::for($full->fresh());

    expect($bareScore['score'])->toBeLessThan(60)->and($bareScore['ready'])->toBeFalse()
        ->and($bareScore['missing'])->toContain('Add at least 5 photos', 'Place the exact map pin (you can still show only an approximate location)')
        ->and($fullScore['score'])->toBe(100)->and($fullScore['missing'])->toBe([]);
});

/* Listing review workflow ------------------------------------------------- */

it('lets reviewers request changes, suspend and reinstate with reasons', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $p = Property::factory()->forDealer($dealer)->status(PropertyStatus::Pending)->create();

    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $p), ['decision' => 'request_changes'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $p), ['decision' => 'request_changes', 'reason' => 'Add photos of the kitchen']);
    expect($p->fresh()->status)->toBe(PropertyStatus::ChangesRequested);
    Notification::assertSentTo($dealer, ListingReviewed::class, fn ($n) => $n->decision === 'request_changes');

    $this->actingAs($dealer)->get(route('dealer.properties.edit', $p))->assertSee('Add photos of the kitchen');
    $this->actingAs($dealer)->post(route('dealer.properties.submit', $p))->assertRedirect();
    expect($p->fresh()->status)->toBe(PropertyStatus::Pending);

    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $p), ['decision' => 'approve']);
    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $p), ['decision' => 'suspend', 'reason' => 'Reported as sold']);
    expect($p->fresh()->status)->toBe(PropertyStatus::Suspended);
    $this->get(route('properties.index'))->assertDontSee($p->title);

    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $p), ['decision' => 'reinstate']);
    expect($p->fresh()->status)->toBe(PropertyStatus::Published)
        ->and(AuditLog::where('action', 'property.changes_requested')->sole()->properties['reason'])->toBe('Add photos of the kitchen');
});

/* Duplicates and trust signals ------------------------------------------- */

it('records duplicate warnings with reasons and deletes nothing', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $original = Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 40_000_000, 'latitude' => 31.5100, 'longitude' => 74.3400, 'address' => 'House 12, Block C']);
    $copy = Property::factory()->forDealer($dealer)->create(['status' => PropertyStatus::Draft, 'area_id' => area('Johar Town')->id, 'price' => 30_000_000, 'latitude' => 31.5101, 'longitude' => 74.3401, 'address' => 'house 12 block c']);

    $this->actingAs($dealer)->post(route('dealer.properties.submit', $copy))
        ->assertSessionHas('error', fn ($m) => str_contains($m, $original->reference) && str_contains($m, 'map pins within 150 m'));

    $flags = $copy->fresh()->review_flags['duplicates'][0];
    expect($flags['reference'])->toBe($original->reference)
        ->and($flags['reasons'])->toContain('map pins within 150 m', 'same address')
        ->and($copy->fresh()->flagged_at)->not->toBeNull()
        ->and(Property::count())->toBe(2)
        ->and($original->fresh()->status)->toBe(PropertyStatus::Published);

    $this->actingAs($this->admin)->get(route('admin.properties.show', $copy))->assertOk()->assertSee('Duplicate suspected')->assertSee('same address');
});

it('does not flag different properties as duplicates', function () {
    $a = Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 40_000_000]);
    $b = Property::factory()->make(['area_id' => area('Gulberg')->id, 'price' => 80_000_000, 'area_size' => 20, 'title' => 'Completely different plaza']);

    expect(app(DuplicateDetector::class)->candidates($b))->toBeEmpty();
});

it('shows real counts on the trust dashboard', function () {
    Property::factory()->status(PropertyStatus::Pending)->create();
    Property::factory()->create(['latitude' => null]);
    $dealer = trustDealer('individual');
    uploadRequired($dealer);
    $this->actingAs($dealer)->post(route('dealer.verification.submit'));

    $this->actingAs($this->admin)->get(route('admin.trust'))->assertOk()
        ->assertSeeInOrder(['Dealers awaiting verification', '1'])
        ->assertSeeInOrder(['Documents awaiting review', '2'])
        ->assertSeeInOrder(['Listings awaiting review', '1']);
});
