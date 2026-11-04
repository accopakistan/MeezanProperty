<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentEvidenceSubmission;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner-submitted evidence. An owner or manager states a fact with its
 * documents; it counts only after staff check the documents once. Staff
 * verification turns it into a verified evidence or legal record whose
 * source is the submitter (owner statement) or the named issuer (auditor,
 * engineer, authority), and whose source URL is the submission's staff
 * page. A yield is computed from the stated rent and value, never typed.
 */
class EvidenceSubmissions
{
    public const UTILITIES = ['electricity', 'gas', 'water', 'sewerage'];

    public const VALUE_BASES = ['independent_valuation' => 'Independent valuation report', 'purchase_price' => 'Documented purchase price'];

    /** Projects evidence can be submitted for: real, published, in the five categories. */
    public static function accepts(Project $project): bool
    {
        return ! $project->is_demo && $project->is_published && isset(IndexEngine::categoryMap()[$project->category]);
    }

    /** Factors and context evidence an owner can document for this project's category. */
    public static function factorsFor(Project $project): array
    {
        $category = IndexEngine::categoryMap()[$project->category] ?? null;
        $labels = config("investment_index.categories.$category.factors", []);
        $factors = [];
        foreach (InvestmentEvidenceSubmission::OWNER_FACTORS as $f) {
            if (isset($labels[$f]) || in_array($f, ['delivery_status', 'operational_status', 'development_progress'], true)) {
                $factors[$f] = $labels[$f]['label'] ?? ucfirst(str_replace('_', ' ', $f));
            }
        }

        return $factors;
    }

    /** @return array<string, string> level => label for a level factor or context kind */
    public static function levels(string $factor): array
    {
        $levels = config("investment_index.factors.$factor.levels") ?? config("investment_index.context_evidence.$factor.levels") ?? [];

        return collect($levels)->mapWithKeys(fn ($v, $k) => [$k => is_string($v) ? $v : ucfirst(str_replace('_', ' ', $k))])->all();
    }

    /** @throws ValidationException */
    public function store(Project $project, User $user, array $input, array $files): InvestmentEvidenceSubmission
    {
        if (! static::accepts($project)) {
            throw ValidationException::withMessages(['project' => 'Evidence can only be submitted for published developments in the Investment Index.']);
        }
        $data = validator($input + ['files' => $files], $this->rules($project, $input))->validate();
        $kind = $data['kind'];

        $submission = new InvestmentEvidenceSubmission([
            'organisation' => $data['organisation'], 'submitter_role' => $data['submitter_role'], 'kind' => $kind,
            'observed_on' => $data['observed_on'], 'notes' => $data['notes'] ?? null,
            'document_type' => $kind === 'legal' ? 'third_party' : $data['document_type'],
            'issuer' => $kind === 'legal' ? $data['authority'] : ($data['issuer'] ?? null),
        ]);
        if ($kind === 'legal') {
            $submission->fill(['authority' => $data['authority'], 'record_type' => $data['record_type'], 'legal_status' => $data['legal_status'],
                'reference' => $data['reference'], 'issued_on' => $data['issued_on'] ?? null]);
        } else {
            $factor = $data['factor'];
            $submission->factor = $factor;
            $submission->subject = $factor === 'utilities' ? $data['subject'] : null;
            match (true) {
                in_array($factor, ['development_progress', 'occupancy'], true) => $submission->value_numeric = (float) $data['percent'],
                $factor === 'rental_income' => [$submission->value_numeric = round($data['annual_rent'] / $data['property_value'] * 100, 2),
                    $submission->figures = ['annual_rent' => (int) $data['annual_rent'], 'property_value' => (int) $data['property_value'], 'value_basis' => $data['value_basis']]],
                default => $submission->value_level = $data['level'],
            };
        }
        $submission->project_id = $project->id;
        $submission->user_id = $user->id;
        $submission->status = 'pending'; // explicit, so the returned model is complete without a reload

        return DB::transaction(function () use ($submission, $data, $project, $user) {
            $stored = [];
            foreach ($data['files'] as $file) {
                /** @var UploadedFile $file */
                $stored[] = [
                    // Random name, private disk; type from the file's content, not the browser.
                    'path' => $file->store('investment-evidence/'.$project->id, InvestmentEvidenceSubmission::DISK),
                    'name' => mb_substr($file->getClientOriginalName(), 0, 190), 'mime' => $file->getMimeType(),
                    'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
                ];
            }
            $submission->files = $stored;
            $submission->save();
            AuditLogger::record('investment_index.evidence_submitted', $submission, ['project' => $project->id, 'claim' => $submission->claim()], $user->id);

            return $submission;
        });
    }

    protected function rules(Project $project, array $input): array
    {
        $factors = array_keys(static::factorsFor($project));
        $factor = $input['factor'] ?? null;
        $rules = [
            'kind' => ['required', Rule::in(['evidence', 'legal'])],
            'organisation' => ['required', 'string', 'max:160'],
            'submitter_role' => ['required', 'string', 'max:120'],
            'observed_on' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(5)->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'declaration' => ['accepted'],
            'files' => ['required', 'array', 'min:1', 'max:'.InvestmentEvidenceSubmission::MAX_FILES],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.config('meezan.verification.max_document_kb')],
        ];
        if (($input['kind'] ?? null) === 'legal') {
            return $rules + [
                'authority' => ['required', 'string', 'max:80'],
                'record_type' => ['required', Rule::in(['approval', 'regularisation'])],
                'legal_status' => ['required', Rule::in(['approved', 'partially_approved', 'pending'])],
                'reference' => ['required', 'string', 'max:160'],
                'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            ];
        }
        $rules += [
            'factor' => ['required', Rule::in($factors)],
            'document_type' => ['required', Rule::in(array_keys(InvestmentEvidenceSubmission::DOCUMENT_TYPES)),
                in_array($factor, InvestmentEvidenceSubmission::THIRD_PARTY_ONLY, true) ? Rule::in(['third_party']) : 'string'],
            'issuer' => [($input['document_type'] ?? null) === 'third_party' ? 'required' : 'nullable', 'string', 'max:160'],
        ];

        return $rules + match (true) {
            in_array($factor, ['development_progress', 'occupancy'], true) => ['percent' => ['required', 'numeric', 'between:0,100']],
            $factor === 'rental_income' => ['annual_rent' => ['required', 'integer', 'min:1'], 'property_value' => ['required', 'integer', 'min:1'],
                'value_basis' => ['required', Rule::in(array_keys(self::VALUE_BASES))]],
            $factor === 'utilities' => ['subject' => ['required', Rule::in(self::UTILITIES)], 'level' => ['required', Rule::in(array_keys(static::levels('utilities')))]],
            $factor !== null => ['level' => ['required', Rule::in(array_keys(static::levels($factor)))]],
            default => [],
        };
    }

    /**
     * Staff confirmed the documents support the claim: record it as verified evidence.
     *
     * @throws DomainException when the record cannot be verified (wrong source type, demo project...)
     */
    public function verify(InvestmentEvidenceSubmission $s, User $staff, ?string $note = null): void
    {
        if ($s->status !== 'pending') {
            throw new DomainException('Only a submission awaiting a check can be verified.');
        }
        DB::transaction(function () use ($s, $staff, $note) {
            $source = $this->sourceFor($s, $staff);
            $common = ['project_id' => $s->project_id, 'source_id' => $source->id,
                'source_url' => route('admin.investment-index.submissions.show', $s), 'collected_at' => $s->created_at,
                'content_hash' => hash('sha256', implode('|', array_column($s->files, 'sha256'))),
                'excerpt' => mb_substr("Submitted by {$s->organisation} ({$s->submitter_role}); ".count($s->files).' document(s) checked by Meezan staff.'.($note ? " {$note}" : ''), 0, 2000),
                'collector' => 'owner_submission', 'verification_method' => 'staff_document_check',
                'meta' => ['submission_id' => $s->id, 'document_type' => $s->document_type, 'issuer' => $s->issuer, 'figures' => $s->figures]];

            if ($s->kind === 'legal') {
                $record = new InvestmentLegalRecord($common + ['authority' => $s->authority, 'record_type' => $s->record_type, 'status' => $s->legal_status,
                    'reference' => $s->reference, 'issued_on' => $s->issued_on, 'title' => $s->project->name]);
            } else {
                $record = new InvestmentEvidence($common + ['factor' => $s->factor, 'subject' => $s->subject, 'value_numeric' => $s->value_numeric,
                    'value_level' => $s->value_level, 'unit' => in_array($s->factor, ['development_progress', 'occupancy', 'rental_income'], true) ? '%' : null,
                    'observed_on' => $s->observed_on]);
            }
            $record->forceFill(['verification_status' => 'verified', 'verified_by' => $staff->id])->save();

            $s->forceFill(['status' => 'verified', 'reviewed_by' => $staff->id, 'reviewed_at' => now(), 'review_note' => $note,
                $s->kind === 'legal' ? 'legal_record_id' : 'evidence_id' => $record->id])->save();
            AuditLogger::record('investment_index.evidence_verified', $s, ['record' => $record->id, 'claim' => $s->claim()], $staff->id);
        });
    }

    public function reject(InvestmentEvidenceSubmission $s, User $staff, string $reason): void
    {
        if ($s->status !== 'pending') {
            throw new DomainException('Only a submission awaiting a check can be rejected.');
        }
        $s->forceFill(['status' => 'rejected', 'reviewed_by' => $staff->id, 'reviewed_at' => now(), 'review_note' => $reason])->save();
        AuditLogger::record('investment_index.evidence_rejected', $s, ['reason' => $reason], $staff->id);
    }

    /** The traceable source: the authority for an approval, the named issuer for a third-party document, otherwise the submitting organisation. */
    protected function sourceFor(InvestmentEvidenceSubmission $s, User $staff): MarketSource
    {
        [$type, $publisher, $name] = match (true) {
            $s->kind === 'legal' => ['official_record', $s->authority, "{$s->authority} — approval documents (staff-verified)"],
            $s->document_type === 'third_party' => ['audited_document', $s->issuer, "{$s->issuer} — certified documents"],
            default => ['owner_document', $s->organisation, "{$s->organisation} — owner documents"],
        };
        $source = MarketSource::firstOrNew(['name' => mb_substr($name, 0, 250), 'type' => $type]);
        if (! $source->exists) {
            $source->fill(['publisher' => $publisher, 'reliability' => $type === 'owner_document' ? 'medium' : 'high', 'notes' => 'Created when Meezan staff verified owner-submitted documents.']);
            $source->forceFill(['access_basis' => 'owner_submission', 'authorized_at' => now(), 'authorized_by' => $staff->id]);
        }
        $source->is_active = true;
        $source->save();

        return $source;
    }
}
