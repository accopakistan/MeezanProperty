<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Read-only view of integration status. Secrets are never displayed;
 * only whether each value is present.
 */
class SettingsController extends Controller
{
    public function __invoke(WhatsAppClient $whatsapp): View
    {
        $checks = [
            ['Public contact email', filled(config('meezan.contact.email')), 'MEEZAN_CONTACT_EMAIL'],
            ['Public contact phone', filled(config('meezan.contact.phone')), 'MEEZAN_CONTACT_PHONE'],
            ['Company WhatsApp number', filled(config('meezan.contact.whatsapp')), 'MEEZAN_WHATSAPP_NUMBER'],
            ['WhatsApp Cloud API credentials', $whatsapp->isConfigured(), 'WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN, WHATSAPP_APP_SECRET, WHATSAPP_VERIFY_TOKEN'],
            ['WhatsApp outbound sending', $whatsapp->canSend(), 'WHATSAPP_SEND_ENABLED=true'],
            ['Map tiles', (bool) config('meezan.map.enabled'), 'MEEZAN_MAP_ENABLED, MEEZAN_MAP_TILE_URL'],
            ['Public storage link', file_exists(public_path('storage')), 'php artisan storage:link'],
            ['Outgoing mail', config('mail.default') !== 'log', 'MAIL_MAILER (currently "'.config('mail.default').'")'],
            // Emails are queued: without a running worker they wait in the jobs table.
            ['Queued emails waiting to send', ($waiting = DB::table('jobs')->count()) === 0, 'php artisan queue:work (or composer run dev) sends them', $waiting.' waiting'],
            ['Emails that failed to send', ($failed = DB::table('failed_jobs')->count()) === 0, 'php artisan queue:failed to inspect, queue:retry all to resend', $failed.' failed'],
            ['Email confirmation required', (bool) config('meezan.require_verified_email'), 'MEEZAN_REQUIRE_EMAIL_VERIFICATION=true, once outgoing mail works', config('meezan.require_verified_email') ? 'On' : 'Off'],
        ];

        return view('admin.settings', [
            'checks' => $checks,
            'aiDriver' => config('meezan.ai.driver'),
            'marla' => config('meezan.marla_sqft'),
            'cities' => config('meezan.cities'),
            'lifetime' => config('meezan.listing.lifetime_days'),
            'webhookUrl' => route('webhooks.whatsapp'),
            'diskUrl' => Storage::disk('public')->url(''),
        ]);
    }
}
