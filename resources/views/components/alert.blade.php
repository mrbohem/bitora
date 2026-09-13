@props([
    'variant' => 'info',
])

@php
    $styles = match($variant) {
        'info' => 'background-color: rgba(77, 171, 247, 0.1); border-color: #4dabf7; color: #4dabf7;',
        'success' => 'background-color: rgba(78, 222, 163, 0.1); border-color: var(--color-secondary); color: var(--color-secondary);',
        'warning' => 'background-color: rgba(251, 191, 36, 0.1); border-color: #fbbf24; color: #fbbf24;',
        'danger' => 'background-color: rgba(255, 81, 106, 0.1); border-color: var(--color-tertiary-container); color: var(--color-tertiary-container);',
        default => 'background-color: rgba(77, 171, 247, 0.1); border-color: #4dabf7; color: #4dabf7;',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg p-4 border']) }} style="{{ $styles }}">
    {{ $slot }}
</div>
