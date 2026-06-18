@props([
    'transaksi' => null,
    'title' => 'Timeline Transaksi',
])

@php
    $steps = $transaksi?->buyerTimeline() ?? [];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3']) }}>
    <p class="text-xs font-extrabold text-slate-800">{{ $title }}</p>
    <div class="mt-3 space-y-3">
        @foreach($steps as $index => $step)
            <div class="flex gap-3">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-[11px] font-black {{
                    $step['done']
                        ? 'border-emerald-200 bg-emerald-100 text-emerald-700'
                        : 'border-slate-200 bg-white text-slate-400'
                }} {{ $step['current'] ? 'ring-4 ring-emerald-100' : '' }}">
                    {{ $index + 1 }}
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-extrabold {{ $step['done'] ? 'text-slate-900' : 'text-slate-500' }}">{{ $step['title'] }}</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed {{ $step['done'] ? 'text-slate-600' : 'text-slate-400' }}">{{ $step['description'] }}</p>
                    @if(! empty($step['at']))
                        <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $step['at'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
