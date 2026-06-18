<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Masuk dengan Google</h1>
        <p class="mt-1 text-sm text-slate-500">Akun Borgfish menggunakan Google sebagai login utama dan WhatsApp untuk verifikasi akun.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        <p class="font-bold text-slate-800">Password manual tidak digunakan</p>
        <p class="mt-2">Jika Anda ingin masuk kembali, gunakan tombol login Google. OTP WhatsApp hanya dipakai saat akun belum terverifikasi atau saat proses tertentu membutuhkan konfirmasi tambahan.</p>
    </div>

    <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-4 text-xs text-cyan-800">
        <p class="font-black">Langkah yang disarankan</p>
        <p class="mt-1 leading-relaxed">Kembali ke halaman login, pilih akun Google yang terhubung, lalu ikuti proses verifikasi jika sistem memintanya.</p>
    </div>

    <div class="mt-5 text-right">
        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 sm:w-auto">
            Buka Login
        </a>
    </div>
</x-guest-layout>
