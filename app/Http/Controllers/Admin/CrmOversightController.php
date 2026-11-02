<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Enums\PipelineStage;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use App\Models\User;
use App\Services\Crm\CrmStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Lead oversight for staff. A marketplace lead can be shared with up to
 * three dealers who each keep their own pipeline, so stage figures count
 * dealer assignments, and "won" is reported as "leads at least one dealer
 * marked won" — never as the lead being globally won. Dealer figures are
 * operational insights from recorded CRM activity, not a ranking.
 */
class CrmOversightController extends Controller
{
    public function __invoke(Request $request, CrmStats $stats): View
    {
        $period = (int) ($request->validate(['days' => ['nullable', 'integer', 'in:7,30,90']])['days'] ?? 30);
        $from = now()->subDays($period);

        $city = DB::table('leads')
            ->leftJoin('areas', 'areas.id', '=', 'leads.area_id')
            ->leftJoin('buyer_requirements', 'buyer_requirements.id', '=', 'leads.buyer_requirement_id')
            ->leftJoin('properties', 'properties.id', '=', 'leads.property_id')
            ->selectRaw('coalesce(areas.city, buyer_requirements.city, properties.city) as city, count(*) as total')
            ->groupBy(DB::raw('coalesce(areas.city, buyer_requirements.city, properties.city)'))
            ->orderByDesc('total')->pluck('total', 'city');

        $dealerIds = LeadAssignment::distinct()->pluck('dealer_id');

        return view('admin.crm', [
            'period' => $period,
            'totals' => [
                'leads' => Lead::count(),
                'assigned' => Lead::whereHas('activeAssignments')->count(),
                'unassigned' => Lead::whereDoesntHave('activeAssignments')->count(),
                'held' => Lead::where('status', LeadStatus::Unrouted)->whereNotNull('hold_reason')->count(),
                'invalidated' => Lead::whereNotNull('invalidated_at')->count(),
                'anyWon' => Lead::whereHas('assignments', fn ($q) => $q->where('pipeline_stage', 'won'))->count(),
                'dealerReportedInvalid' => LeadAssignment::active()->whereIn('pipeline_stage', ['invalid', 'duplicate'])->count(),
                'overdue' => LeadFollowUp::overdue()->whereHas('assignment', fn ($q) => $q->active())->count(),
                'stale' => LeadAssignment::active()->whereNotNull('stale_flagged_at')->count(),
            ],
            'byCity' => $city,
            'stages' => LeadAssignment::active()->groupBy('pipeline_stage')->selectRaw('pipeline_stage, count(*) as total')->pluck('total', 'pipeline_stage'),
            'dealers' => User::whereIn('id', $dealerIds)->where('role', Role::Dealer)->with('dealerProfile:id,user_id,agency_name,city')->orderBy('name')->get(['id', 'name']),
            'performance' => $stats->performance($dealerIds, $from),
            'stageCases' => PipelineStage::cases(),
        ]);
    }
}
