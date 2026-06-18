@php
    $tipeLelang = $tipeLelang ?? 'semua';
    $allUrl = $allUrl ?? '#';
    $naikUrl = $naikUrl ?? '#';
    $turunUrl = $turunUrl ?? '#';
    $marginClass = $marginClass ?? 'mb-6';

    $tipeLelangLabel = match ($tipeLelang) {
        'naik' => 'Lelang Naik',
        'turun' => 'Lelang Turun',
        default => 'Semua Lelang',
    };

    $tipeLelangTone = match ($tipeLelang) {
        'naik' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'turun' => 'bg-amber-50 text-amber-700 border-amber-200',
        default => 'bg-slate-50 text-slate-700 border-gray-200',
    };
@endphp

<div class="{{ $marginClass }}">
    <div class="sm:hidden">
        <div class="rounded-2xl border border-slate-200 bg-white/90 p-2 shadow-sm" x-data="{ open: false }" @keydown.escape.window="open = false">
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex min-h-[48px] min-w-0 flex-1 items-center rounded-xl border px-4 py-2.5 text-sm font-extrabold {{ $tipeLelangTone }}">
                    <span class="truncate">{{ $tipeLelangLabel }}</span>
                </span>

                <div class="relative shrink-0">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                    aria-label="Pilih jenis lelang"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M6 12h12m-7 7h2" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    @click.outside="open = false"
                    x-transition
                    class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                >
                    <a href="{{ $allUrl }}" class="flex min-h-[48px] items-center px-4 py-3 text-sm font-semibold transition {{ $tipeLelang === 'semua' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                        Semua Lelang
                    </a>
                    <a href="{{ $naikUrl }}" class="flex min-h-[48px] items-center px-4 py-3 text-sm font-semibold transition {{ $tipeLelang === 'naik' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-50' }}">
                        Lelang Naik
                    </a>
                    <a href="{{ $turunUrl }}" class="flex min-h-[48px] items-center px-4 py-3 text-sm font-semibold transition {{ $tipeLelang === 'turun' ? 'bg-amber-50 text-amber-700' : 'text-slate-700 hover:bg-slate-50' }}">
                        Lelang Turun
                    </a>
                </div>
            </div>
            </div>
        </div>
    </div>

    <div class="hidden sm:flex justify-end">
        <div
            class="relative flex items-center gap-2"
            x-data="{
                open: false,
                flash: false,
                rippleFor: null,
                rippleStyle: '',
                select(event) {
                    const link = event.currentTarget;
                    const rect = link.getBoundingClientRect();
                    const x = Number.isFinite(event.offsetX) ? event.offsetX : rect.width / 2;
                    const y = Number.isFinite(event.offsetY) ? event.offsetY : rect.height / 2;

                    this.rippleFor = link.dataset.option ?? null;
                    this.rippleStyle = `left: ${x}px; top: ${y}px;`;
                    this.open = false;

                    setTimeout(() => {
                        window.location.href = link.href;
                    }, 160);
                },
            }"
            x-init="requestAnimationFrame(() => { flash = true; setTimeout(() => flash = false, 450); })"
            @keydown.escape.window="open = false"
        >
            <span
                class="inline-flex min-h-[48px] items-center rounded-xl border px-4 py-2.5 text-xs font-extrabold transition-all duration-300 {{ $tipeLelangTone }}"
                :class="flash ? 'scale-105 ring-2 ring-offset-2 ring-slate-300 shadow-sm' : ''"
            >
                {{ $tipeLelangLabel }}
            </span>

            <button
                type="button"
                @click="open = !open"
                class="inline-flex h-12 w-12 items-center justify-center rounded-xl border shadow-sm transition {{ $tipeLelangTone }} hover:brightness-95"
                title="Jenis lelang: {{ $tipeLelangLabel }}"
                aria-label="Pilih jenis lelang"
            >
                <svg class="w-5 h-5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M6 12h12m-7 7h2" />
                </svg>
            </button>

            <div
                x-cloak
                x-show="open"
                @click.outside="open = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                class="absolute right-0 mt-2 w-52 bg-white rounded-xl border border-gray-100 shadow-xl overflow-hidden z-30 origin-top-right"
            >
                <a href="{{ $allUrl }}" data-option="semua" @click.prevent="select($event)" class="relative flex min-h-[48px] items-center overflow-hidden px-4 py-3 text-sm transition {{ $tipeLelang === 'semua' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-700 hover:bg-gray-50' }}">
                    <span class="relative z-10">Semua Lelang</span>
                    <span x-show="rippleFor === 'semua'" x-transition.opacity.duration.150ms class="pointer-events-none absolute h-20 w-20 -translate-x-1/2 -translate-y-1/2 rounded-full bg-current/25 animate-ping" :style="rippleStyle"></span>
                </a>
                <a href="{{ $naikUrl }}" data-option="naik" @click.prevent="select($event)" class="relative flex min-h-[48px] items-center overflow-hidden px-4 py-3 text-sm transition {{ $tipeLelang === 'naik' ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-gray-700 hover:bg-gray-50' }}">
                    <span class="relative z-10">Lelang Naik</span>
                    <span x-show="rippleFor === 'naik'" x-transition.opacity.duration.150ms class="pointer-events-none absolute h-20 w-20 -translate-x-1/2 -translate-y-1/2 rounded-full bg-current/25 animate-ping" :style="rippleStyle"></span>
                </a>
                <a href="{{ $turunUrl }}" data-option="turun" @click.prevent="select($event)" class="relative flex min-h-[48px] items-center overflow-hidden px-4 py-3 text-sm transition {{ $tipeLelang === 'turun' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-700 hover:bg-gray-50' }}">
                    <span class="relative z-10">Lelang Turun</span>
                    <span x-show="rippleFor === 'turun'" x-transition.opacity.duration.150ms class="pointer-events-none absolute h-20 w-20 -translate-x-1/2 -translate-y-1/2 rounded-full bg-current/25 animate-ping" :style="rippleStyle"></span>
                </a>
            </div>
        </div>
    </div>
</div>
