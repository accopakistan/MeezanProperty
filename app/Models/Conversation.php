<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const STATUSES = [
        'active' => 'Active',
        'qualified' => 'Requirement saved',
        'lead' => 'Lead created',
        'escalated' => 'Needs a person',
        'failed' => 'Not understood',
        'closed' => 'Closed',
    ];

    protected $fillable = ['channel', 'external_contact', 'session_key', 'contact_name', 'mode', 'status', 'state', 'locale', 'last_message_at', 'escalated_at', 'escalation_reason'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'escalated_at' => 'datetime',
            'state' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->oldest('id');
    }

    /** The most recent messages, oldest first (history shown to people is bounded). */
    public function recentMessages(int $limit = 50)
    {
        return $this->messages()->getQuery()->reorder()->latest('id')->take($limit)->get()->reverse()->values();
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(BuyerRequirement::class, 'buyer_requirement_id');
    }

    /** Contact shown in admin lists: only the last digits of a phone number. */
    public function maskedContact(): string
    {
        if ($this->channel !== 'whatsapp') {
            return $this->user?->name ?? 'Website visitor';
        }

        return '•••• '.substr($this->external_contact, -4);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
