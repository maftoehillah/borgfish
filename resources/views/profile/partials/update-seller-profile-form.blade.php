<section>
    <header>
        <h2 class="text-lg font-black text-slate-900">Data Toko & Rekening</h2>
        <p class="mt-1 text-sm text-slate-500">
            Perbarui informasi toko dan rekening bank untuk pencairan dana.
        </p>
    </header>

    <form method="post" action="{{ route('profile.seller.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="store_name" value="Nama Toko" />
            <x-text-input id="store_name" name="store_name" type="text" class="mt-1 block w-full" :value="old('store_name', $user->sellerProfile?->store_name)" required />
            <x-input-error class="mt-2" :messages="$errors->get('store_name')" />
        </div>

        <div>
            <x-input-label for="full_address" value="Alamat Lengkap Toko" />
            <textarea id="full_address" name="full_address" rows="4" class="mt-1 w-full rounded-xl border-slate-300 px-4 py-3 text-base focus:border-cyan-500 focus:ring-cyan-500">{{ old('full_address', $user->sellerProfile?->full_address) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('full_address')" />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 font-semibold">
            Informasi Rekening Bank (untuk pencairan dana)
        </div>

        <div>
            <x-input-label for="bank_name" value="Nama Bank" />
            <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name', $user->sellerProfile?->bank_name)" required placeholder="Contoh: BCA, BNI, BRI, Mandiri" />
            <x-input-error class="mt-2" :messages="$errors->get('bank_name')" />
        </div>

        <div>
            <x-input-label for="bank_account_number" value="Nomor Rekening" />
            <x-text-input id="bank_account_number" name="bank_account_number" type="tel" class="mt-1 block w-full" :value="old('bank_account_number', $user->sellerProfile?->bank_account_number)" inputmode="numeric" autocomplete="off" required placeholder="Contoh: 1234567890" />
            <x-input-error class="mt-2" :messages="$errors->get('bank_account_number')" />
        </div>

        <div>
            <x-input-label for="bank_account_name" value="Nama Pemilik Rekening" />
            <x-text-input id="bank_account_name" name="bank_account_name" type="text" class="mt-1 block w-full" :value="old('bank_account_name', $user->sellerProfile?->bank_account_name)" required placeholder="Sesuai buku tabungan" />
            <x-input-error class="mt-2" :messages="$errors->get('bank_account_name')" />
        </div>

        <x-image-upload-preview
            name="store_photo"
            id="store_photo"
            label="Foto Toko (opsional, kosongkan jika tidak ingin mengubah)"
            accept="image/jpeg,image/png,image/webp"
            hint="Upload foto toko atau etalase versi terbaru. Format JPG, PNG, atau WebP, maksimal 4 MB."
            :existing-url="$user->sellerProfile?->store_photo_path ? publicStorageUrl($user->sellerProfile->store_photo_path) : null"
            existing-label="Foto toko saat ini"
            max-size-mb="4"
        />

        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-4">
            <x-primary-button>Simpan Data Toko</x-primary-button>

            @if (session('status') === 'seller-profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-slate-600">Tersimpan.</p>
            @endif
        </div>
    </form>
</section>
