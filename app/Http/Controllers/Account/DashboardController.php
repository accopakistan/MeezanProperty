<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatcher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PropertyMatcher $matcher): View
    {
        $user = $request->user();
        $requirement = $user->requirements()->where('status', 'active')->latest()->first();

        return view('account.dashboard', [
            'user' => $user,
            'savedCount' => $user->savedProperties()->count(),
            'inquiryCount' => $user->inquiries()->count(),
            'requirementCount' => $user->requirements()->where('status', 'active')->count(),
            'recentSaved' => $user->savedProperties()->forCards()->orderByPivot('created_at', 'desc')->take(3)->get(),
            'recentInquiries' => $user->inquiries()->with('property:id,title,slug,reference,deleted_at')->latest()->take(4)->get(),
            'requirement' => $requirement,
            'recommendations' => $requirement ? $matcher->match(MatchCriteria::fromRequirement($requirement)) : null,
        ]);
    }
}
