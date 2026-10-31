<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\DealerDocument;
use App\Models\DealerProfile;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\ListingCompleteness;
use Illuminate\View\View;

/**
 * Marketplace trust overview. Every figure is a live database count with a
 * link to the matching list; nothing is estimated.
 */
class TrustDashboardController extends Controller
{
    public function __invoke(): View
    {
        $live = [PropertyStatus::Published, PropertyStatus::Pending];

        return view('admin.trust', [
            'dealers' => [
                ['Dealers awaiting verification', DealerProfile::whereIn('verification_status', DealerStatus::reviewQueue())->count(), route('admin.dealers.index', ['status' => DealerStatus::Submitted->value])],
                ['Documents awaiting review', DealerDocument::where('status', 'pending')->count(), route('admin.dealers.index')],
                ['Verified dealers', DealerProfile::where('verification_status', DealerStatus::Verified)->count(), route('admin.dealers.index', ['status' => DealerStatus::Verified->value])],
                ['Suspended dealers', DealerProfile::where('verification_status', DealerStatus::Suspended)->count(), route('admin.dealers.index', ['status' => DealerStatus::Suspended->value])],
            ],
            'listings' => [
                ['Listings awaiting review', Property::where('status', PropertyStatus::Pending)->count(), route('admin.properties.index', ['status' => PropertyStatus::Pending->value])],
                ['Changes requested', Property::where('status', PropertyStatus::ChangesRequested)->count(), route('admin.properties.index', ['status' => PropertyStatus::ChangesRequested->value])],
                ['Suspended listings', Property::where('status', PropertyStatus::Suspended)->count(), route('admin.properties.index', ['status' => PropertyStatus::Suspended->value])],
                ['Marked as requiring review', Property::whereNotNull('flagged_at')->whereIn('status', $live)->count(), route('admin.properties.index', ['flag' => 'review'])],
            ],
            'quality' => [
                ['Suspected duplicates', Property::whereIn('status', $live)->where('review_flags', 'like', '%"duplicates"%')->count(), route('admin.properties.duplicates')],
                ['Live listings without an exact pin', Property::where('status', PropertyStatus::Published)->whereNull('latitude')->count(), route('admin.properties.index', ['flag' => 'no_pin'])],
                ['Locations flagged', Property::whereIn('status', $live)->where('location_status', 'flagged')->count(), route('admin.properties.index', ['flag' => 'location'])],
                ['Low completeness (under '.ListingCompleteness::READY_AT.'%)', Property::whereIn('status', $live)->where('completeness_score', '<', ListingCompleteness::READY_AT)->count(), route('admin.properties.index', ['flag' => 'incomplete'])],
                ['Photos flagged or hidden', PropertyImage::whereIn('status', ['flagged', 'rejected', 'removed'])->count(), null],
            ],
        ]);
    }
}
