<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PropertyImage extends Model
{
    protected $fillable = ['path', 'thumb_path', 'width', 'height', 'sort_order'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Card-sized version; photos uploaded before processing existed use the full image. */
    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path ?? $this->path);
    }

    /** Remove the stored files (full image and thumbnail). */
    public function deleteFiles(): void
    {
        Storage::disk('public')->delete(array_filter([$this->path, $this->thumb_path]));
    }
}
