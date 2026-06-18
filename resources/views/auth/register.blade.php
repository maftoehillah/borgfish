<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Buat Akun Borgfish</h1>
        <p class="mt-1 text-sm text-slate-500">Pilih jenis akun, lalu lanjutkan dengan Google.</p>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-2 sm:hidden">
        <a href="{{ route('register') }}" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-700">Daftar</a>
        <a href="{{ route('login') }}" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">Masuk</a>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        @php
            $selectedRole = old('role');
        @endphp

        <div class="mt-4">
            <x-input-label for="role" :value="__('Daftar sebagai')" />
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 cursor-pointer border rounded-2xl px-4 py-4 transition {{ $selectedRole === 'pembeli' ? 'border-cyan-300 bg-cyan-50 shadow-sm' : 'border-slate-200 bg-white' }}">
                    <input type="radio" name="role" value="pembeli" {{ $selectedRole === 'pembeli' ? 'checked' : '' }} class="mt-1 text-cyan-600 focus:ring-cyan-500">
                    <div>
                        <p class="font-semibold text-slate-800 text-sm">Pembeli</p>
                        <p class="text-xs text-slate-500">Bid lelang dan bayar pemenang</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer border rounded-2xl px-4 py-4 transition {{ $selectedRole === 'penjual' ? 'border-cyan-300 bg-cyan-50 shadow-sm' : 'border-slate-200 bg-white' }}">
                    <input type="radio" name="role" value="penjual" {{ $selectedRole === 'penjual' ? 'checked' : '' }} class="mt-1 text-cyan-600 focus:ring-cyan-500">
                    <div>
                        <p class="font-semibold text-slate-800 text-sm">Penjual</p>
                        <p class="text-xs text-slate-500">Upload lot dan kelola order</p>
                    </div>
                </label>
            </div>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <p class="mt-4 text-sm text-slate-500">Pembeli cukup verifikasi WhatsApp saat aktivasi akun. Penjual lanjut isi data toko dan rekening sebelum verifikasi.</p>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 mt-5">
            <x-primary-button class="w-full sm:w-auto sm:ms-4">
                Pilih akun Google
            </x-primary-button>

            <a class="text-sm font-semibold text-cyan-700 hover:text-cyan-800 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 text-center sm:text-left" href="{{ route('login') }}">
                Sudah punya akun?
            </a>
        </div>
    </form>
</x-guest-layout>
