<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Another name a source uses for a project, so the same development is never added twice. */
class ProjectAlias extends Model
{
    protected $fillable = ['project_id', 'alias', 'normalized', 'source_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
