<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Billing is not integrated yet. This page shows the dealer's real usage
 * and states plainly that paid plans are not available.
 */
class SubscriptionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $dealer = $request->user();

        return view('dealer.subscription', [
            'activeListings' => $dealer->properties()->whereIn('status', [PropertyStatus::Published, PropertyStatus::Pending])->count(),
            'photoCount' => PropertyImage::whereIn('property_id', $dealer->properties()->select('id'))->count(),
            'leadCount' => $dealer->leadAssignments()->count(),
        ]);
    }
}
