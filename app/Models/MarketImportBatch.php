<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One CSV import: rows read, imported as drafts, duplicates skipped and row errors. */
class MarketImportBatch extends Model
{
    protected $fillable = ['type', 'file_name'];

    protected function casts(): array
    {
        return ['errors' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
