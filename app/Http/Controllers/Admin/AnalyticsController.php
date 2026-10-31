<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(): View
    {
        $byType = Property::published()->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type')
            ->mapWithKeys(fn ($total, $type) => [PropertyType::from($type)->label() => $total])
            ->sortDesc();

        $byArea = Area::withCount(['properties as total' => fn ($q) => $q->published()])
            ->get(['id', 'name'])
            ->filter(fn ($a) => $a->total > 0)
            ->sortByDesc('total')
            ->take(10)
            ->mapWithKeys(fn ($a) => [$a->name => $a->total]);

        $inquiriesByArea = Inquiry::join('properties', 'properties.id', '=', 'inquiries.property_id')
            ->join('areas', 'areas.id', '=', 'properties.area_id')
            ->select('areas.name', DB::raw('count(*) as total'))
            ->groupBy('areas.name')
            ->orderByDesc('total')
            ->take(10)
            ->pluck('total', 'name');

        $since = now()->subDays(29)->startOfDay();
        $signups = User::where('created_at', '>=', $since)->get(['created_at'])->countBy(fn ($u) => $u->created_at->toDateString());
        $inquiries = Inquiry::where('created_at', '>=', $since)->get(['created_at'])->countBy(fn ($i) => $i->created_at->toDateString());
        $days = collect(range(0, 29))->map(fn ($d) => $since->copy()->addDays($d));

        return view('admin.analytics', [
            'byType' => $byType,
            'byArea' => $byArea,
            'inquiriesByArea' => $inquiriesByArea,
            'days' => $days,
            'signups' => $signups,
            'inquiries' => $inquiries,
            'topViewed' => Property::published()->with('area:id,name')->orderByDesc('views')->take(5)->get(['id', 'title', 'slug', 'views', 'area_id', 'reference']),
            'avgPrice' => Property::published()->where('purpose', 'sale')->whereIn('type', ['house'])->avg('price'),
            'publishedCount' => Property::where('status', PropertyStatus::Published)->count(),
        ]);
    }
}
