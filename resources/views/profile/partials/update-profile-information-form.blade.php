<section>
    <header>
        <h2 class="text-lg font-black text-slate-900">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Perbarui nama akun Anda. Email Google tidak bisa diubah dari halaman ini.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Nama" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email_display" value="Email Google" />
            <div id="email_display" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 break-all">
                {{ $user->email }}
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Email mengikuti akun Google yang dipakai saat masuk.
            </p>
        </div>

        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-4">
            <x-primary-button>Simpan</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >Tersimpan.</p>
            @endif
        </div>
    </form>
</section>
