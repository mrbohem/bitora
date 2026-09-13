<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold font-display" style="color: var(--color-on-surface);">{{ __('Projects') }}</h1>
            <p class="mt-1" style="color: var(--color-on-surface-variant);">{{ __('Manage your deployed Laravel projects') }}</p>
        </div>
        <flux:button href="{{ route('projects.deploy') }}" variant="primary" wire:navigate>
            <flux:icon.plus class="size-5" />
            {{ __('Deploy New Project') }}
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="flex gap-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search projects...') }}"
                icon="magnifying-glass"
            />
        </div>
        <flux:select wire:model.live="statusFilter" placeholder="{{ __('All Status') }}">
            <option value="">{{ __('All Status') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="stopped">{{ __('Stopped') }}</option>
            <option value="deploying">{{ __('Deploying') }}</option>
            <option value="failed">{{ __('Failed') }}</option>
        </flux:select>
    </div>

    <!-- Projects Grid -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse($projects as $project)
                <div class="relative rounded-lg p-6 transition-all hover:scale-[1.02]"
                      style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                     <a href="{{ route('projects.show', $project) }}" wire:navigate
                         class="absolute inset-0 rounded-lg"
                         aria-label="{{ __('View :project', ['project' => $project->name]) }}"></a>
                <div class="flex items-start justify-between mb-4">
                    <div class="h-12 w-12 rounded-lg flex items-center justify-center"
                         style="background-color: var(--color-primary-container);">
                        <flux:icon.cube class="size-6" style="color: var(--color-on-primary-container);" />
                    </div>
                    <flux:badge
                        :color="match($project->status) {
                            'active' => 'lime',
                            'deploying' => 'blue',
                            'failed' => 'red',
                            default => 'zinc'
                        }"
                        size="sm">
                        {{ $project->status }}
                    </flux:badge>
                </div>

                <h3 class="text-lg font-semibold font-display mb-1" style="color: var(--color-on-surface);">
                    {{ $project->name }}
                </h3>
                <p class="text-sm mb-4" style="color: var(--color-on-surface-variant);">
                    {{ $project->domain ?? $project->slug }}
                </p>

                @if($project->url)
                          <a href="{{ $project->url }}" target="_blank" rel="noopener noreferrer"
                              class="relative z-10 inline-flex items-center gap-2 text-sm font-medium mb-4 hover:underline"
                       style="color: var(--color-primary);">
                        <flux:icon.arrow-top-right-on-square class="size-4" />
                        Visit Site
                    </a>
                @endif

                <div class="flex items-center gap-4 text-xs" style="color: var(--color-on-surface-variant);">
                    <div class="flex items-center gap-1">
                        <flux:icon.server class="size-4" />
                        {{ ucfirst($project->web_server) }}
                    </div>
                    @if($project->deployed_at)
                        <div class="flex items-center gap-1">
                            <flux:icon.clock class="size-4" />
                            {{ $project->deployed_at->diffForHumans() }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="rounded-lg p-12 text-center" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    <flux:icon.inbox class="size-16 mx-auto mb-4 opacity-40" style="color: var(--color-on-surface-variant);" />
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--color-on-surface);">{{ __('No projects found') }}</h3>
                    <p class="mb-6" style="color: var(--color-on-surface-variant);">{{ __('Deploy your first Laravel project to get started.') }}</p>
                    <flux:button href="{{ route('projects.deploy') }}" variant="primary" wire:navigate>
                        {{ __('Deploy Your First Project') }}
                    </flux:button>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($projects->hasPages())
        <div class="mt-6">
            {{ $projects->links() }}
        </div>
    @endif
</div>
