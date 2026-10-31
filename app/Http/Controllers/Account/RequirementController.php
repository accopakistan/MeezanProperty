<?php

namespace App\Http\Controllers\Account;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Http\Controllers\Controller;
use App\Models\BuyerRequirement;
use App\Services\Leads\LeadRouter;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatcher;
use App\Support\DealerCoverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequirementController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.requirements.index', [
            'requirements' => $request->user()->requirements()->with('leads.activeAssignments')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('account.requirements.form', [
            'requirement' => new BuyerRequirement(['purpose' => 'sale', 'city' => config('meezan.default_city')]),
            'suggestions' => DealerCoverage::suggestions(),
        ]);
    }

    public function store(Request $request, LeadRouter $router): RedirectResponse
    {
        $data = $this->validated($request);

        $requirement = new BuyerRequirement($data);
        $requirement->user_id = $request->user()->id;
        $requirement->source = 'account';
        $requirement->save();

        $lead = $router->routeRequirement($requirement, $request->boolean('share_with_dealers'));

        $message = match (true) {
            $lead === null => 'Requirement saved. It stays private — we’ll use it to recommend listings.',
            $lead->activeAssignments()->exists() => 'Requirement saved and shared with '.$lead->activeAssignments()->count().' verified dealer(s) in your areas.',
            default => 'Requirement saved. No verified dealer covers these areas yet — our team will review it.',
        };

        return redirect()->route('account.requirements.matches', $requirement)->with('status', $message);
    }

    public function edit(Request $request, BuyerRequirement $requirement): View
    {
        $this->authorizeOwner($request, $requirement);

        return view('account.requirements.form', ['requirement' => $requirement, 'suggestions' => DealerCoverage::suggestions()]);
    }

    public function update(Request $request, BuyerRequirement $requirement, LeadRouter $router): RedirectResponse
    {
        $this->authorizeOwner($request, $requirement);

        $requirement->fill($this->validated($request));
        $requirement->status = $request->boolean('closed') ? 'closed' : 'active';
        $requirement->save();

        if ($requirement->status === 'active') {
            $router->routeRequirement($requirement, $request->boolean('share_with_dealers'));
        }

        return redirect()->route('account.requirements.index')->with('status', 'Requirement updated.');
    }

    public function destroy(Request $request, BuyerRequirement $requirement): RedirectResponse
    {
        $this->authorizeOwner($request, $requirement);

        $requirement->status = 'closed';
        $requirement->save();

        return redirect()->route('account.requirements.index')->with('status', 'Requirement closed. Dealers will no longer receive it.');
    }

    public function matches(Request $request, BuyerRequirement $requirement, PropertyMatcher $matcher): View
    {
        $this->authorizeOwner($request, $requirement);

        return view('account.requirements.matches', [
            'requirement' => $requirement,
            'result' => $matcher->match(MatchCriteria::fromRequirement($requirement)),
        ]);
    }

    protected function authorizeOwner(Request $request, BuyerRequirement $requirement): void
    {
        abort_unless($requirement->user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'purpose' => ['required', Rule::enum(Purpose::class)],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            'area_ids' => ['nullable', 'array', 'max:5'],
            'area_ids.*' => ['integer', Rule::exists('areas', 'id')->where('city', $request->input('city'))->where('is_active', true)],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', Rule::when($request->filled('min_price'), 'gte:min_price')],
            'min_size' => ['nullable', 'numeric', 'min:0'],
            'max_size' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('min_size'), 'gte:min_size')],
            'size_unit' => ['required', Rule::enum(AreaUnit::class)],
            'bedrooms' => ['nullable', 'integer', 'between:1,10'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['max_price.gte' => 'The maximum budget must be at least the minimum.']);

        $unit = AreaUnit::from($data['size_unit']);

        return [
            'title' => $data['title'] ?? null,
            'purpose' => $data['purpose'],
            'type' => $data['type'] ?? null,
            'city' => $data['city'],
            'area_ids' => array_map('intval', $data['area_ids'] ?? []),
            'min_price' => $data['min_price'] ?? null,
            'max_price' => $data['max_price'] ?? null,
            'min_area_sqft' => isset($data['min_size']) ? $unit->toSquareFeet((float) $data['min_size']) : null,
            'max_area_sqft' => isset($data['max_size']) ? $unit->toSquareFeet((float) $data['max_size']) : null,
            'bedrooms' => $data['bedrooms'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
