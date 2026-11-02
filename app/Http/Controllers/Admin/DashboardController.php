<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\DealerProfile;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Billing\BillingReport;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(WhatsAppClient $whatsapp): View
    {
        $since = now()->subDays(30);
        $recent = fn (string $model) => $model::where('created_at', '>=', $since)->count();

        $listingStatus = Property::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $leadStatus = Lead::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        // Real recorded payments only (test payments excluded); "—" until there are any.
        $paid = app(BillingReport::class)->paidRevenue(now()->startOfMonth(), now()->endOfMonth());
        $gateways = collect(app(GatewayManager::class)->available())->reject(fn ($g) => $g->isTest());

        $stats = [
            ['Total Properties', Property::count(), 'home', 'bg-brand-500', $recent(Property::class).' added in 30 days', route('admin.properties.index', ['status' => ''])],
            ['Total Users', User::count(), 'users', 'bg-gold-500', $recent(User::class).' joined in 30 days', route('admin.users.index')],
            ['Verified Dealers', DealerProfile::where('verification_status', DealerStatus::Verified)->count(), 'badge-check', 'bg-brand-500', User::where('role', Role::Dealer)->count().' dealer accounts', route('admin.dealers.index', ['status' => 'verified'])],
            ['Total Inquiries', Inquiry::count(), 'message', 'bg-brand-600', $recent(Inquiry::class).' in 30 days', route('admin.leads.index')],
            ['Published Listings', (int) ($listingStatus[PropertyStatus::Published->value] ?? 0), 'check', 'bg-emerald-500', ((int) ($listingStatus[PropertyStatus::Pending->value] ?? 0)).' awaiting review', route('admin.properties.index', ['status' => 'published'])],
            ['Paid This Month', $paid->isEmpty() ? '—' : $paid->map(fn ($v, $c) => $c.' '.number_format($v))->implode(' · '), 'credit-card', 'bg-gold-500', 'Recorded payments, net of refunds', route('admin.billing')],
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'growth' => $this->growth(),
            'byArea' => Area::withCount(['properties as total' => fn ($q) => $q->published()])->get(['id', 'name'])
                ->where('total', '>', 0)->sortByDesc('total')->take(7)->values(),
            'byType' => $this->typeShare(),
            'recentListings' => Property::with(['area:id,name', 'coverImage'])->latest()->take(5)->get(),
            'recentInquiries' => Inquiry::with('property:id,title,slug,area_id,deleted_at', 'property.area:id,name')->latest()->take(5)->get(),
            'topDealers' => User::where('role', Role::Dealer)
                ->with('dealerProfile:id,user_id,agency_name')
                ->withCount(['properties as listings_count' => fn ($q) => $q->published(), 'receivedInquiries as inquiries_count', 'receivedInquiries as handled_count' => fn ($q) => $q->whereIn('status', ['contacted', 'closed'])])
                ->orderByDesc('listings_count')->take(5)->get(),
            'pending' => [
                ['Property Approvals', (int) ($listingStatus[PropertyStatus::Pending->value] ?? 0), 'home', 'bg-gold-100 text-gold-700', route('admin.properties.index', ['status' => 'pending'])],
                ['Dealer Verifications', DealerProfile::whereIn('verification_status', DealerStatus::reviewQueue())->count(), 'users', 'bg-brand-100 text-brand-700', route('admin.dealers.index', ['status' => 'pending'])],
                ['Leads Needing Review', (int) ($leadStatus[LeadStatus::Unrouted->value] ?? 0), 'alert', 'bg-red-100 text-red-700', route('admin.leads.index', ['status' => 'unrouted'])],
                ['New Inquiries', Inquiry::where('status', 'new')->count(), 'message', 'bg-sky-100 text-sky-700', route('admin.leads.index', ['status' => 'new'])],
                ['Bank Transfers to Review', Payment::where('status', PaymentStatus::AwaitingReview)->count(), 'credit-card', 'bg-gold-100 text-gold-700', route('admin.payments', ['status' => 'awaiting_review'])],
            ],
            'registrations' => DealerProfile::with('user:id,name')->latest()->take(5)->get(),
            'system' => [
                ['Website', true, 'Online'],
                ['Database', true, 'Connected'],
                ['AI Matching', true, config('meezan.ai.driver') === 'rules' ? 'Built-in rules' : config('meezan.ai.driver')],
                ['WhatsApp API', $whatsapp->isConfigured(), $whatsapp->isConfigured() ? ($whatsapp->isLive() ? 'Live' : 'Receiving only') : 'Not configured'],
                ['Email Service', config('mail.default') !== 'log', config('mail.default') === 'log' ? 'Log only' : 'Configured'],
                ['Payment Gateway', $gateways->isNotEmpty(), $gateways->isNotEmpty() ? $gateways->map->label()->implode(', ') : 'Not configured'],
            ],
            'activity' => AuditLog::with('user:id,name')->latest('created_at')->take(6)->get(),
        ]);
    }

    /**
     * Monthly totals for the last 12 months, plus SVG polyline points.
     *
     * @return array{labels: list<string>, series: array<string, array{color: string, values: list<int>, points: string}>, max: int}
     */
    protected function growth(): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $start = $months->first();

        $count = function (string $model) use ($months, $start) {
            $byMonth = $model::where('created_at', '>=', $start)->get(['created_at'])
                ->countBy(fn ($row) => Carbon::parse($row->created_at)->format('Y-m'));

            return $months->map(fn ($m) => (int) ($byMonth[$m->format('Y-m')] ?? 0))->all();
        };

        $series = [
            'Properties' => ['color' => '#6a3fb0', 'values' => $count(Property::class)],
            'Users' => ['color' => '#d4af37', 'values' => $count(User::class)],
            'Inquiries' => ['color' => '#3b82f6', 'values' => $count(Inquiry::class)],
        ];
        $max = max(1, ...array_merge(...array_map(fn ($s) => $s['values'], array_values($series))));

        foreach ($series as &$s) {
            $s['points'] = collect($s['values'])->map(fn ($v, $i) => round($i * (560 / 11), 1).','.round(180 - ($v / $max) * 160, 1))->implode(' ');
        }

        return ['labels' => $months->map->format('M')->all(), 'series' => $series, 'max' => $max];
    }

    /**
     * Share of published listings by headline type, for the donut chart.
     *
     * @return list<array{label: string, count: int, color: string}>
     */
    protected function typeShare(): array
    {
        $counts = Property::published()->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type');
        $sum = fn (array $types) => (int) collect($types)->sum(fn ($t) => $counts[$t->value] ?? 0);

        return array_values(array_filter([
            ['label' => 'Houses', 'count' => $sum([PropertyType::House, PropertyType::Farmhouse]), 'color' => '#4b2e83'],
            ['label' => 'Plots', 'count' => $sum(PropertyType::inGroup('plots')), 'color' => '#d4af37'],
            ['label' => 'Apartments & portions', 'count' => $sum([PropertyType::Apartment, PropertyType::Penthouse, PropertyType::UpperPortion, PropertyType::LowerPortion]), 'color' => '#3b82f6'],
            ['label' => 'Commercial', 'count' => $sum(PropertyType::inGroup('commercial')), 'color' => '#8c66c8'],
        ], fn ($row) => $row['count'] > 0));
    }
}
