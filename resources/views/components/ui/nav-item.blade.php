@props(['href' => '#', 'active' => false])

@php
    $base = 'flex items-center gap-3 p-2 rounded-md text-text no-underline transition';
    $activeClass = $active ? ' bg-accent-100 font-semibold' : ' hover:bg-slate-50';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $base . $activeClass]) }}>
    {{ $slot }}
</a>
