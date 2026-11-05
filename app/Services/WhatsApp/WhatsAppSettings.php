<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\AuditLogger;
use InvalidArgumentException;

/**
 * Admin switches for the WhatsApp channel (stored in app_settings):
 *
 * - automation: any automatic outgoing message (assistant replies, dealer
 *   alerts). Off: nothing is sent automatically; staff replies still work.
 * - auto_reply: the assistant answering buyers. Off: incoming messages are
 *   stored and marked "held" for staff; nothing is discarded.
 * - test_mode: every message goes to the mock provider and is flagged as a
 *   test, whatever driver is configured (nothing reaches a real phone).
 */
class WhatsAppSettings
{
    public const SWITCHES = [
        'automation' => ['whatsapp.automation_enabled', true, 'WhatsApp automation'],
        'auto_reply' => ['whatsapp.auto_reply_enabled', true, 'AI auto-reply'],
        'test_mode' => ['whatsapp.test_mode', false, 'Mock mode'],
    ];

    public function automationEnabled(): bool
    {
        return $this->get('automation');
    }

    public function autoReplyEnabled(): bool
    {
        return $this->get('auto_reply');
    }

    public function testMode(): bool
    {
        return $this->get('test_mode');
    }

    public function get(string $switch): bool
    {
        [$key, $default] = self::SWITCHES[$switch] ?? throw new InvalidArgumentException("Unknown switch [{$switch}].");

        return (bool) AppSetting::get($key, $default);
    }

    public function set(string $switch, bool $on, User $by): void
    {
        $before = $this->get($switch);
        if ($before === $on) {
            return;
        }
        AppSetting::put(self::SWITCHES[$switch][0], $on, $by->id);
        AuditLogger::record('whatsapp.setting_changed', $by, ['setting' => $switch, 'from' => $before, 'to' => $on], $by->id);
    }
}
