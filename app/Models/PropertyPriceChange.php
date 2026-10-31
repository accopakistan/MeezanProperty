<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An asking-price change recorded by Meezan Property when a published listing is edited. */
class PropertyPriceChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['old_price', 'new_price', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'old_price' => 'integer', 'new_price' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
