<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Verifikasi Akun</h1>
        <p class="mt-1 text-sm text-slate-500">Borgfish menggunakan Google untuk login dan OTP WhatsApp untuk verifikasi akun bila diperlukan.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        <p class="font-bold text-slate-800">Tidak ada password manual</p>
        <p class="mt-2">Silakan login ulang dengan Google jika sistem meminta verifikasi ulang.</p>
    </div>

    <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-4 text-xs text-cyan-800">
        <p class="font-black">Verifikasi ulang akun</p>
        <p class="mt-1 leading-relaxed">Jika ada tindakan sensitif, sistem bisa meminta login ulang Google atau OTP WhatsApp sesuai kebutuhan keamanan.</p>
    </div>

    <div class="mt-5 text-right">
        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800 sm:w-auto">
            Login Ulang
        </a>
    </div>
</x-guest-layout>
