<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorWorker extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'command',
        'processes',
        'status',
        'error_message',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'processes' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
