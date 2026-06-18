@props([
    'href',
    'label' => 'Kembali',
    'showOnMobile' => false,
    'iconOnlyOnMobile' => false,
])

@php
    $isMobileVisible = $showOnMobile || $iconOnlyOnMobile;
    $visibilityClass = $isMobileVisible ? 'inline-flex' : 'hidden sm:inline-flex';
    $mobileIconClass = $iconOnlyOnMobile ? 'min-w-[48px] justify-center gap-0 px-3 sm:min-w-0 sm:justify-start sm:gap-2 sm:px-4' : 'gap-2 px-4';
@endphp

<a
    href="{{ $href }}"
    aria-label="{{ $label }}"
    {{ $attributes->merge([
        'data-restore-scroll-target' => 'true',
        'class' => $visibilityClass . ' min-h-[48px] items-center rounded-xl border border-slate-200 bg-white/90 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 ' . $mobileIconClass,
    ]) }}
>
    <span aria-hidden="true" class="text-base leading-none">&larr;</span>
    @if($iconOnlyOnMobile)
        <span class="sr-only sm:not-sr-only">{{ $label }}</span>
    @else
        <span>{{ $label }}</span>
    @endif
</a>
