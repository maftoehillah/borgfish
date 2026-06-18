@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')
@php
    $activeFilter = $activeFilter ?? 'all';
    $summary = $summary ?? ['all' => 0, 'unread' => 0, 'read' => 0];
    $currentUrl = request()->fullUrl();

    $filterTabs = [
        [
            'key' => 'all',
            'label' => 'Semua',
            'short' => 'Semua',
            'count' => (int) ($summary['all'] ?? 0),
            'url' => route('notifications.index', ['filter' => 'all']),
        ],
        [
            'key' => 'unread',
            'label' => 'Belum Dibaca',
            'short' => 'Belum',
            'count' => (int) ($summary['unread'] ?? 0),
            'url' => route('notifications.index', ['filter' => 'unread']),
        ],
        [
            'key' => 'read',
            'label' => 'Sudah Dibaca',
            'short' => 'Dibaca',
            'count' => (int) ($summary['read'] ?? 0),
            'url' => route('notifications.index', ['filter' => 'read']),
        ],
    ];
@endphp

<section class="rounded-3xl border border-cyan-100 bg-gradient-to-br from-cyan-50 via-sky-50 to-emerald-50 px-5 py-6 sm:px-8 sm:py-9 mb-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-cyan-700 bg-cyan-100/80 border border-cyan-200/80">
                Notifikasi
            </p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Notifikasi</h1>
            <p class="mt-2 text-slate-600 max-w-2xl">Semua update penting akun dan transaksi Anda.</p>
        </div>

        @if((int) ($summary['unread'] ?? 0) > 0)
            <form method="POST" action="{{ route('notifications.read_all') }}" class="shrink-0">
                @csrf
                <input type="hidden" name="return_url" value="{{ $currentUrl }}">
                <button type="submit" class="inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-cyan-700 px-4 py-3 text-sm font-extrabold tracking-wide text-white transition hover:bg-cyan-800 sm:w-auto">
                    Tandai Semua Dibaca
                </button>
            </form>
        @endif
    </div>

    <div class="mt-6 flex gap-3 overflow-x-auto pb-1 sm:grid sm:grid-cols-3 sm:overflow-visible sm:pb-0">
        <div class="min-w-[150px] rounded-2xl border border-white/70 bg-white/90 p-4 sm:min-w-0">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 font-bold">Total Notifikasi</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format((int) ($summary['all'] ?? 0)) }}</p>
        </div>
        <div class="min-w-[150px] rounded-2xl border border-white/70 bg-white/90 p-4 sm:min-w-0">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 font-bold">Belum Dibaca</p>
            <p class="mt-1 text-2xl font-black text-rose-700">{{ number_format((int) ($summary['unread'] ?? 0)) }}</p>
        </div>
        <div class="min-w-[150px] rounded-2xl border border-white/70 bg-white/90 p-4 sm:min-w-0">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 font-bold">Sudah Dibaca</p>
            <p class="mt-1 text-2xl font-black text-emerald-700">{{ number_format((int) ($summary['read'] ?? 0)) }}</p>
        </div>
    </div>

    <div class="mt-5 sm:hidden">
        <div class="rounded-2xl border border-white/70 bg-white/90 p-2">
            <div class="grid grid-cols-3 gap-2">
                @foreach($filterTabs as $tab)
                    <a
                        href="{{ $tab['url'] }}"
                        class="inline-flex min-h-[48px] min-w-0 items-center justify-center gap-1.5 rounded-xl border px-2 py-2.5 text-[11px] font-extrabold transition {{ $activeFilter === $tab['key'] ? 'bg-cyan-100 text-cyan-800 border-cyan-200' : 'bg-white text-slate-700 border-slate-200' }}"
                        @if($activeFilter === $tab['key']) aria-current="page" @endif
                    >
                        <span class="truncate">{{ $tab['short'] }}</span>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] {{ $activeFilter === $tab['key'] ? 'bg-cyan-700 text-white' : 'bg-slate-100 text-slate-600' }}">
                            {{ number_format($tab['count']) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="bg-white rounded-2xl border border-slate-200 overflow-hidden xl:rounded-3xl">
    <div class="hidden sm:flex xl:hidden px-5 py-4 border-b border-slate-100 flex-wrap items-center gap-2">
        @foreach($filterTabs as $tab)
            <a
                href="{{ $tab['url'] }}"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs sm:text-sm font-bold border transition {{ $activeFilter === $tab['key'] ? 'bg-cyan-100 text-cyan-800 border-cyan-200' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}"
                @if($activeFilter === $tab['key']) aria-current="page" @endif
            >
                <span>{{ $tab['label'] }}</span>
                <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full text-[11px] {{ $activeFilter === $tab['key'] ? 'bg-cyan-700 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ number_format($tab['count']) }}
                </span>
            </a>
        @endforeach
    </div>

    @if($notifications->isEmpty())
        <div class="px-5 py-14 text-center">
            <h2 class="text-lg font-black text-slate-800">Belum Ada Notifikasi</h2>
            <p class="mt-2 text-sm text-slate-500">Tidak ada notifikasi pada filter ini.</p>
        </div>
    @else
        <div class="hidden xl:grid xl:grid-cols-[0.32fr,1fr] border-b border-slate-100">
            <div class="px-5 py-5 border-r border-slate-100 bg-slate-50/80">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-slate-400">Filter Desktop</p>
                <div class="mt-4 space-y-2">
                    @foreach($filterTabs as $tab)
                        <a
                            href="{{ $tab['url'] }}"
                            class="flex items-center justify-between rounded-2xl border px-4 py-3 text-sm font-bold transition {{ $activeFilter === $tab['key'] ? 'bg-cyan-50 text-cyan-800 border-cyan-200' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}"
                            @if($activeFilter === $tab['key']) aria-current="page" @endif
                        >
                            <span>{{ $tab['label'] }}</span>
                            <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 rounded-full text-[11px] {{ $activeFilter === $tab['key'] ? 'bg-cyan-700 text-white' : 'bg-slate-100 text-slate-600' }}">
                                {{ number_format($tab['count']) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="px-5 py-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-slate-900">
                        {{ $activeFilter === 'unread' ? 'Notifikasi Belum Dibaca' : ($activeFilter === 'read' ? 'Riwayat Notifikasi Dibaca' : 'Semua Notifikasi') }}
                    </p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Pilih filter yang ingin ditampilkan.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                    {{ $notifications->total() }} item
                </span>
            </div>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach($notifications as $notification)
                @php
                    $payloadTransaksiId = data_get($notification->payload, 'transaksi_id');
                    $isUnread = $notification->read_at === null;
                @endphp
                <li class="px-4 py-4 sm:px-5 xl:px-6 {{ $isUnread ? 'bg-rose-50/20' : 'bg-white' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm sm:text-base font-black text-slate-900">{{ $notification->title }}</p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $isUnread ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $isUnread ? 'Belum dibaca' : 'Sudah dibaca' }}
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">
                                    {{ notificationCategoryLabel($notification->category) }}
                                </span>
                                @if($payloadTransaksiId)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-cyan-100 text-cyan-700">
                                        Trx #{{ $payloadTransaksiId }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-slate-700">{{ $notification->message }}</p>
                            <p class="mt-2 text-[11px] font-semibold text-slate-500">{{ $notification->created_at?->isoFormat('dddd, D MMMM YYYY HH:mm') }}</p>
                        </div>

                        @if($isUnread)
                            <div class="shrink-0 flex w-full sm:w-auto items-stretch gap-2">
                                <a href="{{ route('notifications.open', $notification) }}" class="inline-flex min-h-[48px] flex-1 sm:flex-none items-center justify-center rounded-xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white transition hover:bg-cyan-800">
                                    Buka Detail
                                </a>
                                <form method="POST" action="{{ route('notifications.read', $notification) }}" class="flex-1 sm:flex-none">
                                    @csrf
                                    <input type="hidden" name="return_url" value="{{ $currentUrl }}">
                                    <button type="submit" class="inline-flex min-h-[48px] w-full items-center justify-center rounded-xl border border-cyan-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 transition hover:bg-cyan-50">
                                        Tandai Dibaca
                                    </button>
                                </form>
                            </div>
                        @else
                            <x-secondary-action-link :href="route('notifications.open', $notification)" class="shrink-0 rounded-xl px-4 py-3 text-sm">
                                Buka Detail
                            </x-secondary-action-link>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="px-5 py-4 border-t border-slate-100">
            {{ $notifications->links() }}
        </div>
    @endif
</section>
@endsection
