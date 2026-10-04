<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Page Header -->
    <div>
        <h1 class="text-3xl font-bold font-display" style="color: var(--color-on-surface);">{{ __('Panel Settings') }}</h1>
        <p class="mt-1" style="color: var(--color-on-surface-variant);">{{ __('Configure your Bitora panel') }}</p>
    </div>

    <!-- Settings Form -->
    <div class="max-w-3xl">
        <form wire:submit="save" class="space-y-6">
            <!-- General Settings -->
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <h2 class="text-xl font-semibold font-display mb-4" style="color: var(--color-on-surface);">{{ __('General Settings') }}</h2>

                <div class="space-y-4">
                    <flux:input
                        wire:model="panelName"
                        label="{{ __('Panel Name') }}"
                        placeholder="Bitora"
                        required
                    />

                    <flux:radio.group wire:model="defaultWebServer" label="{{ __('Default Web Server') }}" required>
                        <flux:radio value="nginx" label="Nginx" description="{{ __('High-performance web server') }}" />
                        <flux:radio value="apache" label="Apache" description="{{ __('Traditional web server with .htaccess support') }}" />
                    </flux:radio.group>
                </div>
            </div>

            <!-- SSH Key Management -->
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold font-display" style="color: var(--color-on-surface);">{{ __('SSH Key') }}</h2>
                    @if(!$sshPublicKey)
                        <flux:button wire:click="generateSshKey" type="button" size="sm" variant="primary">
                            {{ __('Generate SSH Key') }}
                        </flux:button>
                    @endif
                </div>

                @if($sshPublicKey)
                    <div>
                        <flux:label>{{ __('Public Key') }}</flux:label>
                        <div class="mt-2 p-4 rounded-lg font-mono text-xs overflow-x-auto" style="background-color: var(--color-surface-container-low); color: var(--color-on-surface);">
                            {{ $sshPublicKey }}
                        </div>
                        <x-alert variant="info" class="mt-4">
                            {{ __('Add this public key to your Git provider (GitHub, GitLab, etc.) to enable repository access.') }}
                        </x-alert>
                    </div>
                @else
                    <x-alert variant="info">
                        {{ __('No SSH key has been generated yet. Generate one to enable Git repository access.') }}
                    </x-alert>
                @endif
            </div>

            <!-- Server Information -->
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                <h2 class="text-xl font-semibold font-display mb-4" style="color: var(--color-on-surface);">{{ __('Server Information') }}</h2>

                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('PHP Version') }}</dt>
                        <dd class="mt-1 font-mono" style="color: var(--color-on-surface);">{{ PHP_VERSION }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Laravel Version') }}</dt>
                        <dd class="mt-1 font-mono" style="color: var(--color-on-surface);">{{ app()->version() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium" style="color: var(--color-on-surface-variant);">{{ __('Operating System') }}</dt>
                        <dd class="mt-1 font-mono" style="color: var(--color-on-surface);">{{ php_uname('s') }} {{ php_uname('r') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Actions -->
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    {{ __('Save Settings') }}
                </flux:button>
            </div>
        </form>

        <section
            @if(in_array($updateStatus['status'] ?? 'idle', ['starting', 'pulling', 'restarting']))
                wire:poll.3s="refreshUpdateStatus"
            @endif
            class="mt-6 rounded-lg p-6"
            style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold font-display" style="color: var(--color-on-surface);">{{ __('Bitora Panel Update') }}</h2>
                    <p class="mt-2 text-sm" style="color: var(--color-on-surface-variant);">
                        {{ __('Current version: :version', ['version' => $currentVersion]) }}
                        @if($latestVersion)
                            <span class="mx-1">·</span>
                            {{ __('Latest version: :version', ['version' => $latestVersion]) }}
                        @endif
                    </p>
                    @if($latestVersion)
                        <p role="status" aria-live="polite" class="mt-1 text-sm" style="color: var(--color-on-surface-variant);">
                            @if($updateAvailable)
                                {{ __('Version :version is available.', ['version' => $latestVersion]) }}
                            @else
                                {{ __('You are already using the latest version.') }}
                            @endif
                        </p>
                    @elseif(!$updateError)
                        <p role="status" aria-live="polite" class="mt-1 text-sm" style="color: var(--color-on-surface-variant);">
                            {{ __('No versioned releases are currently available.') }}
                        </p>
                    @endif
                    <p class="mt-1 text-sm" style="color: var(--color-on-surface-variant);">
                        {{ __('Updating briefly restarts only the Bitora panel. Deployed project containers are not restarted.') }}
                    </p>
                </div>

                @if($updateAvailable && !in_array($updateStatus['status'] ?? 'idle', ['starting', 'pulling', 'restarting']))
                    <flux:button
                        type="button"
                        wire:click="updatePanel"
                        wire:loading.attr="disabled"
                        wire:target="updatePanel"
                        variant="primary"
                    >
                        <span wire:loading.remove wire:target="updatePanel">{{ __('Update Panel') }}</span>
                        <span wire:loading wire:target="updatePanel">{{ __('Preparing update...') }}</span>
                    </flux:button>
                @elseif(!in_array($updateStatus['status'] ?? 'idle', ['starting', 'pulling', 'restarting']))
                    <flux:button type="button" wire:click="checkForUpdates" wire:loading.attr="disabled" wire:target="checkForUpdates">
                        <span wire:loading.remove wire:target="checkForUpdates">{{ __('Check for Updates') }}</span>
                        <span wire:loading wire:target="checkForUpdates">{{ __('Checking...') }}</span>
                    </flux:button>
                @endif
            </div>

            @if($updateStatus['message'] ?? false)
                <p role="status" class="mt-4 text-sm" style="color: var(--color-on-surface-variant);">
                    {{ $updateStatus['message'] }}
                </p>
            @endif

            @if($updateError)
                <x-alert variant="danger" class="mt-4">{{ $updateError }}</x-alert>
            @endif
        </section>
    </div>
</div>
