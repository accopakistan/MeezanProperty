<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\OutboundMessage;
use Illuminate\Console\Command;

/**
 * Keeps the outbound queue honest:
 * - messages still "queued" after 10 minutes (e.g. the worker was down)
 *   are dispatched again; sending is row-locked, so this never sends twice;
 * - messages stuck in "sending" for 15 minutes (the worker stopped
 *   mid-request) are marked failed with "outcome unknown", for staff to
 *   check and retry. They are not re-sent automatically: the first attempt
 *   may have reached the phone.
 */
class WhatsAppSweep extends Command
{
    protected $signature = 'meezan:whatsapp-sweep';

    protected $description = 'Re-dispatch stale queued WhatsApp messages and flag stuck sends';

    public function handle(): int
    {
        $requeued = 0;
        OutboundMessage::where('status', 'queued')->where('updated_at', '<', now()->subMinutes(10))
            ->where('attempts', '<', (int) config('meezan.whatsapp.max_send_attempts'))
            ->pluck('id')->each(function ($id) use (&$requeued) {
                SendWhatsAppMessage::dispatch($id);
                $requeued++;
            });

        $stuck = OutboundMessage::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => 'Outcome unknown: sending was interrupted. Check before retrying.', 'updated_at' => now()]);

        $this->info("Re-dispatched {$requeued}; marked {$stuck} interrupted send(s) as failed.");

        return self::SUCCESS;
    }
}
