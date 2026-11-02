<?php

namespace App\Jobs;

use App\Services\WhatsApp\OutboundMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one queued outbound WhatsApp message. Safe to run twice: the
 * messenger only sends a message that is still "queued" (row-locked).
 * Temporary failures are released back to the queue with a growing delay,
 * up to meezan.whatsapp.max_send_attempts.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $outboundMessageId)
    {
        $this->onQueue(config('meezan.whatsapp.queue'));
    }

    public function handle(OutboundMessenger $messenger): void
    {
        if ($messenger->deliver($this->outboundMessageId) === 'retry') {
            $this->release([30, 120, 600][min($this->attempts() - 1, 2)]);
        }
    }
}
