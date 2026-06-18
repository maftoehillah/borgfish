@props(['active' => false])

@php
    $base = 'px-3 py-1 rounded-md text-sm text-muted transition';
    $activeClass = $active ? ' bg-accent-100 text-text font-medium' : '';
@endphp

<button {{ $attributes->merge(['class' => $base . $activeClass]) }} aria-selected="{{ $active ? 'true' : 'false' }}">
    {{ $slot }}
</button>
