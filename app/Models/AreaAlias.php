<?php

namespace App\Models;

use App\Support\LocationName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaAlias extends Model
{
    protected $fillable = ['area_id', 'city_id', 'alias', 'normalized'];

    protected static function booted(): void
    {
        static::saving(function (AreaAlias $alias) {
            $alias->normalized = LocationName::normalize($alias->alias);
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
