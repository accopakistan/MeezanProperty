<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A support case escalated to staff (by the Customer Support agent or staff). */
class SupportTicket extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (SupportTicket $t) => $t->reference ??= 'SUP-'.strtoupper(Str::random(8)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
