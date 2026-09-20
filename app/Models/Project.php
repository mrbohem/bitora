<?php

namespace App\Models;

use App\Services\ContainerManagementService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'git_repo',
        'git_branch',
        'github_auto_update',
        'git_auth_type',
        'git_credentials',
        'app_path',
        'php_version',
        'framework',
        'domain',
        'port',
        'document_root',
        'container_id',
        'docker_network',
        'reverb_enabled',
        'octane_enabled',
        'octane_server',
        'queue_enabled',
        'queue_connection',
        'queue_workers',
        'memory_limit_mb',
        'storage_limit_gb',
        'status',
        'deployment_error',
        'deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'deployed_at' => 'datetime',
            'reverb_enabled' => 'boolean',
            'octane_enabled' => 'boolean',
            'queue_enabled' => 'boolean',
            'github_auto_update' => 'boolean',
            'queue_workers' => 'integer',
            'memory_limit_mb' => 'integer',
            'storage_limit_gb' => 'integer',
        ];
    }

    public function getGitCredentialsAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return decrypt($value);
        } catch (DecryptException|\Throwable $e) {
            return $value;
        }
    }

    public function setGitCredentialsAttribute(?string $value): void
    {
        $this->attributes['git_credentials'] = $value === null || $value === ''
            ? null
            : encrypt($value);
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deploymentConfigs(): HasMany
    {
        return $this->hasMany(DeploymentConfig::class);
    }

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }

    public function supervisorWorkers(): HasMany
    {
        return $this->hasMany(SupervisorWorker::class);
    }

    public function environmentVariables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Get the public URL for this project
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->status !== 'active') {
            return null;
        }

        if ($this->domain) {
            return 'http://'.$this->domain;
        }

        // Get actual Docker-mapped external port
        $containerService = app(ContainerManagementService::class);
        $externalPort = $containerService->getExternalPort($this);

        if ($externalPort) {
            $host = request()->getHost();

            return "http://{$host}:{$externalPort}";
        }

        return null;
    }
}
