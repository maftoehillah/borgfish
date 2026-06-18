@props([
    'href',
])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-center text-[15px] font-bold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2',
    ]) }}
>
    {{ $slot }}
</a>
