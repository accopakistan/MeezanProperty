<?php

namespace App\Http\Controllers;

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Models\Area;
use App\Models\DealerProfile;
use App\Support\LocationResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Find a dealer": public directory of agencies. Only dealers who are
 * verified or have at least one published listing appear; private owners do
 * not. Only public profile fields are shown.
 */
class DealerDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:140'],
            'city' => ['nullable', 'string', Rule::in(config('meezan.cities'))],
            'verified' => ['nullable', 'boolean'],
        ]);
        $area = isset($filters['area']) ? Area::where('slug', $filters['area'])->first() : null;

        $dealers = $this->listed()
            ->with('areas:id,name,slug', 'cities:id,name')
            ->withCount(['properties as listings_count' => fn ($q) => $q->published()])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('agency_name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%'))
            ->when($area, fn ($q) => $q->whereHas('areas', fn ($a) => $a->whereIn('areas.id', LocationResolver::related([$area->id]))))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where(fn ($c) => $c->whereHas('cities', fn ($x) => $x->where('name', $city))
                ->orWhere(fn ($home) => $home->whereDoesntHave('cities')->where('city', $city))))
            ->when($filters['verified'] ?? false, fn ($q) => $q->where('verification_status', DealerStatus::Verified))
            ->orderByRaw("case when verification_status = 'verified' then 0 else 1 end")
            ->orderByDesc('listings_count')
            ->paginate(12)
            ->withQueryString();

        return view('dealers.index', compact('dealers', 'filters', 'area'));
    }

    public function show(DealerProfile $dealer): View
    {
        abort_unless($this->listed()->whereKey($dealer->id)->exists(), 404);

        $dealer->load('areas:id,name,slug', 'user:id,created_at');

        return view('dealers.show', [
            'dealer' => $dealer,
            'listings' => $dealer->properties()->published()->forCards()->latest('published_at')->paginate(12),
            'counts' => [
                'sale' => $dealer->properties()->published()->where('purpose', 'sale')->count(),
                'rent' => $dealer->properties()->published()->where('purpose', 'rent')->count(),
            ],
        ]);
    }

    protected function listed(): Builder
    {
        return DealerProfile::query()
            ->where('business_type', '!=', 'owner')
            ->where('is_demo', false)
            ->whereNotIn('verification_status', [DealerStatus::Rejected, DealerStatus::Suspended])
            ->whereHas('user', fn ($u) => $u->where('status', 'active'))
            ->where(fn ($q) => $q->where('verification_status', DealerStatus::Verified)
                ->orWhereHas('properties', fn ($p) => $p->where('status', PropertyStatus::Published)));
    }
}
