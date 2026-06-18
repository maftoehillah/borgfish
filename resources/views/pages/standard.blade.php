@extends('layouts.app')
@section('title', $title . ' - Borgfish')

@section('content')
@php
    $siteName = $settings['site_name'] ?? 'Borgfish';
    $address = $settings['site_address'] ?? 'Indonesia';
    $email = $settings['site_email'] ?? 'admin@borgfish.test';

    $settingParagraphs = function (?string $value, array $fallback): array {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        return collect(preg_split('/\R+/', $value))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    };

    $body = match ($slug) {
        'tentang-kami' => $settingParagraphs($settings['about_text'] ?? null, [
            'Borgfish adalah marketplace lelang ikan online yang mempertemukan penjual dan pembeli dalam proses bidding yang transparan, tercatat, dan dipantau admin.',
            'Borgfish bertindak sebagai merchant penerima pembayaran transaksi marketplace. Pembeli membayar invoice melalui payment gateway resmi, lalu tim Borgfish memantau status pembayaran, packing, penjemputan, dan penyelesaian transaksi sampai selesai.',
            'Dana penjualan tidak langsung diteruskan saat invoice dibayar. Penyelesaian dana kepada penjual dilakukan setelah transaksi dinyatakan selesai, atau setelah sengketa dinyatakan clear sesuai kebijakan operasional Borgfish.',
        ]),
        'kontak' => $settingParagraphs($settings['contact_page'] ?? null, [
            'Hubungi admin Borgfish untuk bantuan akun, pembayaran, transaksi, sengketa, pelanggaran, atau kebutuhan operasional marketplace.',
            'Tim support membantu konfirmasi pembayaran, pengecekan status transaksi, koordinasi sengketa, dan informasi penyelesaian dana penjual pada hari kerja.',
            'Semua aktivitas penting seperti login, bid, pembuatan invoice, callback payment gateway, dan perubahan status transaksi dicatat untuk menjaga keamanan marketplace.',
        ]),
        'kebijakan-privasi' => $settingParagraphs($settings['privacy_policy'] ?? null, [
            'Kami menyimpan data akun, nomor WhatsApp, data toko, aktivitas bid, transaksi, pembayaran, penjemputan, dan log sistem hanya untuk kebutuhan operasional marketplace.',
            'Data pengguna dilindungi melalui autentikasi Google, verifikasi OTP WhatsApp, validasi input, kontrol akses berbasis role, dan pencatatan aktivitas penting.',
        ]),
        'kebijakan-pembayaran' => $settingParagraphs($settings['payment_policy'] ?? null, [
            'Pembayaran invoice marketplace diproses atas nama Borgfish sebagai merchant penerima pembayaran. Metode pembayaran yang tersedia mengikuti kanal resmi payment gateway yang aktif pada halaman checkout.',
            'Dana dari pembeli digunakan untuk menyelesaikan transaksi marketplace terkait. Dana tidak dianggap final untuk penjual sampai transaksi selesai, pembeli mengonfirmasi barang diterima, atau admin menyatakan sengketa selesai.',
            'Penyelesaian dana penjual dilakukan ke rekening bank penjual yang telah diverifikasi di sistem Borgfish. Target operasional penyelesaian dana adalah maksimal H+2 hari kerja setelah transaksi selesai dan tidak berada dalam status sengketa.',
            'Jika terjadi komplain, mismatch penjemputan, dugaan barang tidak sesuai, atau pelanggaran operasional lain, Borgfish berhak menahan penyelesaian dana sementara sampai proses peninjauan admin selesai.',
            'Refund atau pengembalian dana kepada pembeli dilakukan berdasarkan hasil peninjauan admin dan status transaksi pada payment gateway. Permintaan refund yang disetujui akan dicatat pada sistem dan diproses sesuai prosedur operasional Borgfish.',
        ]),
        default => $settingParagraphs($settings['terms_conditions'] ?? null, [
            'Pengguna wajib mengikuti proses lelang secara jujur, membayar kemenangan sebelum tenggat, dan memberikan data penjemputan yang benar.',
            'Pembeli memahami bahwa pembayaran invoice dilakukan ke merchant Borgfish untuk menyelesaikan transaksi marketplace, bukan transfer langsung ke penjual perorangan.',
            'Penjual memahami bahwa penyelesaian dana dilakukan setelah transaksi selesai atau setelah sengketa dinyatakan clear, ke rekening bank yang telah diverifikasi pada profil penjual.',
            'Admin berhak meninjau transaksi, menahan penyelesaian dana, mencatat pelanggaran, melakukan suspend sementara, ban permanen, refund, atau keputusan operasional lain sesuai bukti dan kebijakan marketplace.',
        ]),
    };
@endphp

<section class="rounded-3xl border border-cyan-100/70 px-6 py-7 sm:px-8 sm:py-9 mb-8 bg-white">
    <p class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-cyan-700 bg-cyan-100/70 border border-cyan-200/70">
        {{ $siteName }}
    </p>
    <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">{{ $title }}</h1>
    <p class="mt-2 text-slate-600 max-w-2xl">Informasi resmi website dan kebijakan operasional Borgfish.</p>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <article class="lg:col-span-2 rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        @foreach($body as $paragraph)
            <p class="text-sm leading-7 text-slate-700">{{ $paragraph }}</p>
        @endforeach
    </article>

    <aside class="rounded-3xl border border-slate-200 bg-white p-6 h-fit">
        <h2 class="font-black text-slate-900">Informasi Website</h2>
        <div class="mt-4 space-y-3 text-sm">
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                <p class="text-xs text-slate-500">Alamat</p>
                <p class="break-words font-bold text-slate-800">{{ $address }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                <p class="text-xs text-slate-500">Email</p>
                <p class="break-all font-bold text-slate-800">{{ $email }}</p>
            </div>
        </div>

        @if($slug === 'kontak')
            <div class="mt-5 space-y-3">
                <x-whatsapp-contact
                    :user-name="auth()->check() ? auth()->user()->name : null"
                    :user-email="auth()->check() ? auth()->user()->email : null"
                    :user-phone="auth()->check() ? auth()->user()->whatsapp_number : null"
                    :user-role="auth()->check() ? auth()->user()->displayRoleLabel() : null"
                />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Social Media</p>
                    <div class="mt-3">
                        <x-social-links variant="compact" />
                    </div>
                </div>
            </div>
        @endif
    </aside>
</div>
@endsection
