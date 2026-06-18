@php
    $deletionOtpToken = session('account_deletion_otp_token');
@endphp

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-black text-slate-900">
            Hapus Akun
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Akun akan dinonaktifkan permanen setelah verifikasi OTP WhatsApp. Riwayat bid, transaksi, pembayaran, dan audit tetap disimpan untuk keamanan marketplace.
        </p>
    </header>

    @if($errors->userDeletion->has('delete_account'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $errors->userDeletion->first('delete_account') }}
        </div>
    @endif

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Hapus Akun</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty() || session()->has('account_deletion_otp_token')" focusable>
        <div class="p-6">
            <h2 class="text-lg font-black text-slate-900">
                Konfirmasi Hapus Akun
            </h2>

            <p class="mt-1 text-sm text-slate-600">
                Untuk melindungi akun Google/WhatsApp Anda, hapus akun harus diverifikasi menggunakan OTP WhatsApp. Akun tidak bisa dihapus jika masih memiliki lelang, bid, atau transaksi aktif.
            </p>

            @if(! $deletionOtpToken)
                <form method="post" action="{{ route('profile.delete_otp') }}" class="mt-6">
                    @csrf

                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Setelah OTP dikirim, masukkan kode 6 digit untuk menonaktifkan akun ini.
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-secondary-button x-on:click="$dispatch('close')">
                            Batal
                        </x-secondary-button>

                        <x-danger-button class="sm:ms-3">
                            Kirim OTP WhatsApp
                        </x-danger-button>
                    </div>
                </form>
            @else
                <form method="post" action="{{ route('profile.destroy') }}" id="delete-account-with-otp-form" class="mt-6 space-y-4">
                    @csrf
                    @method('delete')

                    <input type="hidden" name="session_token" value="{{ $deletionOtpToken }}">

                    <div>
                        <x-input-label for="account_deletion_otp" value="Kode OTP WhatsApp" />
                        <x-text-input
                            id="account_deletion_otp"
                            name="otp"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            class="mt-1 block w-full tracking-[0.4em] text-center sm:w-3/4"
                            placeholder="000000"
                            required
                        />
                        <x-input-error :messages="$errors->userDeletion->get('otp')" class="mt-2" />
                    </div>

                    <label class="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            name="confirmation"
                            value="1"
                            class="mt-1 rounded border-slate-300 text-rose-600 shadow-sm focus:ring-rose-500"
                            required
                        >
                        <span>Saya paham akun akan dinonaktifkan dan tidak bisa digunakan masuk kembali tanpa bantuan admin.</span>
                    </label>
                    <x-input-error :messages="$errors->userDeletion->get('confirmation')" class="mt-2" />
                </form>

                <form method="post" action="{{ route('profile.delete_otp') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-cyan-700 hover:text-cyan-800">
                        Kirim ulang OTP
                    </button>
                </form>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-secondary-button x-on:click="$dispatch('close')">
                        Batal
                    </x-secondary-button>

                    <x-danger-button class="sm:ms-3" form="delete-account-with-otp-form">
                        Hapus Akun
                    </x-danger-button>
                </div>
            @endif
        </div>
    </x-modal>
</section>
