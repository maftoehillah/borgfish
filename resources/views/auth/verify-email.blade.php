<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Verifikasi WhatsApp</h1>
        <p class="mt-1 text-sm text-slate-500">Verifikasi WhatsApp dipakai saat aktivasi akun dan tindakan sensitif tertentu.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        <p class="font-bold text-slate-800">Gunakan alur login utama</p>
        <p class="mt-2">Masuk dengan Google, lengkapi data wajib jika akun baru, lalu ikuti instruksi OTP WhatsApp saat sistem meminta verifikasi aktivasi.</p>
    </div>

    <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-4 text-xs text-cyan-800">
        <p class="font-black">Catatan</p>
        <p class="mt-1 leading-relaxed">Borgfish tidak mengirim email verifikasi biasa. Verifikasi akun dilakukan melalui WhatsApp sesuai flow aktivasi.</p>
    </div>

    <div class="mt-5 text-right">
        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 sm:w-auto">
            Buka Login
        </a>
    </div>
</x-guest-layout>
