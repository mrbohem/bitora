<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <!-- Page Header -->
        <div>
            <h1 class="text-3xl font-bold font-display" style="color: var(--color-on-surface);">{{ __('Dashboard') }}</h1>
            <p class="mt-1" style="color: var(--color-on-surface-variant);">{{ __('Overview of your server resources and projects') }}</p>
        </div>

        <!-- Resource Overview Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('CPU Usage') }}</p>
                        <p class="mt-2 text-3xl font-semibold font-display" style="color: var(--color-on-surface);">24%</p>
                    </div>
                    <div class="h-12 w-12 rounded-full flex items-center justify-center" style="background-color: var(--color-secondary-container);">
                        <flux:icon.cpu-chip class="size-6" style="color: var(--color-on-secondary-container);" />
                    </div>
                </div>
                <p class="mt-2 text-xs" style="color: var(--color-secondary);">{{ __('↑ 2% from last hour') }}</p>
            </div>

            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Memory Usage') }}</p>
                        <p class="mt-2 text-3xl font-semibold font-display" style="color: var(--color-on-surface);">4.2 GB</p>
                    </div>
                    <div class="h-12 w-12 rounded-full flex items-center justify-center" style="background-color: var(--color-primary-container);">
                        <flux:icon.circle-stack class="size-6" style="color: var(--color-on-primary-container);" />
                    </div>
                </div>
                <p class="mt-2 text-xs" style="color: var(--color-on-surface-variant);">{{ __('of 8 GB total') }}</p>
            </div>

            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Active Projects') }}</p>
                        <p class="mt-2 text-3xl font-semibold font-display" style="color: var(--color-on-surface);">3</p>
                    </div>
                    <div class="h-12 w-12 rounded-full flex items-center justify-center" style="background-color: var(--color-secondary-container);">
                        <flux:icon.rocket-launch class="size-6" style="color: var(--color-on-secondary-container);" />
                    </div>
                </div>
                <p class="mt-2 text-xs" style="color: var(--color-secondary);">{{ __('All systems running') }}</p>
            </div>
        </div>

        <!-- Projects List & Activity Feed -->
        <div class="grid gap-6 md:grid-cols-2">
            <!-- Projects List -->
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold font-display" style="color: var(--color-on-surface);">{{ __('Recent Projects') }}</h2>
                    <flux:button size="sm" variant="ghost">{{ __('View All') }}</flux:button>
                </div>
                <div class="space-y-3">
                    @forelse(auth()->user()->projects()->latest()->limit(5)->get() as $project)
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--color-surface-container-high);">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg flex items-center justify-center" style="background-color: var(--color-primary-container);">
                                    <flux:icon.cube class="size-5" style="color: var(--color-on-primary-container);" />
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--color-on-surface);">{{ $project->name }}</p>
                                    <p class="text-xs" style="color: var(--color-on-surface-variant);">{{ $project->domain ?? $project->slug }}</p>
                                </div>
                            </div>
                            <flux:badge :color="$project->status === 'active' ? 'lime' : 'zinc'" size="sm">{{ $project->status }}</flux:badge>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <flux:icon.inbox class="size-12 mx-auto mb-3 opacity-40" style="color: var(--color-on-surface-variant);" />
                            <p style="color: var(--color-on-surface-variant);">{{ __('No projects yet') }}</p>
                            <flux:button size="sm" variant="primary" class="mt-4">{{ __('Deploy Your First Project') }}</flux:button>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold font-display" style="color: var(--color-on-surface);">{{ __('Recent Activity') }}</h2>
                </div>
                <div class="space-y-4">
                    @php
                        $activities = app(\App\Services\ActivityLogService::class)->getUserActivities(auth()->user(), 10);
                    @endphp
                    @forelse($activities as $activity)
                        <div class="flex gap-3">
                            <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: var(--color-surface-container-highest);">
                                <flux:icon.bolt class="size-4" style="color: var(--color-on-surface-variant);" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm" style="color: var(--color-on-surface);">{{ $activity->description }}</p>
                                <p class="text-xs mt-1" style="color: var(--color-on-surface-variant);">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <flux:icon.clock class="size-12 mx-auto mb-3 opacity-40" style="color: var(--color-on-surface-variant);" />
                            <p style="color: var(--color-on-surface-variant);">{{ __('No recent activity') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
