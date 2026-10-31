<?php

namespace App\Models;

use App\Enums\DealerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealerProfile extends Model
{
    protected $fillable = [
        'agency_name',
        'slug',
        'city',
        'office_address',
        'registration_number',
        'years_in_business',
        'bio',
        'public_phone',
        'whatsapp_number',
        'accepts_leads',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => DealerStatus::class,
            'verified_at' => 'datetime',
            'verification_submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'accepts_leads' => 'boolean',
            'lead_capacity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'user_id', 'user_id');
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class);
    }

    /** Cities the dealer serves; leads are only routed within these. */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class);
    }

    /** Names of the served cities, falling back to the profile's home city. */
    public function servedCityNames(): array
    {
        $names = $this->relationLoaded('cities') ? $this->cities->pluck('name')->all() : $this->cities()->pluck('name')->all();

        return $names ?: array_filter([$this->city]);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DealerDocument::class)->latest();
    }

    /**
     * Required documents and where each stands: missing, pending, approved
     * or rejected (the newest current upload of that type decides).
     *
     * @return array<string, array{label: string, state: string, document: ?DealerDocument}>
     */
    public function documentChecklist(): array
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();
        $current = $documents->where('status', '!=', 'superseded');

        $list = [];
        foreach (DealerDocument::requiredFor($this->business_type) as $type) {
            $ofType = $current->where('type', $type);
            $best = $ofType->firstWhere('status', 'approved') ?? $ofType->firstWhere('status', 'pending') ?? $ofType->first();
            $list[$type] = ['label' => DealerDocument::TYPES[$type] ?? $type, 'state' => $best?->status ?? 'missing', 'document' => $best];
        }

        return $list;
    }

    public function requiredDocumentsUploaded(): bool
    {
        return collect($this->documentChecklist())->every(fn ($row) => in_array($row['state'], ['pending', 'approved'], true));
    }

    public function requiredDocumentsApproved(): bool
    {
        return collect($this->documentChecklist())->every(fn ($row) => $row['state'] === 'approved');
    }

    /**
     * What the dealer sees as their status, with the next step.
     *
     * @return array{title: string, body: string, tone: string}
     */
    public function verificationHeadline(): array
    {
        $hasDocuments = $this->documents()->exists();

        return match ($this->verification_status) {
            DealerStatus::Verified => ['title' => 'Verified', 'tone' => 'success', 'body' => 'Your agency is verified'.($this->verification_expires_at ? ' until '.$this->verification_expires_at->format('j M Y') : '').'. The verified badge appears on your profile and listings.'],
            DealerStatus::Submitted => ['title' => 'Submitted for review', 'tone' => 'warning', 'body' => 'Our team reviews applications in the order they arrive. You will be notified of the outcome.'],
            DealerStatus::UnderReview => ['title' => 'Under review', 'tone' => 'warning', 'body' => 'A reviewer is checking your documents now.'],
            DealerStatus::Rejected => ['title' => 'Verification rejected', 'tone' => 'danger', 'body' => 'Read the reason below, upload corrected documents and submit again.'],
            DealerStatus::Suspended => ['title' => 'Account suspended', 'tone' => 'danger', 'body' => 'Contact Meezan Property support to resolve this.'],
            DealerStatus::Expired => ['title' => 'Verification expired', 'tone' => 'muted', 'body' => 'Upload current documents and submit again to restore the verified badge.'],
            default => match (true) {
                $this->changes_requested_at !== null => ['title' => 'Additional documents required', 'tone' => 'warning', 'body' => 'Replace the documents marked below, then submit again.'],
                ! $hasDocuments => ['title' => 'Verification not started', 'tone' => 'neutral', 'body' => 'Upload the required documents, then submit them for review.'],
                ! $this->requiredDocumentsUploaded() => ['title' => 'Documents required', 'tone' => 'warning', 'body' => 'Some required documents are still missing.'],
                default => ['title' => 'Ready to submit', 'tone' => 'info', 'body' => 'All required documents are uploaded. Submit them for review.'],
            },
        };
    }

    public function isVerified(): bool
    {
        return $this->verification_status === DealerStatus::Verified;
    }
}
