<?php

namespace App\Console\Commands;

use App\Models\OutboundMessage;
use App\Services\AuditLogger;
use App\Services\WhatsApp\OutboundMessenger;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;

/**
 * The owner's live test: shows the WhatsApp setup (never a secret) and, only
 * when run without --check and confirmed, sends Meta's sample template to the
 * verified test recipient. Nothing else ever triggers it.
 */
class WhatsAppTestSend extends Command
{
    protected $signature = 'meezan:whatsapp-test-send {--check : Show the setup only; send nothing} {--yes : Skip the confirmation question}';

    protected $description = 'Check the WhatsApp setup, and send Meta\'s hello_world template to the verified test recipient';

    public function handle(WhatsAppClient $client, OutboundMessenger $messenger): int
    {
        $d = $client->details();
        $this->table(['Setting', 'Value'], [
            ['Environment', $d['mode']],
            ['Mock mode', $client->mockStatus()['on'] ? 'ON (nothing is sent)' : 'off'],
            ['Messages right now', WhatsAppClient::DELIVERY[$client->deliveryMode()]],
            ['Customer-facing number (not the API sender)', $d['customer_number'] ?: 'not set'],
            ['API test sender', $d['test_sender'] ?: 'missing'],
            ['Sending enabled', $d['enabled'] ? 'yes' : 'no'],
            ['Phone number ID', $d['phone_number_id'] ?: 'missing'],
            ['Graph API version', $d['graph_version']],
            ['Test recipient', $d['test_recipient_last4'] ? '•••• '.$d['test_recipient_last4'] : 'missing'],
            ['Access token configured', $d['token_set'] ? 'yes' : 'no'],
            ['App secret configured', $d['secret_set'] ? 'yes' : 'no'],
            ['Verify token configured', $d['verify_set'] ? 'yes' : 'no'],
        ]);
        foreach ([...$client->configurationWarnings(), ...$client->formatProblems()] as $warning) {
            $this->warn($warning);
        }

        if ($this->option('check')) {
            return self::SUCCESS;
        }
        if (! $client->isMetaTestMode()) {
            $this->error('This command only works in test mode (WHATSAPP_MODE=test).');

            return self::FAILURE;
        }
        if ($client->mockStatus()['on']) {
            $this->error('The mock provider is active, so nothing would reach a phone. Set WHATSAPP_MOCK_MODE=false in .env, turn off Mock mode in Admin → WhatsApp Control, and run php artisan config:clear.');

            return self::FAILURE;
        }
        if (! $client->metaTestStatus()['ready']) {
            $this->error('Sending is not ready. Fix the warnings above, then run php artisan config:clear.');

            return self::FAILURE;
        }

        $template = config('meezan.whatsapp.test_template.name');
        if (! $this->option('yes') && ! $this->confirm("Send the {$template} template to the number ending {$d['test_recipient_last4']}?")) {
            $this->line('Nothing was sent.');

            return self::SUCCESS;
        }

        $outbound = $messenger->ownerTest();
        AuditLogger::record('whatsapp.test_sent', $outbound, ['outbound_id' => $outbound->id, 'status' => $outbound->status, 'provider' => $outbound->provider]);

        if ($outbound->status === 'sent') {
            $this->info("Meta accepted message #{$outbound->id}. Message id: {$outbound->provider_message_id}");
            $this->line('Check the phone. Delivered and read updates need the public webhook, so they will not appear here yet.');

            return self::SUCCESS;
        }
        $this->error('Not sent: '.($outbound->status === 'blocked' ? (OutboundMessage::BLOCK_REASONS[$outbound->blocked_reason] ?? $outbound->blocked_reason) : $outbound->last_error));

        return self::FAILURE;
    }
}
