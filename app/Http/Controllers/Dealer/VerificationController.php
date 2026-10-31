<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\DealerStatus;
use App\Http\Controllers\Controller;
use App\Models\DealerDocument;
use App\Models\User;
use App\Notifications\DealerDocumentsSubmitted;
use App\Services\AuditLogger;
use App\Services\DealerVerification;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Verification documents (reference: Dealer Platform mockup, "Document
 * upload"). Files go to the private disk; only the dealer and admins can
 * open them. Uploading after a rejection puts the profile back in review.
 */
class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->dealerProfile()->firstOrFail()->load('documents');

        return view('dealer.verification', [
            'profile' => $profile,
            'documents' => $profile->documents,
            'checklist' => $profile->documentChecklist(),
            'headline' => $profile->verificationHeadline(),
            'canRemove' => ! in_array($profile->verification_status, [DealerStatus::Verified, DealerStatus::Submitted, DealerStatus::UnderReview], true),
        ]);
    }

    /** Send the uploaded documents for review. */
    public function submit(Request $request, DealerVerification $verification): RedirectResponse
    {
        try {
            $verification->submit($request->user()->dealerProfile()->firstOrFail(), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Submitted for review. We will notify you of the outcome.');
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->dealerProfile()->firstOrFail();
        abort_if($profile->verification_status === DealerStatus::Suspended, 403, 'Suspended accounts cannot upload documents.');

        $remaining = DealerDocument::MAX_PER_PROFILE - $profile->documents()->current()->count();
        if ($remaining < 1) {
            return back()->with('error', 'You already have the maximum of '.DealerDocument::MAX_PER_PROFILE.' documents. Remove one before adding another.');
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DealerDocument::TYPES))],
            'files' => ['required', 'array', 'min:1', 'max:'.min(4, $remaining)],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('meezan.verification.max_document_kb'), $this->intact(...)],
        ], [
            'files.required' => 'Choose at least one file.',
            'files.max' => 'You can add up to '.min(4, $remaining).' file(s) at a time here.',
            'files.*.mimes' => 'Upload a photo (JPG, PNG, WebP) or a PDF.',
            'files.*.max' => 'Each file must be smaller than '.(config('meezan.verification.max_document_kb') / 1024).' MB.',
        ]);
        $resubmitted = false;
        DB::transaction(function () use ($request, $profile, $data, &$resubmitted) {
            foreach ($data['files'] as $file) {
                $document = new DealerDocument([
                    'type' => $data['type'],
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
                ]);
                $document->dealer_profile_id = $profile->id;
                $document->uploaded_by = $request->user()->id;
                // Random name, private disk; MIME from the file's content, not the browser.
                $document->path = $file->store('dealer-documents/'.$profile->id, DealerDocument::DISK);
                $document->mime = $file->getMimeType();
                $document->size = $file->getSize();
                $document->sha256 = hash_file('sha256', $file->getRealPath());
                $document->save();
            }

            // A new upload replaces rejected documents of the same type; they stay on file as history.
            $profile->documents()->where('type', $data['type'])->where('status', 'rejected')->update(['status' => 'superseded']);

            if (in_array($profile->verification_status, [DealerStatus::Rejected, DealerStatus::Expired], true)) {
                $profile->verification_status = DealerStatus::Pending;
                $profile->save();
                $resubmitted = true;
            }

            AuditLogger::record('dealer.documents_uploaded', $profile, ['type' => $data['type'], 'count' => count($data['files']), 'resubmitted' => $resubmitted]);
        });

        Notification::send(User::activeAdmins(), new DealerDocumentsSubmitted($profile, DealerDocument::TYPES[$data['type']], count($data['files'])));

        return back()->with('status', $resubmitted
            ? 'Documents uploaded. Your application is back in review.'
            : 'Documents uploaded. Our team will review them.');
    }

    /**
     * Images must actually decode (a renamed or truncated file fails) and stay
     * within the size limits. The extension and browser type are not trusted.
     */
    protected function intact(string $attribute, mixed $file, Closure $fail): void
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return;
        }
        $path = $file->getRealPath();
        $mime = (string) $file->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($path);
            $min = (int) config('meezan.verification.min_image_px');
            $max = (int) config('meezan.verification.max_image_px', 10000);
            if (! $info) {
                $fail('This image could not be read. Take the photo again or upload a PDF.');
            } elseif ($min && min($info[0], $info[1]) < $min) {
                $fail("This image is too small to read (at least {$min} px on each side).");
            } elseif (max($info[0], $info[1]) > $max) {
                $fail("This image is too large (at most {$max} px on each side).");
            }
        }
        // PDFs: the "mimes" rule already identifies the type from the file's
        // content (the %PDF- signature), not from its name.
    }

    public function show(Request $request, DealerDocument $document): StreamedResponse
    {
        abort_unless($document->dealer_profile_id === $request->user()->dealerProfile?->id, 404);

        return $document->streamResponse();
    }

    public function destroy(Request $request, DealerDocument $document): RedirectResponse
    {
        $profile = $request->user()->dealerProfile()->firstOrFail();
        abort_unless($document->dealer_profile_id === $profile?->id, 404);

        // Evidence behind an approved verification is kept.
        if ($profile->verification_status === DealerStatus::Verified) {
            return back()->with('error', 'Documents cannot be removed after your agency is verified. Contact support if one is out of date.');
        }
        // Reviewed documents are part of the verification record; upload a replacement instead.
        if ($document->status !== 'pending' || $profile->verification_status->awaitsReview()) {
            return back()->with('error', 'This document is part of a review and cannot be removed. Upload a replacement instead.');
        }

        $document->deleteFile();
        $document->delete();
        AuditLogger::record('dealer.document_removed', $profile, ['type' => $document->type]);

        return back()->with('status', 'Document removed.');
    }
}
