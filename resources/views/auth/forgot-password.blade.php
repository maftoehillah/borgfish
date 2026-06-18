<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Masuk dengan Google</h1>
        <p class="mt-1 text-sm text-slate-500">Borgfish tidak menggunakan password manual untuk pembeli dan penjual.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        <p class="font-bold text-slate-800">Tidak perlu reset password</p>
        <p class="mt-2">Silakan masuk menggunakan akun Google yang terhubung dengan Borgfish. Jika akun Google Anda bermasalah, pulihkan melalui halaman akun Google.</p>
    </div>

    <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-4 text-xs text-cyan-800">
        <p class="font-black">Butuh akses lagi?</p>
        <p class="mt-1 leading-relaxed">Pastikan Anda memakai akun Google yang sama seperti saat pendaftaran. Jika belum punya akun Borgfish, buat akun baru lalu lanjut verifikasi WhatsApp.</p>
    </div>

    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 sm:w-auto">
            Buka Login
        </a>
        <a href="{{ route('register') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-200 bg-white px-5 py-3 text-sm font-bold text-cyan-700 transition hover:bg-cyan-50 sm:w-auto">
            Buat Akun Baru
        </a>
    </div>
</x-guest-layout>
