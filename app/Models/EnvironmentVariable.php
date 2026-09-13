<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentVariable extends Model
{
    protected $fillable = [
        'project_id',
        'key',
        'value',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
