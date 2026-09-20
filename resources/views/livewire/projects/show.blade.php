<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.index') }}" wire:navigate class="text-sm" style="color: var(--color-on-surface-variant);">
                    <flux:icon.chevron-left class="size-4 inline" />
                    {{ __('Projects') }}
                </a>
            </div>
            <h1 class="text-3xl font-bold font-display mt-2" style="color: var(--color-on-surface);">{{ $project->name }}</h1>
            <p class="mt-1 flex items-center gap-2" style="color: var(--color-on-surface-variant);">
                {{ $project->domain ?? $project->slug }}
                <flux:badge :color="$project->status === 'active' ? 'lime' : ($project->status === 'failed' ? 'red' : 'zinc')" size="sm">{{ $deploymentStatus }}</flux:badge>
            </p>
            @if($project->url)
                <a href="{{ $project->url }}" target="_blank"
                   class="mt-2 inline-flex items-center gap-2 text-sm font-medium hover:underline"
                   style="color: var(--color-primary);">
                    <flux:icon.arrow-top-right-on-square class="size-4" />
                    Visit Site: {{ $project->url }}
                </a>
            @endif
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-2">
            @if($project->git_repo)
                <flux:button wire:click="syncFromGitHub" size="sm" variant="primary">
                    {{ __('Sync from GitHub') }}
                </flux:button>
                <flux:button wire:click="toggleGitHubAutoUpdate" size="sm" :variant="$project->github_auto_update ? 'filled' : 'ghost'">
                    {{ $project->github_auto_update ? __('Disable Auto Update') : __('Enable Auto Update') }}
                </flux:button>
            @endif
            @if($deploymentStatus === 'active' && $this->containerStatus['running'])
                <flux:button wire:click="stopProject" size="sm">
                    {{ __('Stop') }}
                </flux:button>
                <flux:button wire:click="restartProject" size="sm">
                    {{ __('Restart') }}
                </flux:button>
            @elseif($deploymentStatus === 'stopped')
                <flux:button wire:click="startProject" variant="primary" size="sm">
                    {{ __('Start Project') }}
                </flux:button>
            @endif
            <flux:button wire:click="$set('showDeleteModal', true)" variant="danger" size="sm" icon="trash">
                {{ __('Delete Project') }}
            </flux:button>
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal" name="delete-project" class="max-w-lg">
        <form wire:submit="deleteProject" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete :project?', ['project' => $project->name]) }}</flux:heading>
                <flux:subheading>
                    {{ __('This permanently removes the project, its container, volumes, files, and configuration. This action cannot be undone.') }}
                </flux:subheading>
            </div>

            <flux:input
                wire:model="projectNameConfirmation"
                :label="__('Type :project to confirm', ['project' => $project->name])"
                placeholder="{{ $project->name }}"
                autocomplete="off"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Delete permanently') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Deployment Progress (shown during deployment) -->
    @if(in_array($deploymentStatus, ['pending', 'cloning', 'installing', 'generating', 'building', 'starting', 'deploying']))
        <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-primary);">
            <div class="flex items-center gap-3 mb-4">
                <div class="animate-spin">
                    <flux:icon.arrow-path class="size-5" style="color: var(--color-primary);" />
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold" style="color: var(--color-on-surface);">{{ __('Deployment in Progress') }}</h3>
                    @if($deploymentMessage)
                        <p class="text-sm mt-1" style="color: var(--color-on-surface-variant);">{{ $deploymentMessage }}</p>
                    @endif
                </div>
                <span class="text-sm font-mono font-semibold" style="color: var(--color-primary);">{{ $deploymentProgress }}%</span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--color-surface-container-high);">
                <div class="h-full transition-all duration-500" style="background-color: var(--color-primary); width: {{ $deploymentProgress }}%;"></div>
            </div>

            <!-- Status Messages -->
            <div class="mt-4 space-y-2">
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.check-circle class="size-4" style="color: {{ $deploymentProgress >= 20 ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }};" />
                    <span style="color: {{ $deploymentProgress >= 20 ? 'var(--color-on-surface)' : 'var(--color-on-surface-variant)' }};">{{ __('Cloning repository') }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.check-circle class="size-4" style="color: {{ $deploymentProgress >= 40 ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }};" />
                    <span style="color: {{ $deploymentProgress >= 40 ? 'var(--color-on-surface)' : 'var(--color-on-surface-variant)' }};">{{ __('Installing dependencies') }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.check-circle class="size-4" style="color: {{ $deploymentProgress >= 60 ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }};" />
                    <span style="color: {{ $deploymentProgress >= 60 ? 'var(--color-on-surface)' : 'var(--color-on-surface-variant)' }};">{{ __('Generating Docker configuration') }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.check-circle class="size-4" style="color: {{ $deploymentProgress >= 80 ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }};" />
                    <span style="color: {{ $deploymentProgress >= 80 ? 'var(--color-on-surface)' : 'var(--color-on-surface-variant)' }};">{{ __('Building Docker image') }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.check-circle class="size-4" style="color: {{ $deploymentProgress >= 100 ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }};" />
                    <span style="color: {{ $deploymentProgress >= 100 ? 'var(--color-on-surface)' : 'var(--color-on-surface-variant)' }};">{{ __('Starting container') }}</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Deployment Failed Alert -->
    @if($deploymentStatus === 'failed' && $project->deployment_error)
        <x-alert variant="danger">
            <strong>{{ __('Deployment Failed:') }}</strong> {{ $project->deployment_error }}
        </x-alert>
    @endif

    <!-- Tabs Navigation -->
    <div class="flex gap-1 border-b" style="border-color: var(--color-outline-variant);">
        <button wire:click="$set('activeTab', 'overview')" type="button"
                class="px-4 py-2 font-medium text-sm rounded-t-lg transition-colors"
                style="{{ $activeTab === 'overview' ? 'background-color: var(--color-surface-container); color: var(--color-primary); border-bottom: 2px solid var(--color-primary);' : 'color: var(--color-on-surface-variant);' }}">
            {{ __('Overview') }}
        </button>
        <button wire:click="$set('activeTab', 'cron')" type="button"
                class="px-4 py-2 font-medium text-sm rounded-t-lg transition-colors"
                style="{{ $activeTab === 'cron' ? 'background-color: var(--color-surface-container); color: var(--color-primary); border-bottom: 2px solid var(--color-primary);' : 'color: var(--color-on-surface-variant);' }}">
            {{ __('Cron Jobs') }}
        </button>
        <button wire:click="$set('activeTab', 'logs')" type="button"
                class="px-4 py-2 font-medium text-sm rounded-t-lg transition-colors"
                style="{{ $activeTab === 'logs' ? 'background-color: var(--color-surface-container); color: var(--color-primary); border-bottom: 2px solid var(--color-primary);' : 'color: var(--color-on-surface-variant);' }}">
            {{ __('Logs') }}
        </button>
        <button wire:click="$set('activeTab', 'terminal')" type="button"
                class="px-4 py-2 font-medium text-sm rounded-t-lg transition-colors"
                style="{{ $activeTab === 'terminal' ? 'background-color: var(--color-surface-container); color: var(--color-primary); border-bottom: 2px solid var(--color-primary);' : 'color: var(--color-on-surface-variant);' }}">
            {{ __('Terminal') }}
        </button>
        <button wire:click="$set('activeTab', 'env')" type="button"
                class="px-4 py-2 font-medium text-sm rounded-t-lg transition-colors"
                style="{{ $activeTab === 'env' ? 'background-color: var(--color-surface-container); color: var(--color-primary); border-bottom: 2px solid var(--color-primary);' : 'color: var(--color-on-surface-variant);' }}">
            {{ __('Environment') }}
        </button>
    </div>

    <!-- Tab Content -->
    <div>
        <!-- Overview Tab -->
        @if($activeTab === 'overview')
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Project Details -->
                <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    <h3 class="text-lg font-semibold font-display mb-4" style="color: var(--color-on-surface);">{{ __('Project Details') }}</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('PHP Version') }}</dt>
                            <dd class="mt-1" style="color: var(--color-on-surface);">{{ $project->php_version }}</dd>
                        </div>
                        @if($project->git_repo)
                            <div>
                                <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Git Repository') }}</dt>
                                <dd class="mt-1 text-sm truncate" style="color: var(--color-on-surface);">{{ $project->git_repo }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Branch') }}</dt>
                                <dd class="mt-1" style="color: var(--color-on-surface);">{{ $project->git_branch }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Auto Update') }}</dt>
                                <dd class="mt-1" style="color: var(--color-on-surface);">
                                    {{ $project->github_auto_update ? __('Enabled') : __('Disabled') }}
                                </dd>
                            </div>
                        @endif
                        @if($project->deployed_at)
                            <div>
                                <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Deployed At') }}</dt>
                                <dd class="mt-1" style="color: var(--color-on-surface);">{{ $project->deployed_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- Container Stats -->
                <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    <h3 class="text-lg font-semibold font-display mb-4" style="color: var(--color-on-surface);">{{ __('Container Status') }}</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Status') }}</span>
                            <flux:badge :color="$this->containerStatus['running'] ? 'lime' : 'zinc'" size="sm">
                                {{ $this->containerStatus['status'] ?? 'unknown' }}
                            </flux:badge>
                        </div>
                        @if($this->containerStatus['running'] && !empty($this->containerStats))
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('CPU Usage') }}</span>
                                <span class="font-mono text-sm" style="color: var(--color-on-surface);">{{ $this->containerStats['cpu_percent'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Memory Usage') }}</span>
                                <span class="font-mono text-sm" style="color: var(--color-on-surface);">{{ $this->containerStats['memory_usage'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Storage Usage / Limit') }}</span>
                                <span class="font-mono text-sm" style="color: var(--color-on-surface);">
                                    {{ $this->containerStats['storage_usage'] ?? 'N/A' }} / {{ $this->containerStats['storage_limit'] ?? 'Unlimited' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Network I/O') }}</span>
                                <span class="font-mono text-sm" style="color: var(--color-on-surface);">{{ $this->containerStats['network_io'] ?? 'N/A' }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Features Enabled -->
                <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    <h3 class="text-lg font-semibold font-display mb-4" style="color: var(--color-on-surface);">{{ __('Enabled Features') }}</h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            @if($project->reverb_enabled)
                                <flux:icon.check-circle class="size-5" style="color: var(--color-primary);" />
                            @else
                                <flux:icon.x-circle class="size-5" style="color: var(--color-on-surface-variant);" />
                            @endif
                            <span class="text-sm" style="color: var(--color-on-surface);">{{ __('Laravel Reverb') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($project->octane_enabled)
                                <flux:icon.check-circle class="size-5" style="color: var(--color-primary);" />
                                <span class="text-sm" style="color: var(--color-on-surface);">{{ __('Laravel Octane') }} ({{ ucfirst($project->octane_server) }})</span>
                            @else
                                <flux:icon.x-circle class="size-5" style="color: var(--color-on-surface-variant);" />
                                <span class="text-sm" style="color: var(--color-on-surface);">{{ __('Laravel Octane') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($project->queue_enabled)
                                <flux:icon.check-circle class="size-5" style="color: var(--color-primary);" />
                                <span class="text-sm" style="color: var(--color-on-surface);">{{ __('Queue Workers') }} ({{ $project->queue_workers }} workers)</span>
                            @else
                                <flux:icon.x-circle class="size-5" style="color: var(--color-on-surface-variant);" />
                                <span class="text-sm" style="color: var(--color-on-surface);">{{ __('Queue Workers') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    <h3 class="text-lg font-semibold font-display mb-4" style="color: var(--color-on-surface);">{{ __('Quick Stats') }}</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Cron Jobs') }}</span>
                            <span class="font-semibold" style="color: var(--color-on-surface);">{{ $project->cronJobs()->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Environment Variables') }}</span>
                            <span class="font-semibold" style="color: var(--color-on-surface);">{{ $project->environmentVariables()->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Container ID') }}</span>
                            <span class="font-mono text-xs" style="color: var(--color-on-surface);">{{ $project->container_id ? Str::limit($project->container_id, 12, '') : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Cron Jobs Tab -->
        @if($activeTab === 'cron')
            <div class="space-y-4">
                <div class="flex justify-end">
                    <flux:button wire:click="$set('showCronModal', true)" icon="plus" variant="primary" size="sm" class="w-full whitespace-nowrap sm:w-auto">
                        {{ __('Add Cron Job') }}
                    </flux:button>
                </div>

                <div class="rounded-lg" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    @forelse($project->cronJobs as $cron)
                        <div class="p-4 flex items-center justify-between border-b last:border-b-0" style="border-color: var(--color-outline-variant);">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <code class="font-mono text-sm" style="color: var(--color-primary);">{{ $cron->schedule }}</code>
                                    <flux:badge :color="$cron->enabled ? 'lime' : 'zinc'" size="sm">
                                        {{ $cron->enabled ? __('Enabled') : __('Disabled') }}
                                    </flux:badge>
                                </div>
                                <p class="text-sm font-mono" style="color: var(--color-on-surface);">{{ $cron->command }}</p>
                                @if($cron->last_run_at)
                                    <p class="text-xs mt-1" style="color: var(--color-on-surface-variant);">
                                        {{ __('Last run:') }} {{ $cron->last_run_at->diffForHumans() }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <flux:button wire:click="toggleCronJob({{ $cron->id }})" size="sm">
                                    {{ $cron->enabled ? __('Disable') : __('Enable') }}
                                </flux:button>
                                <flux:button wire:click="deleteCronJob({{ $cron->id }})" variant="danger" size="sm">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center" style="color: var(--color-on-surface-variant);">
                            {{ __('No cron jobs configured') }}
                        </div>
                    @endforelse
                </div>

                <x-alert variant="info">
                    {{ __('Cron jobs run inside the container. Restart the project to apply changes.') }}
                </x-alert>
            </div>

            @if($showCronModal)
                <flux:modal wire:model="showCronModal" title="{{ __('Add Cron Job') }}">
                    <form wire:submit="createCronJob" class="space-y-4">
                        <flux:input
                            wire:model="cronSchedule"
                            label="{{ __('Schedule (Cron Expression)') }}"
                            placeholder="* * * * *"
                            required
                        />
                        <flux:input
                            wire:model="cronCommand"
                            label="{{ __('Command') }}"
                            placeholder="php artisan inspire"
                            required
                        />
                        <div class="flex justify-end gap-2">
                            <flux:button type="button" wire:click="$set('showCronModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endif
        @endif

        <!-- Logs Tab -->
        @if($activeTab === 'logs')
            <div class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:select wire:model.live="logLines" class="w-full sm:w-auto">
                        <option value="50">50 lines</option>
                        <option value="100">100 lines</option>
                        <option value="200">200 lines</option>
                        <option value="500">500 lines</option>
                    </flux:select>

                    <flux:button wire:click="refreshLogs" icon="arrow-path" size="sm" class="w-full whitespace-nowrap sm:w-auto">
                        {{ __('Refresh') }}
                    </flux:button>
                </div>

                <div class="rounded-lg p-4" style="background-color: var(--color-surface-container-low); border: 1px solid var(--color-outline-variant);">
                    <pre class="text-xs font-mono overflow-x-auto" style="color: var(--color-on-surface);">{{ $this->logs ?: __('No logs available. Container may not be running.') }}</pre>
                </div>

                <x-alert variant="info">
                    {{ __('Showing container logs (stdout/stderr). Logs are fetched in real-time from Docker.') }}
                </x-alert>
            </div>
        @endif

        <!-- Terminal Tab -->
        @if($activeTab === 'terminal')
            <div class="space-y-4">
                <div
                    wire:ignore
                    x-data
                    x-init="window.initProjectTerminal($el)"
                    tabindex="0"
                    data-project-id="{{ $project->id }}"
                    class="h-[560px] w-full rounded-lg p-3"
                    style="background-color: #111827; border: 1px solid var(--color-outline-variant);"
                ></div>
            </div>
        @endif

        <!-- Environment Tab -->
        @if($activeTab === 'env')
            <div class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:button wire:click="refreshEnvVariables" icon="arrow-path" size="sm" class="w-full whitespace-nowrap sm:w-auto">
                        {{ __('Refresh from .env') }}
                    </flux:button>
                    <flux:button wire:click="$set('showEnvModal', true)" icon="plus" variant="primary" size="sm" class="w-full whitespace-nowrap sm:w-auto">
                        {{ __('Add Variable') }}
                    </flux:button>
                </div>

                <div class="rounded-lg" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    @forelse($project->environmentVariables as $env)
                        <div class="p-4 flex items-center justify-between border-b last:border-b-0" style="border-color: var(--color-outline-variant);">
                            <div class="flex-1">
                                <code class="font-mono text-sm font-semibold" style="color: var(--color-primary);">{{ $env->key }}</code>
                                <p class="mt-1 text-sm font-mono" style="color: var(--color-on-surface);">{{ Str::limit($env->value, 60) }}</p>
                            </div>
                            <div class="flex gap-2">
                                <flux:button wire:click="editEnvVariable({{ $env->id }})" size="sm">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button wire:click="deleteEnvVariable({{ $env->id }})" variant="danger" size="sm">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center" style="color: var(--color-on-surface-variant);">
                            {{ __('No environment variables configured') }}
                        </div>
                    @endforelse
                </div>

                <x-alert variant="warning">
                    {{ __('Environment variable changes require a project restart to take effect.') }}
                </x-alert>
            </div>

            @if($showEnvModal)
                <flux:modal wire:model="showEnvModal" title="{{ $editingEnvId ? __('Edit Environment Variable') : __('Add Environment Variable') }}">
                    <form wire:submit="saveEnvVariable" class="space-y-4">
                        <flux:input
                            wire:model="envKey"
                            label="{{ __('Key') }}"
                            placeholder="APP_DEBUG"
                            :readonly="!!$editingEnvId"
                            required
                        />
                        <flux:textarea
                            wire:model="envValue"
                            label="{{ __('Value') }}"
                            placeholder="false"
                            rows="3"
                            required
                        />
                        <div class="flex justify-end gap-2">
                            <flux:button type="button" wire:click="$set('showEnvModal', false); $set('editingEnvId', null); $set('envKey', ''); $set('envValue', '');" variant="ghost">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endif
        @endif
    </div>
</div>
