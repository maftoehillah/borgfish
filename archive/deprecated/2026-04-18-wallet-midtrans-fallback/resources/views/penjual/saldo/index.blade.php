@extends('layouts.app')
@section('title', 'Dana Penjual')

@section('content')
@php
    $entryLabels = [
        'escrow_release_credit' => 'Escrow Masuk Saldo',
        'withdraw_request_locked' => 'Request Withdraw',
        'withdraw_paid' => 'Withdraw Dibayar',
        'withdraw_rejected' => 'Withdraw Ditolak',
    ];

    $entryBadges = [
        'escrow_release_credit' => 'bg-emerald-100 text-emerald-700',
        'withdraw_request_locked' => 'bg-amber-100 text-amber-700',
        'withdraw_paid' => 'bg-violet-100 text-violet-700',
        'withdraw_rejected' => 'bg-rose-100 text-rose-700',
    ];

    $withdrawalBadges = [
        'pending' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-cyan-100 text-cyan-700',
        'paid' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
    ];


@endphp

<style>
    .seller-wallet-hero {
        background:
            radial-gradient(circle at 12% 12%, rgba(99, 102, 241, 0.12), transparent 32%),
            radial-gradient(circle at 88% 8%, rgba(16, 185, 129, 0.14), transparent 34%),
            linear-gradient(145deg, #f8fcff 0%, #f5f7ff 44%, #fbfdff 100%);
    }

    .seller-wallet-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 18px 30px -26px rgba(15, 23, 42, 0.65);
    }
</style>

<div class="max-w-7xl mx-auto">
    <section class="seller-wallet-hero rounded-3xl border border-violet-100/80 px-6 py-6 sm:px-8 sm:py-8 mb-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-xs font-extrabold uppercase tracking-[0.28em] text-violet-700 shadow-sm">
                    Wallet Seller
                </p>
                <h1 class="mt-4 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Dana penjual & pencairan</h1>
                <p class="mt-2 max-w-3xl text-sm sm:text-base text-slate-600">
                    Dana hasil lelang masuk otomatis ke saldo seller saat escrow dilepas. Dari sini seller bisa melihat mutasi, mengirim request withdraw, dan memantau riwayat payout.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('penjual.ikans.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    Kembali ke dashboard
                </a>
                <a href="{{ route('penjual.ikans.create', ['return_url' => request()->fullUrl()]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                    Upload ikan
                </a>
            </div>
        </div>
    </section>

    <section class="seller-wallet-surface rounded-3xl bg-white p-6 sm:p-7 mb-6">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-amber-50 px-4 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Dana masih di escrow</p>
                <p class="mt-2 text-2xl font-black text-amber-700">{{ formatRupiah($escrowHeldAmount) }}</p>
                <p class="mt-1 text-xs text-slate-500">Belum masuk saldo seller sampai transaksi selesai.</p>
            </div>
            <div class="rounded-2xl bg-violet-50 px-4 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo siap ditarik</p>
                <p class="mt-2 text-2xl font-black text-violet-800">{{ formatRupiah($seller->sellerSaldoTersedia()) }}</p>
                <p class="mt-1 text-xs text-slate-500">Dana yang sudah bisa dimasukkan ke request withdraw.</p>
            </div>
            <div class="rounded-2xl bg-cyan-50 px-4 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pending withdraw</p>
                <p class="mt-2 text-2xl font-black text-cyan-800">{{ formatRupiah($seller->sellerSaldoPendingWithdrawal()) }}</p>
                <p class="mt-1 text-xs text-slate-500">Dana yang sedang diproses admin untuk pencairan.</p>
            </div>
            <div class="rounded-2xl bg-slate-50 px-4 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total dana seller</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ formatRupiah($seller->sellerTotalDana()) }}</p>
                <p class="mt-1 text-xs text-slate-500">Gabungan saldo siap tarik dan pending withdraw.</p>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(340px,1fr)]">
        <div class="space-y-6">
            <section class="seller-wallet-surface rounded-3xl bg-white p-6 sm:p-7">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Request pencairan</h2>
                        <p class="text-sm text-slate-500">Isi rekening tujuan. Dana akan dipindahkan ke antrean withdraw dan menunggu approval admin.</p>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wide text-violet-500">Tersedia {{ formatRupiah($seller->sellerSaldoTersedia()) }}</span>
                </div>

                <form id="withdraw-form" action="{{ route('penjual.saldo.withdrawals.store') }}" method="POST" class="mt-5 grid gap-4" novalidate>
                    @csrf
                    @php
                        // Prefill the withdraw input rounded down to the nearest 1,000
                        $roundedAmount = $seller->sellerSaldoTersedia() >= 1000 ? (int) (floor($seller->sellerSaldoTersedia() / 1000) * 1000) : null;
                        $prefill = old('amount', $roundedAmount !== null ? (string) $roundedAmount : ($seller->sellerSaldoTersedia() > 0 ? (string) ((int) round($seller->sellerSaldoTersedia())) : ''));
                    @endphp

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount" class="text-sm font-bold text-slate-700">Nominal withdraw</label>
                            <input
                                id="amount"
                                type="number"
                                name="amount"
                                min="0"
                                step="1000"
                                value="{{ $prefill }}"
                                data-available="{{ $roundedAmount ?? 0 }}"
                                data-raw-available="{{ $seller->sellerSaldoTersedia() }}"
                                @if($roundedAmount)
                                    max="{{ $roundedAmount }}"
                                @endif
                                class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                placeholder="contoh: 250000"
                            >

                            <div class="mt-2 flex items-center gap-3">
                                <button type="button" id="withdraw-all" class="text-xs inline-flex items-center font-semibold px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Tarik Semua</button>
                                <p id="withdraw-helper" class="text-xs text-slate-500">Masukkan kelipatan Rp1.000 · Biaya: Rp0 · Proses: 1–2 hari kerja</p>
                            </div>
                            <p id="withdraw-error" class="mt-2 text-xs text-rose-600 hidden" role="alert"></p>
                        </div>
                        <div>
                            <label for="bank_name" class="text-sm font-bold text-slate-700">Bank tujuan</label>
                            <input
                                id="bank_name"
                                type="text"
                                name="bank_name"
                                value="{{ old('bank_name') }}"
                                class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                placeholder="contoh: BCA"
                            >
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="account_number" class="text-sm font-bold text-slate-700">Nomor rekening</label>
                            <input
                                id="account_number"
                                type="text"
                                name="account_number"
                                value="{{ old('account_number') }}"
                                class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                placeholder="angka saja"
                            >
                        </div>
                        <div>
                            <label for="account_holder_name" class="text-sm font-bold text-slate-700">Nama pemilik rekening</label>
                            <input
                                id="account_holder_name"
                                type="text"
                                name="account_holder_name"
                                value="{{ old('account_holder_name', $seller->name) }}"
                                class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                placeholder="sesuai buku rekening"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="seller_note" class="text-sm font-bold text-slate-700">Catatan untuk admin</label>
                        <textarea
                            id="seller_note"
                            name="seller_note"
                            rows="3"
                            class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-100"
                            placeholder="opsional, misalnya bank prioritas atau catatan operasional"
                        >{{ old('seller_note') }}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button id="withdraw-submit" type="submit" class="inline-flex items-center justify-center rounded-2xl bg-violet-700 px-5 py-3 text-sm font-black text-white transition hover:bg-violet-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Kirim request withdraw
                        </button>
                        <p class="text-xs text-slate-500">Setelah dikirim, nominal akan pindah dari saldo siap tarik ke pending withdraw sampai admin approve atau reject.</p>
                    </div>
                </form>

                <div x-data="{ openEscrow: false }">
                    <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row items-center gap-4">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/payments/midtrans.png') }}" alt="Midtrans" class="h-6" onerror="this.style.display='none'">
                            <img src="{{ asset('images/payments/bca.png') }}" alt="BCA" class="h-6" onerror="this.style.display='none'">
                            <img src="{{ asset('images/payments/mandiri.png') }}" alt="Mandiri" class="h-6" onerror="this.style.display='none'">
                        </div>

                        <div class="text-sm text-slate-600 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-800">Keamanan & Pencairan</p>
                                    <p class="mt-1">Pencairan dilakukan melalui penyedia pembayaran terpercaya. Dana seller terlindungi oleh sistem escrow kami — dana hanya dilepas setelah transaksi selesai atau sesuai SLA.</p>
                                    <p class="mt-1 text-xs text-slate-500">Estimasi: 1–2 hari kerja · Biaya admin: Rp0</p>
                                </div>
                                <div class="shrink-0">
                                    <button id="open-escrow-btn" type="button" @click="openEscrow = true" aria-controls="escrow-modal-panel" aria-expanded="false" class="text-xs font-semibold text-cyan-700 hover:underline">Pelajari cara kerja escrow</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-cloak x-show="openEscrow" @keydown.escape.window="openEscrow = false" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                        <div class="fixed inset-0 bg-black/50" @click="openEscrow = false"></div>

                        <div id="escrow-modal-panel" role="dialog" aria-modal="true" aria-labelledby="escrow-modal-title" tabindex="-1" class="panel-surface z-50 w-full max-w-2xl mx-auto rounded-2xl bg-white shadow-xl p-6">
                                <div class="flex items-start justify-between">
                                <h3 id="escrow-modal-title" class="text-lg font-bold text-slate-900">Cara Kerja Escrow</h3>
                                <button type="button" @click="openEscrow = false" aria-label="Tutup dialog" class="text-slate-500 hover:text-slate-700">&times;</button>
                            </div>

                            <div class="mt-4 space-y-3 text-sm text-slate-700">
                                <p><strong>1.</strong> Pembeli melakukan pembayaran ke sistem kami. Dana disimpan aman dalam escrow sampai transaksi selesai.</p>
                                <p><strong>2.</strong> Penjual mengirim barang dan pembeli mengonfirmasi penerimaan.</p>
                                <p><strong>3.</strong> Setelah konfirmasi atau tercapai SLA, sistem melepas dana ke saldo penjual.</p>
                                <p><strong>4.</strong> Penjual dapat membuat request withdraw dari saldo yang tersedia; admin akan memproses pencairan sesuai kebijakan.</p>
                                <p><strong>5.</strong> Jika terjadi sengketa, tim support meninjau bukti transaksi dan menahan atau mengembalikan dana sesuai hasil penyelidikan.</p>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="button" @click="openEscrow = false" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 font-semibold">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    (function(){
                        const form = document.getElementById('withdraw-form');
                        if (!form) return;

                        const amountInput = document.getElementById('amount');
                        const withdrawAllBtn = document.getElementById('withdraw-all');
                        const helperEl = document.getElementById('withdraw-helper');
                        const errorEl = document.getElementById('withdraw-error');
                        const submitBtn = document.getElementById('withdraw-submit');

                        const MIN_WITHDRAW = 1000;

                        function formatRupiah(v){
                            try{ return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }
                            catch(e){ return 'Rp ' + (v||0).toString(); }
                        }

                        function getAvailableRounded(){
                            const v = parseInt(amountInput.dataset.available || '0', 10);
                            return Number.isFinite(v) ? v : 0;
                        }

                        function getAvailableRaw(){
                            const v = parseFloat(amountInput.dataset.rawAvailable || '0');
                            return Number.isFinite(v) ? v : 0;
                        }

                        function setError(msg){
                            if(!msg){
                                errorEl.classList.add('hidden');
                                errorEl.textContent = '';
                            } else {
                                errorEl.classList.remove('hidden');
                                errorEl.textContent = msg;
                            }
                        }

                        function validate(){
                            const availRounded = getAvailableRounded();
                            const availRaw = getAvailableRaw();

                            if (availRounded < MIN_WITHDRAW) {
                                // not enough funds
                                setError('Saldo kurang dari ' + formatRupiah(MIN_WITHDRAW) + '. Tidak dapat melakukan penarikan.');
                                submitBtn.disabled = true;
                                return false;
                            }

                            const raw = amountInput.value ? String(amountInput.value).replace(/[^0-9]/g, '') : '';
                            const val = raw === '' ? NaN : parseInt(raw, 10);

                            if (Number.isNaN(val) || val <= 0) {
                                setError('Masukkan jumlah withdraw yang valid.');
                                submitBtn.disabled = true;
                                return false;
                            }

                            if (val % 1000 !== 0) {
                                setError('Masukkan kelipatan Rp1.000.');
                                submitBtn.disabled = true;
                                return false;
                            }

                            if (val > availRounded) {
                                setError('Maksimum withdraw adalah ' + formatRupiah(availRounded) + '.');
                                submitBtn.disabled = true;
                                return false;
                            }

                            // valid
                            setError('');
                            submitBtn.disabled = false;
                            return true;
                        }

                        // initial state
                        document.addEventListener('DOMContentLoaded', function(){
                            // show available in helper
                            const availRounded = getAvailableRounded();
                            const availRaw = getAvailableRaw();
                            if (availRounded && helperEl) {
                                helperEl.textContent = 'Maks: ' + formatRupiah(availRounded) + ' · Kelipatan Rp1.000 · Biaya: Rp0 · Proses: 1–2 hari kerja';
                            } else if (helperEl) {
                                helperEl.textContent = 'Saldo kurang dari ' + formatRupiah(MIN_WITHDRAW) + ' — tidak tersedia pencairan.';
                            }

                            validate();
                        });

                        amountInput.addEventListener('input', function(){
                            // remove any leading zeros
                            if (this.value && this.value.length > 1 && this.value[0] === '0') {
                                this.value = String(parseInt(this.value, 10) || '');
                            }
                            validate();
                        });

                        withdrawAllBtn.addEventListener('click', function(){
                            const avail = getAvailableRounded();
                            if (!avail || avail < MIN_WITHDRAW) return;
                            amountInput.value = avail;
                            validate();
                        });

                        // prevent submit if invalid
                        form.addEventListener('submit', function(e){
                            if (!validate()) {
                                e.preventDefault();
                                amountInput.focus();
                                return false;
                            }
                            // else allow submit
                        });
                    })();
                </script>
                <script>
                    (function(){
                        const openBtn = document.getElementById('open-escrow-btn');
                        const modalPanel = document.getElementById('escrow-modal-panel');
                        if (!openBtn || !modalPanel) return;

                        let lastFocused = null;
                        let focusable = [];
                        let firstFocusable = null;
                        let lastFocusable = null;
                        let onKeyDown = null;
                        const parent = modalPanel.parentElement;

                        function updateFocusable() {
                            focusable = Array.from(modalPanel.querySelectorAll('a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'));
                            firstFocusable = focusable[0] || modalPanel;
                            lastFocusable = focusable[focusable.length -1] || modalPanel;
                        }

                        function trap(e) {
                            if (e.key !== 'Tab') return;
                            updateFocusable();
                            const active = document.activeElement;
                            if (e.shiftKey) {
                                if (active === firstFocusable) {
                                    e.preventDefault();
                                    (lastFocusable || modalPanel).focus();
                                }
                            } else {
                                if (active === lastFocusable) {
                                    e.preventDefault();
                                    (firstFocusable || modalPanel).focus();
                                }
                            }
                        }

                        function onDocKeydown(e){
                            if (e.key === 'Escape') {
                                // find close button and click it so Alpine handles state
                                const closeBtn = modalPanel.querySelector('button[aria-label="Tutup dialog"]');
                                if (closeBtn) closeBtn.click();
                                else closeHandler();
                            }
                        }

                        function openHandler(){
                            lastFocused = document.activeElement;
                            openBtn.setAttribute('aria-expanded', 'true');
                            setTimeout(function(){
                                try{ modalPanel.focus(); } catch(e){}
                                updateFocusable();
                                onKeyDown = trap;
                                modalPanel.addEventListener('keydown', onKeyDown);
                                document.addEventListener('keydown', onDocKeydown);
                            }, 120);
                        }

                        function closeHandler(){
                            openBtn.setAttribute('aria-expanded', 'false');
                            if (onKeyDown) {
                                modalPanel.removeEventListener('keydown', onKeyDown);
                                onKeyDown = null;
                            }
                            document.removeEventListener('keydown', onDocKeydown);
                            if (lastFocused && typeof lastFocused.focus === 'function') {
                                lastFocused.focus();
                            }
                        }

                        openBtn.addEventListener('click', openHandler);

                        const closeBtn = modalPanel.querySelector('button[aria-label="Tutup dialog"]');
                        if (closeBtn) closeBtn.addEventListener('click', closeHandler);

                        // overlay is expected to be previous sibling in markup
                        const overlay = modalPanel.previousElementSibling;
                        if (overlay) overlay.addEventListener('click', function(){
                            // overlay click will trigger Alpine to close; restore focus anyway
                            closeHandler();
                        });

                        // Watch for hidden state changes (Alpine x-show toggles display)
                        if (parent && window.MutationObserver) {
                            const observer = new MutationObserver(function(){
                                if (modalPanel.offsetParent === null) {
                                    closeHandler();
                                }
                            });
                            observer.observe(parent, { attributes: true, attributeFilter: ['style', 'class'] });
                        }

                        // For automated scans: allow opening modal automatically
                        // when URL contains `open_escrow` query (e.g. ?open_escrow=1)
                        try {
                            if (window.location.search && window.location.search.indexOf('open_escrow') !== -1) {
                                setTimeout(function(){ openBtn.click(); }, 220);
                            }
                        } catch (e) { /* ignore */ }
                    })();
                </script>
            </section>

            <section class="seller-wallet-surface rounded-3xl bg-white p-6 sm:p-7">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Mutasi dana seller</h2>
                        <p class="text-sm text-slate-500">Semua perubahan saldo seller tercatat di sini: escrow release, request withdraw, payout selesai, dan pengembalian dana jika ditolak.</p>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-400">
                        @if($ledgers instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            {{ $ledgers->total() }} total entri
                        @else
                            {{ is_countable($ledgers) ? count($ledgers) : 0 }} total entri
                        @endif
                    </span>
                </div>

                @if($ledgers->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Belum ada mutasi dana seller.
                    </div>
                @else
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200" aria-describedby="mutasi-desc">
                            <caption id="mutasi-desc" class="sr-only">Mutasi dana seller - daftar perubahan saldo, tersedia dan pending</caption>
                            <thead>
                                <tr class="text-left text-xs font-extrabold uppercase tracking-wide text-slate-500">
                                    <th scope="col" class="pb-3 pr-4">Waktu</th>
                                    <th scope="col" class="pb-3 pr-4">Jenis</th>
                                    <th scope="col" class="pb-3 pr-4">Tersedia</th>
                                    <th scope="col" class="pb-3 pr-4">Pending</th>
                                    <th scope="col" class="pb-3 pr-4">Saldo akhir</th>
                                    <th scope="col" class="pb-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($ledgers as $ledger)
                                    <tr class="align-top">
                                        <td class="py-4 pr-4 text-slate-600">
                                            <p class="font-bold text-slate-900">{{ $ledger->created_at?->format('d M Y') }}</p>
                                            <p class="text-xs text-slate-500">{{ $ledger->created_at?->format('H:i') }}</p>
                                        </td>
                                        <td class="py-4 pr-4">
                                            <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-black {{ $entryBadges[$ledger->entry_type] ?? 'bg-slate-100 text-slate-700' }}">
                                                {{ $entryLabels[$ledger->entry_type] ?? ucwords(str_replace('_', ' ', $ledger->entry_type)) }}
                                            </span>
                                        </td>
                                        <td class="py-4 pr-4 font-black {{ (float) $ledger->available_delta >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                            {{ (float) $ledger->available_delta >= 0 ? '+' : '' }}{{ formatRupiah($ledger->available_delta) }}
                                        </td>
                                        <td class="py-4 pr-4 font-black {{ (float) $ledger->pending_delta >= 0 ? 'text-amber-700' : 'text-slate-700' }}">
                                            {{ (float) $ledger->pending_delta >= 0 ? '+' : '' }}{{ formatRupiah($ledger->pending_delta) }}
                                        </td>
                                        <td class="py-4 pr-4 text-slate-700">
                                            <p class="font-black text-slate-900">{{ formatRupiah($ledger->balance_after) }}</p>
                                            <p class="text-xs text-slate-500">Pending {{ formatRupiah($ledger->pending_after) }}</p>
                                        </td>
                                        <td class="py-4 text-slate-600">
                                            <p class="font-semibold text-slate-800">{{ $ledger->note ?: '-' }}</p>
                                            @if($ledger->reference_type && $ledger->reference_id)
                                                <p class="mt-1 text-xs text-slate-500">Ref: {{ $ledger->reference_type }} #{{ $ledger->reference_id }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($ledgers, 'links'))
                        <div class="mt-6">
                            {{ $ledgers->onEachSide(1)->links() }}
                        </div>
                    @endif
                @endif
            </section>
        </div>

        <aside id="riwayat-payout" class="space-y-6 scroll-mt-24">
            <section class="seller-wallet-surface rounded-3xl bg-white p-6">
                <h2 class="text-lg font-black text-slate-900">Riwayat payout</h2>
                <p class="mt-1 text-sm text-slate-500">Lihat status request withdraw seller dari pending sampai dibayar admin.</p>

                @if($withdrawals->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Belum ada riwayat payout seller.
                    </div>
                @else
                    <div class="mt-5 space-y-3">
                        @foreach($withdrawals as $withdrawal)
                            <article class="rounded-2xl border px-4 py-4 {{ $highlightWithdrawalId === (int) $withdrawal->id ? 'border-violet-300 bg-violet-50/70' : 'border-slate-200 bg-white' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ formatRupiah($withdrawal->amount) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Request #{{ $withdrawal->id }} · {{ $withdrawal->requested_at?->format('d M Y H:i') ?? $withdrawal->created_at?->format('d M Y H:i') }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-black {{ $withdrawalBadges[$withdrawal->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ strtoupper($withdrawal->status) }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-1 text-xs text-slate-600">
                                    <p><span class="font-bold text-slate-800">Rekening:</span> {{ $withdrawal->bank_name }} · {{ $withdrawal->account_number }}</p>
                                    <p><span class="font-bold text-slate-800">Atas nama:</span> {{ $withdrawal->account_holder_name }}</p>
                                    @if($withdrawal->review_note)
                                        <p><span class="font-bold text-slate-800">Catatan admin:</span> {{ $withdrawal->review_note }}</p>
                                    @elseif($withdrawal->seller_note)
                                        <p><span class="font-bold text-slate-800">Catatan seller:</span> {{ $withdrawal->seller_note }}</p>
                                    @endif
                                    @if($withdrawal->transfer_reference)
                                        <p><span class="font-bold text-slate-800">Referensi transfer:</span> {{ $withdrawal->transfer_reference }}</p>
                                    @endif
                                    @if($withdrawal->approved_at)
                                        <p><span class="font-bold text-slate-800">Disetujui:</span> {{ $withdrawal->approved_at->format('d M Y H:i') }}</p>
                                    @endif
                                    @if($withdrawal->paid_at)
                                        <p><span class="font-bold text-slate-800">Dibayar:</span> {{ $withdrawal->paid_at->format('d M Y H:i') }}</p>
                                    @endif
                                    @if($withdrawal->rejected_at)
                                        <p><span class="font-bold text-slate-800">Ditolak:</span> {{ $withdrawal->rejected_at->format('d M Y H:i') }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $withdrawals->onEachSide(1)->links() }}
                    </div>
                @endif
            </section>

            <section class="seller-wallet-surface rounded-3xl bg-white p-6">
                <h2 class="text-lg font-black text-slate-900">Cara kerja dana seller</h2>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <p><span class="font-black text-amber-700">Dana di escrow</span> masih terkunci sampai buyer konfirmasi atau SLA selesai.</p>
                    <p><span class="font-black text-violet-700">Saldo siap ditarik</span> muncul otomatis saat escrow dilepas.</p>
                    <p><span class="font-black text-cyan-700">Pending withdraw</span> berarti admin sedang review atau memproses transfer ke rekening seller.</p>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
