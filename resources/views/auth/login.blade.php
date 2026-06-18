<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Masuk ke Borgfish</h1>
        <p class="mt-1 text-sm text-slate-500">Gunakan akun Google Anda untuk masuk. Akun yang sudah aktif akan langsung masuk tanpa OTP ulang.</p>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-2 sm:hidden">
        <a href="{{ route('login') }}" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-700">Masuk</a>
        <a href="{{ route('register') }}" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">Daftar</a>
    </div>

    <div class="space-y-4">
        <a href="{{ route('auth.google.redirect', ['flow' => 'login']) }}" class="inline-flex min-h-[54px] w-full items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-black text-slate-800 shadow-sm transition hover:bg-slate-50">
            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.6 3.9-5.4 3.9-3.2 0-5.9-2.7-5.9-6s2.7-6 5.9-6c1.8 0 3 .8 3.7 1.4l2.5-2.4C16.6 3.8 14.5 3 12 3 7 3 3 7 3 12s4 9 9 9c5.2 0 8.6-3.7 8.6-8.9 0-.6-.1-1.1-.1-1.9H12z"/>
            </svg>
            Pilih akun Google untuk masuk
        </a>

        <p class="text-sm text-slate-500">Belum punya akun? Daftar dulu sebagai pembeli atau penjual, lalu verifikasi WhatsApp saat aktivasi akun pertama.</p>
    </div>

    <div class="mt-5 text-center">
        <a class="text-sm font-semibold text-cyan-700 hover:text-cyan-800 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500" href="{{ route('register') }}">
            Belum punya akun? Daftar dulu
        </a>
    </div>
</x-guest-layout>
