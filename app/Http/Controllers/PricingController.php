<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public package comparison, from the active packages in the database.
 */
class PricingController extends Controller
{
    public function __invoke(Request $request, SubscriptionManager $subscriptions): View
    {
        $user = $request->user();

        return view('pricing', [
            'packages' => Package::offered()->ordered()->get(),
            'current' => $user?->isDealer() ? $subscriptions->current($user) : null,
        ]);
    }
}
