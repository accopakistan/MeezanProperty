<?php

namespace App\Console\Commands;

use App\Enums\DealerStatus;
use App\Models\DealerProfile;
use App\Services\DealerVerification;
use Illuminate\Console\Command;

/**
 * Moves verified dealers whose verification has passed its expiry date to
 * "expired" (audited, dealer notified). Expiry dates exist only when
 * MEEZAN_VERIFICATION_VALID_MONTHS is set, so by default this does nothing.
 */
class ExpireDealerVerifications extends Command
{
    protected $signature = 'meezan:expire-verifications';

    protected $description = 'Expire dealer verifications that have passed their expiry date';

    public function handle(DealerVerification $verification): int
    {
        $expired = 0;
        DealerProfile::where('verification_status', DealerStatus::Verified)
            ->whereNotNull('verification_expires_at')
            ->where('verification_expires_at', '<=', now())
            ->each(function (DealerProfile $profile) use ($verification, &$expired) {
                $verification->expire($profile);
                $expired++;
            });

        $this->info("Expired {$expired} verification(s).");

        return self::SUCCESS;
    }
}
