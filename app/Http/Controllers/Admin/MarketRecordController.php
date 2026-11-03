<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Services\AuditLogger;
use App\Services\Investment\MarketRecords;
use App\Support\Cities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Price/rent observations and market indicators: list, manual entry,
 * correction (drafts and rejected only), review workflow and CSV import.
 * {type} is "observations" or "indicators".
 */
class MarketRecordController extends Controller
{
    public function index(Request $request, string $type): View
    {
        $model = $this->model($type);
        $status = array_key_exists((string) $request->query('status'), MarketObservation::STATUSES) ? $request->query('status') : null;
        $city = in_array($request->query('city'), Cities::names(), true) ? $request->query('city') : null;

        $records = $model::query()->with(['source:id,name,type', 'area:id,name', 'project:id,name', 'creator:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($type === 'observations' && in_array($request->query('kind'), ['sale', 'rent'], true), fn ($q) => $q->where('kind', $request->query('kind')))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.market.records.index', ['type' => $type, 'records' => $records, 'filters' => compact('status', 'city') + ['kind' => $request->query('kind')]]);
    }

    public function create(string $type): View
    {
        return $this->form($type, new ($this->model($type)));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $data = $type === 'observations' ? MarketRecords::validateObservation($request->all()) : MarketRecords::validateIndicator($request->all());
        $model = $this->model($type);
        $record = new $model($data);
        if ($model::where('fingerprint', $model::fingerprintFor($record->getAttributes()))->where('status', '!=', 'rejected')->exists()) {
            return back()->withInput()->withErrors(['observed_on' => 'An identical record already exists (same place, values, date and source).']);
        }
        $record->forceFill(['status' => 'draft', 'created_by' => $request->user()->id])->save();
        AuditLogger::record('market.'.$this->noun($type).'_created', $record);

        return redirect()->route('admin.market.records.edit', [$type, $record->id])->with('status', 'Saved as a draft. Submit it for review when it is complete.');
    }

    public function edit(string $type, int $id): View
    {
        return $this->form($type, $this->find($type, $id));
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->find($type, $id);
        abort_unless($record->isEditable(), 409, 'Only drafts and rejected records can be corrected. Reopen a verified record first.');
        $data = $type === 'observations' ? MarketRecords::validateObservation($request->all()) : MarketRecords::validateIndicator($request->all());
        $before = $record->only(array_keys($data));
        $record->fill($data)->save();
        AuditLogger::record('market.'.$this->noun($type).'_corrected', $record, ['before' => array_diff_assoc(array_map('strval', $before), array_map('strval', $record->only(array_keys($before))))]);

        return back()->with('status', 'Record updated.');
    }

    /** submit · review · verify · reject · reopen */
    public function transition(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->find($type, $id);
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(MarketObservation::TRANSITIONS))],
            'reason' => ['nullable', 'required_if:action,reject', 'string', 'max:500'],
        ]);
        if (! $record->transition($data['action'], $request->user(), $data['reason'] ?? null)) {
            return back()->withErrors(['action' => 'That step is not available for a '.strtolower($record->statusLabel()).' record.']);
        }
        AuditLogger::record('market.'.$this->noun($type).'_'.$data['action'], $record, array_filter(['reason' => $data['reason'] ?? null]));

        return back()->with('status', 'Record '.strtolower($record->statusLabel()).'.');
    }

    public function importForm(string $type): View
    {
        $this->model($type);

        return view('admin.market.records.import', ['type' => $type, 'columns' => $type === 'observations' ? MarketRecords::OBSERVATION_COLUMNS : MarketRecords::INDICATOR_COLUMNS]);
    }

    public function import(Request $request, string $type): View
    {
        $this->model($type);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:2048']]);
        $batch = MarketRecords::import($type, $request->file('file'), $request->user());

        return view('admin.market.records.import', [
            'type' => $type, 'batch' => $batch,
            'columns' => $type === 'observations' ? MarketRecords::OBSERVATION_COLUMNS : MarketRecords::INDICATOR_COLUMNS,
        ]);
    }

    /** A header-only CSV template (no sample figures). */
    public function template(string $type): StreamedResponse
    {
        $this->model($type);
        $columns = $type === 'observations' ? MarketRecords::OBSERVATION_COLUMNS : MarketRecords::INDICATOR_COLUMNS;

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        }, "meezan-market-{$type}-template.csv", ['Content-Type' => 'text/csv']);
    }

    protected function form(string $type, Model $record): View
    {
        return view('admin.market.records.form', [
            'type' => $type,
            'record' => $record,
            'sources' => MarketSource::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'history' => $record->exists ? AuditLog::with('user:id,name')->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)->latest('id')->limit(20)->get() : collect(),
        ]);
    }

    /** @return class-string<MarketObservation|MarketIndicator> */
    protected function model(string $type): string
    {
        return match ($type) {
            'observations' => MarketObservation::class,
            'indicators' => MarketIndicator::class,
            default => abort(404),
        };
    }

    protected function find(string $type, int $id): Model
    {
        return $this->model($type)::with(['source', 'area:id,name,slug,city', 'project:id,name,slug,city'])->findOrFail($id);
    }

    protected function noun(string $type): string
    {
        return $type === 'observations' ? 'observation' : 'indicator';
    }
}
