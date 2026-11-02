<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Assistant\AssistantReply;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

/**
 * Links a WhatsApp conversation to a Meezan Property account. Phone numbers
 * on accounts are not verified, so a conversation is never linked by
 * number: the signed-in buyer gets a one-time code on the website and
 * sends "LINK 123456" from WhatsApp, proving they control both.
 */
class AccountLinker
{
    public const TTL_MINUTES = 15;

    public function generate(User $user): string
    {
        if ($old = Cache::pull('wa-link-user:'.$user->id)) {
            Cache::forget('wa-link:'.$old);
        }
        $code = (string) random_int(100000, 999999);
        Cache::put('wa-link:'.$code, $user->id, now()->addMinutes(self::TTL_MINUTES));
        Cache::put('wa-link-user:'.$user->id, $code, now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    public function pendingCode(User $user): ?string
    {
        return Cache::get('wa-link-user:'.$user->id);
    }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/^\s*link\s+\d{6}\s*$/i', $text);
    }

    public function link(Conversation $conversation, string $text): AssistantReply
    {
        preg_match('/(\d{6})/', $text, $m);
        $userId = Cache::pull('wa-link:'.$m[1]);
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return new AssistantReply('That code is not valid or has expired. Create a new one under Account → Settings on the Meezan Property website.', 'account_link');
        }

        Cache::forget('wa-link-user:'.$user->id);
        $conversation->forceFill(['user_id' => $user->id])->save();
        if ($conversation->requirement && ! $conversation->requirement->user_id) {
            $conversation->requirement->forceFill(['user_id' => $user->id])->save();
        }
        AuditLogger::record('conversation.linked_to_account', $conversation, ['channel' => 'whatsapp'], $user->id);

        return new AssistantReply('This WhatsApp chat is now linked to your Meezan Property account ('.$user->name.'). Requirements you save here will appear under My requirements.', 'account_link');
    }
}
