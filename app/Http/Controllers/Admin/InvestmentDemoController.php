<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\InvestmentCalculation;
use App\Services\AuditLogger;
use App\Services\Investment\DemoDataManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Market Data Centre → Demo Data: the illustrative dataset that fills the
 * Investment Rankings before real market data exists. Demo and real data are
 * counted separately; removal deletes demo records only.
 */
class InvestmentDemoController extends Controller
{
    public function index(DemoDataManager $demo): View
    {
        return view('admin.market.demo', ['stats' => $demo->stats(), 'envDefault' => (bool) config('meezan.investment.demo_mode'),
            'override' => AppSetting::get('investment.demo_mode')]);
    }

    public function run(Request $request, DemoDataManager $demo): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['seed', 'refresh', 'recalculate'])]]);
        @set_time_limit(600);

        $message = '';
        if ($data['action'] !== 'recalculate') {
            $c = $demo->seed(refresh: $data['action'] === 'refresh');
            $message = "{$c['projects']} new demo projects, {$c['observations']} price/rent records and {$c['indicators']} indicators written".($c['skipped_real'] ? ", {$c['skipped_real']} skipped (a real project has the same name)" : '').'. ';
        }
        $calc = $demo->recalculate($request->user());

        return back()->with($calc->status === 'published' ? 'status' : 'error', $message.($calc->status === 'published'
            ? "Demo rankings calculated and published (calculation #{$calc->id})."
            : 'Demo rankings could not be calculated: '.$calc->error));
    }

    public function mode(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['on', 'off', 'default'])]]);
        $data['mode'] === 'default'
            ? AppSetting::where('key', 'investment.demo_mode')->delete()
            : AppSetting::put('investment.demo_mode', $data['mode'] === 'on', $request->user()->id);
        cache()->forget('app-setting:investment.demo_mode');
        AuditLogger::record('investment.demo_mode', null, ['mode' => $data['mode']]);

        return back()->with('status', InvestmentCalculation::demoMode()
            ? 'Demo mode is on: public dashboards show the demo rankings with the demo notice.'
            : 'Demo mode is off: public dashboards show real verified data only.');
    }

    public function remove(Request $request, DemoDataManager $demo): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:REMOVE DEMO DATA']], ['confirm.in' => 'Type REMOVE DEMO DATA to confirm.']);
        $r = $demo->remove($request->user());

        return back()->with('status', "Removed {$r['projects']} demo projects, {$r['observations']} price/rent records, {$r['indicators']} indicators, {$r['sources']} demo sources and {$r['calculations']} demo calculations. Real data was not touched."
            .($r['keptProjects'] || $r['keptSources'] ? " Kept {$r['keptProjects']} demo projects and {$r['keptSources']} demo sources that real records refer to." : ''));
    }
}
