<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An outgoing WhatsApp message and what happened to it. Only
 * App\Services\WhatsApp\OutboundMessenger and the webhook status updates
 * change it; rows are kept (never deleted) as the delivery record.
 */
class OutboundMessage extends Model
{
    public const STATUSES = ['queued', 'sending', 'sent', 'delivered', 'read', 'failed', 'blocked'];

    /** Delivery progress only moves forward. */
    public const PROGRESS = ['sent' => 1, 'delivered' => 2, 'read' => 3];

    public const BLOCK_REASONS = [
        'automation_disabled' => 'WhatsApp automation is switched off',
        'opted_out' => 'The contact opted out',
        'no_consent' => 'No WhatsApp consent recorded for this contact',
        'outside_service_window' => 'More than 24 hours since the contact last wrote, and no approved template',
        'rate_limited' => 'Too many messages to this contact in the last hour',
        'promotional_disabled' => 'Promotional messages are not enabled',
        'sending_disabled' => 'Sending is not enabled for the WhatsApp provider',
        'provider_unavailable' => 'The selected WhatsApp provider is not available here',
    ];

    protected $fillable = [];

    protected $hidden = ['recipient'];

    protected $attributes = ['is_test' => false, 'attempts' => 0, 'type' => 'text', 'channel' => 'whatsapp'];

    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted',
            'payload' => 'array',
            'is_test' => 'boolean',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Outbound messages are kept as the delivery record.'));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function conversationMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedRecipient(): string
    {
        return '•••• '.$this->recipient_last4;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'sent', 'delivered', 'read' => 'success',
            'queued', 'sending' => 'info',
            'blocked' => 'warning',
            'failed' => 'danger',
            default => 'neutral',
        };
    }

    /** A failed send can be tried again by staff (blocked ones need their cause fixed first). */
    public function isRetryable(): bool
    {
        return $this->status === 'failed';
    }
}
