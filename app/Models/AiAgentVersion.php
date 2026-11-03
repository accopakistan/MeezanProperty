<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An immutable snapshot of an agent definition. */
class AiAgentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['version', 'snapshot', 'change_note', 'changed_by', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Agent versions are immutable.'));
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
