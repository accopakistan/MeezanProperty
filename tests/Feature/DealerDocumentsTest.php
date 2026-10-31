<?php

use App\Enums\DealerStatus;
use App\Models\AuditLog;
use App\Models\DealerDocument;
use App\Models\User;
use App\Notifications\DealerDocumentsSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();
});

function uploadDocuments(User $dealer, array $files, string $type = 'cnic_front')
{
    return test()->actingAs($dealer)->post(route('dealer.documents.store'), ['type' => $type, 'files' => $files]);
}

it('stores verification documents privately and tells the admins', function () {
    $admin = User::factory()->admin()->create();
    $dealer = User::factory()->dealer()->create();

    uploadDocuments($dealer, [UploadedFile::fake()->image('cnic.jpg', 1200, 800), UploadedFile::fake()->create('licence.pdf', 300, 'application/pdf')], 'agency_registration')
        ->assertSessionHas('status');

    $documents = DealerDocument::all();
    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('type')->unique()->all())->toBe(['agency_registration'])
        ->and($documents->pluck('mime')->sort()->values()->all())->toBe(['application/pdf', 'image/jpeg']);
    foreach ($documents as $document) {
        Storage::disk('local')->assertExists($document->path);
        expect($document->path)->toStartWith('dealer-documents/'.$dealer->dealerProfile->id.'/')
            ->and($document->path)->not->toContain('cnic');
    }
    expect(Storage::disk('public')->allFiles())->toBe([]);
    Notification::assertSentTo($admin, DealerDocumentsSubmitted::class, fn ($n) => $n->count === 2);
    expect(AuditLog::where('action', 'dealer.documents_uploaded')->exists())->toBeTrue();
});

it('rejects unsafe or oversized files and unknown document types', function () {
    $dealer = User::factory()->dealer()->create();

    uploadDocuments($dealer, [UploadedFile::fake()->createWithContent('cnic.html', '<script>alert(1)</script>')])->assertSessionHasErrors('files.0');
    uploadDocuments($dealer, [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('files.0');
    uploadDocuments($dealer, [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('files.0');
    uploadDocuments($dealer, [UploadedFile::fake()->image('a.jpg')], 'passport_scan')->assertSessionHasErrors('type');
    uploadDocuments($dealer, [])->assertSessionHasErrors('files');

    expect(DealerDocument::count())->toBe(0);
});

it('caps the number of documents per dealer', function () {
    $dealer = User::factory()->dealer()->create();
    foreach (range(1, 3) as $i) {
        uploadDocuments($dealer, array_map(fn () => UploadedFile::fake()->image('d.jpg'), range(1, $i < 3 ? 4 : 2)), 'other');
    }
    expect(DealerDocument::count())->toBe(10);

    uploadDocuments($dealer, [UploadedFile::fake()->image('one-more.jpg')])->assertSessionHas('error');
    expect(DealerDocument::count())->toBe(10);
});

it('puts a rejected application back into review when new documents arrive', function () {
    $dealer = User::factory()->dealer(DealerStatus::Rejected)->create();

    uploadDocuments($dealer, [UploadedFile::fake()->image('cnic.jpg')]);

    expect($dealer->dealerProfile->fresh()->verification_status)->toBe(DealerStatus::Pending);
});

it('shows a document only to its dealer and to admins, with private headers', function () {
    $dealer = User::factory()->dealer()->create();
    uploadDocuments($dealer, [UploadedFile::fake()->image('cnic.jpg')]);
    $document = DealerDocument::sole();

    $response = $this->actingAs($dealer)->get(route('dealer.documents.show', $document))->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs(User::factory()->dealer()->create())->get(route('dealer.documents.show', $document))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('dealer.documents.show', $document))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.dealers.documents.show', $document))->assertForbidden();
    auth()->logout();
    $this->get(route('dealer.documents.show', $document))->assertRedirect(route('login'));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.dealers.documents.show', $document))->assertOk();
    expect(AuditLog::where('action', 'dealer.document_viewed')->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('lets dealers remove documents only until they are verified', function () {
    $dealer = User::factory()->dealer()->create();
    uploadDocuments($dealer, [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]);
    [$first, $second] = DealerDocument::orderBy('id')->get()->all();

    $this->actingAs($dealer)->delete(route('dealer.documents.destroy', $first))->assertSessionHas('status');
    Storage::disk('local')->assertMissing($first->path);
    expect(DealerDocument::count())->toBe(1);

    $dealer->dealerProfile->forceFill(['verification_status' => DealerStatus::Verified])->save();
    $this->actingAs($dealer)->delete(route('dealer.documents.destroy', $second))->assertSessionHas('error');
    Storage::disk('local')->assertExists($second->path);

    $this->actingAs(User::factory()->dealer()->create())->delete(route('dealer.documents.destroy', $second))->assertNotFound();
});

it('lists documents on the verification page and for admins reviewing the dealer', function () {
    $dealer = User::factory()->dealer()->create();
    uploadDocuments($dealer, [UploadedFile::fake()->image('front.jpg')]);

    $this->actingAs($dealer)->get(route('dealer.verification'))
        ->assertOk()->assertSee('CNIC (front)')->assertSee('front.jpg')->assertSee('visible only to you and Meezan Property staff');
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dealers.show', $dealer->dealerProfile))
        ->assertOk()->assertSee('Verification documents')->assertSee('front.jpg');
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dealers.index'))->assertSee('1 document');
});
