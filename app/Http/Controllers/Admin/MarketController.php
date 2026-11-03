<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentMethodology;
use App\Models\MarketImportBatch;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Meezan Property Market Intelligence & Data Centre: overview. */
class MarketController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = fn (string $model) => $model::query()->toBase()->groupBy('status')->pluck(DB::raw('count(*)'), 'status')->map(fn ($n) => (int) $n)->all();
        $byCity = fn (string $model) => $model::verified()->toBase()->groupBy('city')->pluck(DB::raw('count(*)'), 'city')->map(fn ($n) => (int) $n)->all();

        return view('admin.market.index', [
            'observations' => $byStatus(MarketObservation::class),
            'indicators' => $byStatus(MarketIndicator::class),
            'verifiedByCity' => ['observations' => $byCity(MarketObservation::class), 'indicators' => $byCity(MarketIndicator::class)],
            'sources' => MarketSource::count(),
            'methodology' => InvestmentMethodology::active(),
            'published' => InvestmentCalculation::current(),
            'latest' => InvestmentCalculation::latest('id')->first(),
            'imports' => MarketImportBatch::with('creator:id,name')->latest('id')->limit(5)->get(),
        ]);
    }
}
