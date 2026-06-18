<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Verifikasi WhatsApp</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $purpose === 'phone_verification' ? 'Masukkan OTP aktivasi yang dikirim ke ' . $maskedPhone . '.' : 'Masukkan OTP yang dikirim ke ' . $maskedPhone . '.' }}
        </p>
    </div>

    <form method="POST" action="{{ route('auth.otp.verify') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="session_token" value="{{ $challenge->session_token }}">
        <input type="hidden" name="purpose" value="{{ $purpose }}">

        <div class="rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-4 text-sm text-cyan-800">
            <p class="font-black">Periksa WhatsApp Anda</p>
            <p class="mt-1 text-xs leading-relaxed">Kode OTP terdiri dari 6 digit. Jika belum masuk, tunggu sebentar lalu gunakan tombol kirim ulang.</p>
        </div>

        <div>
            <x-input-label for="otp" :value="$purpose === 'phone_verification' ? 'Kode OTP Aktivasi' : 'Kode OTP'" />
            <x-text-input id="otp" class="block mt-1 w-full tracking-[0.35em] text-center text-xl font-black" type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required autofocus />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        @if(session('dev_whatsapp_otp'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                OTP dev: <span class="font-black">{{ session('dev_whatsapp_otp') }}</span>
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-primary-button class="w-full justify-center sm:w-auto min-h-[48px]">
                Verifikasi
            </x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('auth.otp.resend') }}" class="mt-4">
        @csrf
        <input type="hidden" name="purpose" value="{{ $purpose }}">
        <button type="submit" class="inline-flex min-h-[42px] w-full items-center justify-center rounded-xl border border-cyan-200 bg-white px-4 py-3 text-sm font-semibold text-cyan-700 hover:bg-cyan-50 sm:w-auto">
            Kirim ulang OTP
        </button>
    </form>
</x-guest-layout>
