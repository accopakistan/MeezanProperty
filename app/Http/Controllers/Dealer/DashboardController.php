<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $dealer = $request->user()->load('dealerProfile.areas');

        $counts = $dealer->properties()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $stat = fn (PropertyStatus $s) => (int) ($counts[$s->value] ?? 0);

        $newInquiries = $dealer->receivedInquiries()->where('status', 'new')->count();
        $activeLeads = $dealer->leadAssignments()->where('status', 'active')->count();
        $totalViews = (int) $dealer->properties()->sum('views');

        // Inquiries per day over 30 days, split by the buyer's preferred contact channel.
        $since = now()->subDays(29)->startOfDay();
        $days = collect(range(0, 29))->map(fn ($d) => $since->copy()->addDays($d));
        $rows = $dealer->receivedInquiries()->where('created_at', '>=', $since)->get(['created_at', 'preferred_contact']);
        $channel = fn (string $c) => $days->map(fn ($d) => $rows->where('preferred_contact', $c)->filter(fn ($r) => $r->created_at->isSameDay($d))->count())->all();

        $types = $dealer->properties()->published()->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type');
        $group = fn (string $g) => (int) collect(PropertyType::inGroup($g))->sum(fn ($t) => $types[$t->value] ?? 0);

        return view('dealer.dashboard', [
            'chart' => [
                'labels' => $days->map->format('j M')->all(),
                'series' => [
                    'Calls' => ['color' => '#4b2e83', 'values' => $channel('call')],
                    'WhatsApp' => ['color' => '#16a34a', 'values' => $channel('whatsapp')],
                    'Email' => ['color' => '#d4af37', 'values' => $channel('email')],
                ],
            ],
            'typeShare' => array_values(array_filter([
                ['label' => 'Homes', 'count' => $group('homes'), 'color' => '#4b2e83'],
                ['label' => 'Plots', 'count' => $group('plots'), 'color' => '#d4af37'],
                ['label' => 'Commercial', 'count' => $group('commercial'), 'color' => '#3b82f6'],
            ], fn ($r) => $r['count'] > 0)),
            'totalInquiries' => $dealer->receivedInquiries()->count(),
            'closed' => $dealer->receivedInquiries()->where('status', 'closed')->count(),
            'dealer' => $dealer,
            'profile' => $dealer->dealerProfile,
            'stats' => [
                'published' => $stat(PropertyStatus::Published),
                'pending' => $stat(PropertyStatus::Pending),
                'draft' => $stat(PropertyStatus::Draft),
                'rejected' => $stat(PropertyStatus::Rejected),
                'expired' => $stat(PropertyStatus::Expired),
                'archived' => $stat(PropertyStatus::Archived),
            ],
            'newInquiries' => $newInquiries,
            'activeLeads' => $activeLeads,
            'totalViews' => $totalViews,
            'recentInquiries' => $dealer->receivedInquiries()->with('property:id,title,slug,reference')->latest()->take(5)->get(),
            'recentListings' => $dealer->properties()->with('coverImage', 'area:id,name')->latest('updated_at')->take(5)->get(),
        ]);
    }
}
