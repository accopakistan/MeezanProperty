<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Services\DealerPerformance;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceController extends Controller
{
    public function index(Request $request, DealerPerformance $performance): View
    {
        return view('dealer.performance', [
            'report' => $performance->for($request->user(), (int) $request->query('days', 30)),
        ]);
    }

    /** Per-listing figures for the selected period, as CSV. */
    public function export(Request $request, DealerPerformance $performance): StreamedResponse
    {
        $report = $performance->for($request->user(), (int) $request->query('days', 30));
        $filename = 'meezan-listing-performance-'.now()->format('Y-m-d').'-'.$report['days'].'d.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Title', 'Status', 'Asking price (PKR)', 'Views (period)', 'Views (all time)', 'Saves (period)', 'Inquiries (period)', 'Inquiries per 100 views', 'Days live', 'Price per marla vs area median (%)', 'Suggested improvements']);

            foreach ($report['rows'] as $row) {
                $p = $row['property'];
                fputcsv($out, [
                    $p->reference, $p->title, $p->status->label(), $p->price,
                    $row['views'], $row['viewsAllTime'], $row['saves'], $row['inquiries'],
                    $row['conversion'] ?? '', $row['daysLive'] ?? '', $row['vsArea'] ?? '',
                    implode(' | ', $row['issues']),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
