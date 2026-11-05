<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = ['direction', 'role', 'external_id', 'type', 'intent', 'body', 'payload', 'extracted', 'delivery_status', 'agent_slug', 'staff_id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'extracted' => 'array',
        ];
    }

    /** "user" for what the buyer wrote, "assistant" for replies (older rows only have direction). */
    public function speaker(): string
    {
        return $this->role ?? ($this->direction === 'inbound' ? 'user' : 'assistant');
    }

    /** Who wrote an outgoing message: "staff" or "ai". Null for what the contact wrote. */
    public function author(): ?string
    {
        if ($this->speaker() !== 'assistant') {
            return null;
        }

        return $this->staff_id || $this->intent === 'staff_reply' ? 'staff' : 'ai';
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
