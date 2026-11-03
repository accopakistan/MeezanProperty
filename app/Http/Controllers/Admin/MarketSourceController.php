<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketSource;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Data-source registry: name, type, URL, licence, reliability, coverage, last checked. */
class MarketSourceController extends Controller
{
    public function index(): View
    {
        return view('admin.market.sources.index', [
            'sources' => MarketSource::withCount(['observations', 'indicators'])->orderBy('name')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.market.sources.form', ['source' => new MarketSource(['reliability' => 'medium', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $source = new MarketSource($this->validated($request));
        $source->created_by = $request->user()->id;
        $source->save();
        AuditLogger::record('market.source_created', $source, ['type' => $source->type, 'reliability' => $source->reliability]);

        return redirect()->route('admin.market.sources.index')->with('status', 'Source added.');
    }

    public function edit(MarketSource $source): View
    {
        return view('admin.market.sources.form', ['source' => $source]);
    }

    public function update(Request $request, MarketSource $source): RedirectResponse
    {
        $before = $source->only(['type', 'reliability', 'is_active']);
        $source->update($this->validated($request));
        AuditLogger::record('market.source_updated', $source, ['before' => $before, 'after' => $source->only(array_keys($before))]);

        return redirect()->route('admin.market.sources.index')->with('status', 'Source updated.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(config('meezan.investment.source_types')))],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'license' => ['nullable', 'string', 'max:255'],
            'reliability' => ['required', Rule::in(array_keys(MarketSource::RELIABILITY))],
            'coverage' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:160'],
            'last_checked_on' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
