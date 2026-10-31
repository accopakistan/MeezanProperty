<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = ['direction', 'role', 'external_id', 'type', 'intent', 'body', 'payload', 'extracted', 'delivery_status'];

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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
