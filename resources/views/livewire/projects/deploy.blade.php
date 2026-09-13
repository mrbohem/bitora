<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Page Header -->
    <div>
        <h1 class="text-3xl font-bold font-display" style="color: var(--color-on-surface);">{{ __('Deploy New Project') }}</h1>
        <p class="mt-1" style="color: var(--color-on-surface-variant);">{{ __('Deploy your Laravel project with zero configuration') }}</p>
    </div>

    <!-- Progress Steps -->
    <div class="flex items-center justify-between max-w-4xl">
        <div class="flex items-center gap-2 {{ $step >= 1 ? 'opacity-100' : 'opacity-40' }}">
            <div class="h-10 w-10 rounded-full flex items-center justify-center font-semibold"
                 style="background-color: {{ $step >= 1 ? 'var(--color-primary)' : 'var(--color-surface-container-high)' }};
                        color: {{ $step >= 1 ? 'var(--color-on-primary)' : 'var(--color-on-surface-variant)' }};">
                1
            </div>
            <span class="text-sm font-medium" style="color: var(--color-on-surface);">{{ __('Git Repository') }}</span>
        </div>
        <div class="flex-1 h-0.5 mx-4" style="background-color: {{ $step >= 2 ? 'var(--color-primary)' : 'var(--color-outline-variant)' }};"></div>

        <div class="flex items-center gap-2 {{ $step >= 2 ? 'opacity-100' : 'opacity-40' }}">
            <div class="h-10 w-10 rounded-full flex items-center justify-center font-semibold"
                 style="background-color: {{ $step >= 2 ? 'var(--color-primary)' : 'var(--color-surface-container-high)' }};
                        color: {{ $step >= 2 ? 'var(--color-on-primary)' : 'var(--color-on-surface-variant)' }};">
                2
            </div>
            <span class="text-sm font-medium" style="color: var(--color-on-surface);">{{ __('Configuration') }}</span>
        </div>
        <div class="flex-1 h-0.5 mx-4" style="background-color: {{ $step >= 3 ? 'var(--color-primary)' : 'var(--color-outline-variant)' }};"></div>

        <div class="flex items-center gap-2 {{ $step >= 3 ? 'opacity-100' : 'opacity-40' }}">
            <div class="h-10 w-10 rounded-full flex items-center justify-center font-semibold"
                 style="background-color: {{ $step >= 3 ? 'var(--color-primary)' : 'var(--color-surface-container-high)' }};
                        color: {{ $step >= 3 ? 'var(--color-on-primary)' : 'var(--color-on-surface-variant)' }};">
                3
            </div>
            <span class="text-sm font-medium" style="color: var(--color-on-surface);">{{ __('Features') }}</span>
        </div>
        <div class="flex-1 h-0.5 mx-4" style="background-color: {{ $step >= 4 ? 'var(--color-primary)' : 'var(--color-outline-variant)' }};"></div>

        <div class="flex items-center gap-2 {{ $step >= 4 ? 'opacity-100' : 'opacity-40' }}">
            <div class="h-10 w-10 rounded-full flex items-center justify-center font-semibold"
                 style="background-color: {{ $step >= 4 ? 'var(--color-primary)' : 'var(--color-surface-container-high)' }};
                        color: {{ $step >= 4 ? 'var(--color-on-primary)' : 'var(--color-on-surface-variant)' }};">
                4
            </div>
            <span class="text-sm font-medium" style="color: var(--color-on-surface);">{{ __('Environment') }}</span>
        </div>
    </div>

    <!-- Deployment Form -->
    <div class="max-w-4xl">
        <div class="rounded-lg p-8" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
            @if ($errors->has('deployment'))
                <x-alert variant="danger" class="mb-6">
                    {{ $errors->first('deployment') }}
                </x-alert>
            @endif

            <form wire:submit="deploy">
                <!-- Step 1: Git Repository -->
                @if ($step === 1)
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold font-display mb-2" style="color: var(--color-on-surface);">{{ __('Git Repository') }}</h2>
                            <p class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Import your Laravel project from Git') }}</p>
                        </div>

                        <flux:input
                            wire:model="name"
                            label="{{ __('Project Name') }}"
                            placeholder="my-laravel-app"
                            required
                        />

                        <flux:input
                            wire:model="git_repo"
                            label="{{ __('Git Repository URL') }}"
                            placeholder="https://github.com/username/repository.git"
                            type="url"
                        />

                        <flux:input
                            wire:model="git_branch"
                            label="{{ __('Branch') }}"
                            placeholder="main"
                        />

                        <flux:input
                            wire:model="app_path"
                            label="{{ __('Laravel Folder Path') }}"
                            placeholder=". or apps/laravel"
                            required
                        />
                        <p class="text-sm -mt-4" style="color: var(--color-on-surface-variant);">
                            {{ __('Use . when Laravel is at the repository root, or enter its folder path when the repository contains other projects. For example, if artisan is at apps/laravel/artisan, enter apps/laravel.') }}
                        </p>

                        <flux:select wire:model.live="git_auth_type" label="{{ __('Authentication') }}" required>
                            <option value="none">{{ __('Public Repository (No Auth)') }}</option>
                            <option value="token">{{ __('Personal Access Token') }}</option>
                            <option value="ssh">{{ __('SSH Key') }}</option>
                        </flux:select>

                        @if ($git_auth_type === 'token')
                            <flux:textarea
                                wire:model="git_credentials"
                                label="{{ __('Personal Access Token') }}"
                                placeholder="ghp_xxxxxxxxxxxx"
                                rows="2"
                            />
                        @elseif ($git_auth_type === 'ssh')
                            <flux:textarea
                                wire:model="git_credentials"
                                label="{{ __('SSH Private Key') }}"
                                placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
                                rows="6"
                            />
                        @endif

                        <div class="flex justify-end gap-3 pt-4">
                            <flux:button type="button" wire:click="nextStep" variant="primary">
                                {{ __('Continue') }}
                            </flux:button>
                        </div>
                    </div>
                @endif

                <!-- Step 2: Configuration -->
                @if ($step === 2)
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold font-display mb-2" style="color: var(--color-on-surface);">{{ __('Configuration') }}</h2>
                            <p class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Configure PHP version and domain') }}</p>
                        </div>

                        <flux:select wire:model="php_version" label="{{ __('PHP Version') }}" required>
                            <option value="8.4">PHP 8.4</option>
                            <option value="8.3">PHP 8.3</option>
                            <option value="8.2">PHP 8.2</option>
                        </flux:select>

                        <flux:input
                            wire:model="domain"
                            label="{{ __('Domain (Optional)') }}"
                            placeholder="myapp.example.com"
                        />

                        <x-alert variant="info">
                            {{ __('Domain will be auto-configured with reverse proxy. SSL can be enabled later.') }}
                        </x-alert>

                        <div class="flex justify-between gap-3 pt-4">
                            <flux:button type="button" wire:click="previousStep" variant="ghost">
                                {{ __('Back') }}
                            </flux:button>
                            <flux:button type="button" wire:click="nextStep" variant="primary">
                                {{ __('Continue') }}
                            </flux:button>
                        </div>
                    </div>
                @endif

                <!-- Step 3: Features -->
                @if ($step === 3)
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold font-display mb-2" style="color: var(--color-on-surface);">{{ __('Features') }}</h2>
                            <p class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Enable optional Laravel features') }}</p>
                        </div>

                        <div class="space-y-4">
                            <!-- Queue Workers -->
                            <div class="rounded-lg p-4" style="background-color: var(--color-surface-container-high);">
                                <flux:checkbox wire:model.live="queue_enabled" label="{{ __('Enable Queue Workers') }}" />
                                <p class="text-sm mt-1 ml-6" style="color: var(--color-on-surface-variant);">{{ __('Background job processing') }}</p>

                                @if ($queue_enabled)
                                    <div class="mt-3 ml-6 space-y-3">
                                        <flux:input
                                            wire:model="queue_connection"
                                            label="{{ __('Queue Connection') }}"
                                            placeholder="redis"
                                        />
                                        <flux:input
                                            wire:model="queue_workers"
                                            label="{{ __('Number of Workers') }}"
                                            type="number"
                                            min="1"
                                            max="10"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex justify-between gap-3 pt-4">
                            <flux:button type="button" wire:click="previousStep" variant="ghost">
                                {{ __('Back') }}
                            </flux:button>
                            <flux:button type="button" wire:click="nextStep" variant="primary">
                                {{ __('Continue') }}
                            </flux:button>
                        </div>
                    </div>
                @endif

                <!-- Step 4: Environment Variables -->
                @if ($step === 4)
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold font-display mb-2" style="color: var(--color-on-surface);">{{ __('Environment Variables') }}</h2>
                            <p class="text-sm" style="color: var(--color-on-surface-variant);">{{ __('Configure environment variables (Optional)') }}</p>
                        </div>

                        <!-- Add Variable Form -->
                        <div class="rounded-lg p-4" style="background-color: var(--color-surface-container-high);">
                            <div class="grid grid-cols-2 gap-3">
                                <flux:input
                                    wire:model="env_key"
                                    placeholder="KEY"
                                />
                                <flux:input
                                    wire:model="env_value"
                                    placeholder="value"
                                />
                            </div>
                            <flux:button type="button" wire:click="addEnvVariable" variant="ghost" class="mt-2">
                                {{ __('+ Add Variable') }}
                            </flux:button>
                        </div>

                        <!-- Variables List -->
                        @if (count($env_variables) > 0)
                            <div class="space-y-2">
                                @foreach ($env_variables as $key => $value)
                                    <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--color-surface-container-high);">
                                        <div class="flex-1">
                                            <span class="font-mono text-sm font-medium" style="color: var(--color-on-surface);">{{ $key }}</span>
                                            <span class="mx-2" style="color: var(--color-on-surface-variant);">=</span>
                                            <span class="font-mono text-sm" style="color: var(--color-on-surface-variant);">{{ Str::limit($value, 40) }}</span>
                                        </div>
                                        <flux:button type="button" wire:click="removeEnvVariable('{{ $key }}')" variant="ghost" size="sm">
                                            {{ __('Remove') }}
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <x-alert variant="info">
                            {{ __('You can add more variables later. Common variables like APP_KEY will be auto-generated.') }}
                        </x-alert>

                        <div class="flex justify-between gap-3 pt-4">
                            <flux:button type="button" wire:click="previousStep" variant="ghost">
                                {{ __('Back') }}
                            </flux:button>
                            <flux:button type="submit" variant="primary">
                                {{ __('Deploy Project') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
