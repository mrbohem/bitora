<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="background-color: var(--color-background);">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 font-medium" wire:navigate>
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg" style="background-color: var(--color-primary-container);">
                        <x-app-logo-icon class="size-8 fill-current" style="color: var(--color-on-primary-container);" />
                    </span>
                    <span class="text-xl font-semibold" style="color: var(--color-on-surface); font-family: var(--font-display);">{{ config('app.name', 'Lumina Forge') }}</span>
                    <span class="sr-only">{{ config('app.name', 'Lumina Forge') }}</span>
                </a>
                <div class="flex flex-col gap-6 rounded-xl p-8" style="background-color: var(--color-surface-container); border: 1px solid var(--color-outline-variant);">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
