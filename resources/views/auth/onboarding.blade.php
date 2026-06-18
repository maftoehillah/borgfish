<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Lengkapi Data Akun</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $user->isPenjual() ? 'Lengkapi data toko Anda.' : 'Lengkapi nomor WhatsApp Anda.' }}
        </p>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <a href="#section-whatsapp" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-700">WhatsApp</a>
        @if($user->isPenjual())
            <a href="#section-store" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs font-bold text-amber-700">Data Toko</a>
            <a href="#section-gps" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-bold text-indigo-700">GPS</a>
            <a href="#section-bank" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-700">Rekening</a>
        @endif
    </div>

    <form method="POST" action="{{ route('auth.onboarding.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div id="section-whatsapp" class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white px-4 py-4 sm:px-5">
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-cyan-700">Kontak Utama</p>
            <h2 class="mt-2 text-lg font-black text-slate-900">Nomor WhatsApp</h2>
            <p class="mt-1 text-xs text-slate-500">Dipakai untuk aktivasi akun, notifikasi penting, dan verifikasi tindakan sensitif.</p>

            <div class="mt-4">
                <x-input-label for="whatsapp_number" value="Nomor WhatsApp" />
                <x-text-input id="whatsapp_number" class="block mt-1 w-full" type="tel" name="whatsapp_number" :value="old('whatsapp_number', $user->whatsapp_number)" inputmode="numeric" autocomplete="tel-national" required autofocus />
                <x-input-error :messages="$errors->get('whatsapp_number')" class="mt-2" />
            </div>
        </div>

        @if($user->isPenjual())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <p class="font-black">Yang perlu disiapkan</p>
                <p class="mt-1 text-xs leading-relaxed">Nama toko, alamat lengkap, titik GPS saat berada di lokasi, data rekening, dan foto toko atau etalase ikan.</p>
            </div>

            <div id="section-store" class="scroll-mt-28 rounded-3xl border border-amber-100 bg-white px-4 py-4 sm:px-5">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-amber-700">Identitas Toko</p>
                <h2 class="mt-2 text-lg font-black text-slate-900">Profil toko penjual</h2>
                <p class="mt-1 text-xs text-slate-500">Nama dan alamat ini akan dipakai di dashboard toko dan proses operasional marketplace.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="store_name" value="Nama Toko" />
                        <x-text-input id="store_name" class="block mt-1 w-full" type="text" name="store_name" :value="old('store_name', $user->sellerProfile?->store_name)" required />
                        <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="full_address" value="Alamat Lengkap Toko" />
                        <textarea id="full_address" name="full_address" rows="4" class="mt-1 w-full rounded-xl border-slate-300 px-4 py-3 text-base focus:border-cyan-500 focus:ring-cyan-500" placeholder="Contoh: Nama pasar/dermaga, blok/kios, jalan, kecamatan, kota/kabupaten">{{ old('full_address', $user->sellerProfile?->full_address) }}</textarea>
                        <x-input-error :messages="$errors->get('full_address')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div id="section-gps" x-data="sellerGpsCapture({
                latitude: @js(old('store_latitude', $user->sellerProfile?->store_latitude)),
                longitude: @js(old('store_longitude', $user->sellerProfile?->store_longitude)),
                accuracy: @js(old('store_gps_accuracy', $user->sellerProfile?->store_gps_accuracy))
            })" class="scroll-mt-28 rounded-3xl border border-indigo-100 bg-white px-4 py-4 sm:px-5">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-indigo-700">Lokasi Toko</p>
                <h2 class="mt-2 text-lg font-black text-slate-900">Titik GPS</h2>
                <p class="mt-1 text-xs text-slate-500">Ambil titik saat Anda benar-benar berada di lokasi toko agar koordinat akurat.</p>

                <input type="hidden" name="store_latitude" x-model="latitude">
                <input type="hidden" name="store_longitude" x-model="longitude">
                <input type="hidden" name="store_gps_accuracy" x-model="accuracy">

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <x-secondary-button type="button" x-on:click="capture()" x-bind:disabled="loading">
                        <span x-text="loading ? 'Mengambil GPS...' : 'Ambil Titik GPS'"></span>
                    </x-secondary-button>

                    <p class="text-xs font-semibold" x-bind:class="latitude && longitude ? 'text-emerald-700' : 'text-slate-500'" x-text="statusText()"></p>
                </div>

                <div x-show="latitude && longitude" class="mt-3 rounded-xl border border-cyan-100 bg-cyan-50/70 px-3 py-2 text-xs text-cyan-800">
                    <p>Latitude: <span class="break-all font-bold" x-text="latitude"></span></p>
                    <p>Longitude: <span class="break-all font-bold" x-text="longitude"></span></p>
                    <p x-show="accuracy">Akurasi sekitar <span class="font-bold" x-text="accuracy"></span> meter</p>
                </div>

                <x-input-error :messages="$errors->get('store_latitude')" class="mt-2" />
                <x-input-error :messages="$errors->get('store_longitude')" class="mt-2" />
                <x-input-error :messages="$errors->get('store_gps_accuracy')" class="mt-2" />
            </div>

            <div id="section-bank" class="scroll-mt-28 rounded-3xl border border-emerald-100 bg-white px-4 py-4 sm:px-5">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-emerald-700">Pencairan Dana</p>
                <h2 class="mt-2 text-lg font-black text-slate-900">Rekening bank penjual</h2>
                <p class="mt-1 text-xs text-slate-500">Rekening ini dipakai untuk settlement dana setelah transaksi selesai dan lolos verifikasi.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="bank_name" value="Nama Bank" />
                        <x-text-input id="bank_name" class="block mt-1 w-full" type="text" name="bank_name" :value="old('bank_name', $user->sellerProfile?->bank_name)" required placeholder="Contoh: BCA, BNI, BRI, Mandiri" />
                        <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="bank_account_number" value="Nomor Rekening" />
                        <x-text-input id="bank_account_number" class="block mt-1 w-full" type="tel" name="bank_account_number" :value="old('bank_account_number', $user->sellerProfile?->bank_account_number)" inputmode="numeric" autocomplete="off" required placeholder="Contoh: 1234567890" />
                        <x-input-error :messages="$errors->get('bank_account_number')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="bank_account_name" value="Nama Pemilik Rekening" />
                        <x-text-input id="bank_account_name" class="block mt-1 w-full" type="text" name="bank_account_name" :value="old('bank_account_name', $user->sellerProfile?->bank_account_name)" required placeholder="Sesuai buku tabungan" />
                        <x-input-error :messages="$errors->get('bank_account_name')" class="mt-2" />
                    </div>

                    <x-image-upload-preview
                        name="store_photo"
                        id="store_photo"
                        label="Foto Toko / Box / Etalase Ikan"
                        accept="image/jpeg,image/png,image/webp"
                        :required="! $user->sellerProfile?->store_photo_path"
                        max-size-mb="4"
                        hint="Upload foto toko, box/boks penyimpanan, atau etalase ikan. Format JPG, PNG, atau WebP, maksimal 4 MB."
                        :existing-url="$user->sellerProfile?->store_photo_path ? publicStorageUrl($user->sellerProfile->store_photo_path) : null"
                        existing-label="Foto toko sudah tersimpan. Upload file baru hanya jika ingin mengganti."
                    />
                </div>
            </div>
        @endif

        <x-primary-button class="w-full justify-center min-h-[48px]">
            Simpan dan Kirim OTP Aktivasi
        </x-primary-button>
    </form>

    @if($user->isPenjual())
        <script>
            function sellerGpsCapture(initial) {
                return {
                    latitude: initial.latitude || '',
                    longitude: initial.longitude || '',
                    accuracy: initial.accuracy || '',
                    loading: false,
                    message: '',
                    capture() {
                        if (! navigator.geolocation) {
                            this.message = 'Browser tidak mendukung GPS. Gunakan browser modern dengan izin lokasi aktif.';
                            return;
                        }

                        this.loading = true;
                        this.message = 'Mohon izinkan akses lokasi pada browser.';

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                this.latitude = position.coords.latitude.toFixed(7);
                                this.longitude = position.coords.longitude.toFixed(7);
                                this.accuracy = position.coords.accuracy ? position.coords.accuracy.toFixed(2) : '';
                                this.loading = false;
                                this.message = 'Titik GPS berhasil diambil.';
                            },
                            (error) => {
                                this.loading = false;
                                this.message = error.code === error.PERMISSION_DENIED
                                    ? 'Izin lokasi ditolak. Aktifkan izin lokasi lalu coba lagi.'
                                    : 'GPS belum berhasil diambil. Pastikan lokasi aktif dan koneksi stabil.';
                            },
                            {
                                enableHighAccuracy: true,
                                timeout: 15000,
                                maximumAge: 0,
                            }
                        );
                    },
                    statusText() {
                        if (this.message) {
                            return this.message;
                        }

                        if (this.latitude && this.longitude) {
                            return 'Titik GPS sudah tersedia.';
                        }

                        return 'Titik GPS belum diambil.';
                    },
                };
            }
        </script>
    @endif
</x-guest-layout>
