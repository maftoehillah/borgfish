@props(['type' => 'button', 'variant' => 'soft'])

@php
    $classes = 'inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold transition';
    if($variant === 'soft') {
        $classes .= ' bg-accent-100 text-text border-transparent';
    } else {
        $classes .= ' bg-transparent border border-border text-text';
    }
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>
    {{ $slot }}
</button>
