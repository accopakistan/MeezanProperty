<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AuditLogger;
use App\Services\ListingModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The owner's switch between publishing clean listings at once and reviewing every listing. */
class ListingModerationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in([ListingModeration::REVIEW_FLAGGED, ListingModeration::REVIEW_ALL])]]);
        $before = ListingModeration::mode();
        AppSetting::put(ListingModeration::SETTING, $data['mode'], $request->user()->id);
        AuditLogger::record('listings.moderation_switched', $request->user(), ['from' => $before, 'to' => $data['mode']]);

        return back()->with('status', $data['mode'] === ListingModeration::REVIEW_ALL
            ? 'Every new listing now waits for an admin before it goes live.'
            : 'New listings now go live at once unless the automatic checks flag them.');
    }
}
