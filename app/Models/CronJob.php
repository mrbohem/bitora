<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    protected $fillable = [
        'project_id',
        'schedule',
        'command',
        'enabled',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
