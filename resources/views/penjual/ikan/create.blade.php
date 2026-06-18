@extends('layouts.app')
@php
    $isEdit = $isEdit ?? false;
    $ikan = $ikan ?? new \App\Models\Ikan();
    $draftIkan = $draftIkan ?? null;
    $duplicateSourceIkan = $duplicateSourceIkan ?? null;
    $reuseSourceIkanId = old('reuse_source_ikan_id', $duplicateSourceIkan?->id);
    $fotoRequiredOnCreate = ! $isEdit && ! $reuseSourceIkanId;
    $formAction = $isEdit ? route('penjual.ikans.update', $ikan) : route('penjual.ikans.store');
    $pageTitle = $isEdit ? 'Edit Lot Ikan' : 'Upload Ikan Baru';
    $submitLabel = $isEdit ? 'Simpan Perubahan' : 'Upload Ikan ke Lelang';

    $waktuMulai = old('waktu_mulai', $ikan->waktu_mulai ? $ikan->waktu_mulai->format('Y-m-d\TH:i') : '');
    $waktuSelesai = old('waktu_selesai', $ikan->waktu_selesai ? $ikan->waktu_selesai->format('Y-m-d\TH:i') : '');
    $tanggalTangkap = old('tanggal_tangkap', $ikan->tanggal_tangkap ? $ikan->tanggal_tangkap->format('Y-m-d') : '');
    $existingFotoUrl = $ikan->foto ? publicStorageUrl($ikan->foto) : '';
    $existingVideoUrl = $ikan->video ? publicStorageUrl($ikan->video) : '';
    $tipeLelang = old('tipe_lelang', $ikan->tipe_lelang ?? 'naik');
    $reservePrice = old('reserve_price', $ikan->reserve_price);
    $oldMulaiSekarang = old('mulai_sekarang');
    $mulaiSekarangDefault = $oldMulaiSekarang === null
        ? ! $isEdit
        : ((string) $oldMulaiSekarang === '1');

    $initialStep = $errors->hasAny([
        'tipe_lelang',
        'harga_awal',
        'reserve_price',
        'minimal_increment',
        'buy_now_price',
        'anti_sniping_window_seconds',
        'anti_sniping_extend_seconds',
        'anti_sniping_max_extensions',
        'waktu_mulai',
        'waktu_selesai',
        'mulai_sekarang',
    ]) ? 2 : 1;

    $draftPayload = $draftIkan ? [
        'metode_tangkap' => $draftIkan->metode_tangkap,
        'deskripsi' => $draftIkan->deskripsi,
        'jenis_kemasan' => $draftIkan->jenis_kemasan,
    ] : null;

    $requestedReturnUrl = old('return_url', request()->query('return_url'));
    $safeReturnUrl = safeInternalReturnUrl($requestedReturnUrl, route('penjual.ikans.index'));
    $antiSnipingDefaults = $antiSnipingDefaults ?? [
        'enabled' => true,
        'window_seconds' => 60,
        'extend_seconds' => 90,
        'max_extensions' => 3,
    ];
    $antiSnipingEnabledInitial = old('anti_sniping_enabled', $ikan->anti_sniping_enabled ?? $antiSnipingDefaults['enabled']);
@endphp
@section('title', $pageTitle)

@section('content')
<style>
    .seller-form-hero {
        background:
            radial-gradient(circle at 88% 0%, rgba(34, 211, 238, 0.18), transparent 34%),
            radial-gradient(circle at 8% 95%, rgba(16, 185, 129, 0.14), transparent 28%),
            linear-gradient(145deg, #f9fdff 0%, #eef7ff 52%, #f8fcff 100%);
    }

    .seller-form-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 18px 28px -24px rgba(15, 23, 42, 0.6);
    }
</style>

<div class="max-w-4xl mx-auto">
    <section class="seller-form-hero rounded-3xl border border-cyan-100/70 px-6 py-6 sm:px-8 sm:py-7 mb-6">
        <x-back-button :href="$safeReturnUrl" label="Kembali ke Aktivitas" />
        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 mt-3">{{ $pageTitle }}</h1>
        <p class="text-sm text-slate-600 mt-2">Lengkapi data lot dan pilih mode lelang yang sesuai.</p>
    </section>

    @if(! $isEdit && $duplicateSourceIkan)
        <div class="mb-6 rounded-2xl border border-cyan-200 bg-cyan-50/90 p-4 sm:p-5">
            <p class="text-sm font-black text-cyan-900">Mode Upload Ulang Aktif</p>
            <p class="mt-1 text-xs text-cyan-800">Data lot disalin dari <span class="font-semibold">{{ $duplicateSourceIkan->nama_ikan }}</span>. Anda tetap bisa edit semua isi sebelum upload, dan media lama akan dipakai jika tidak upload file baru.</p>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50/90 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <p class="text-sm font-black text-rose-800">Data upload belum lengkap.</p>
                    <p class="mt-1 text-xs text-rose-700">{{ session('error') ?? 'Mohon lengkapi semua kolom wajib bertanda * lalu kirim ulang.' }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-700">
                    {{ $errors->count() }} peringatan
                </span>
            </div>

            <ul class="mt-3 space-y-1 text-xs text-rose-700 list-disc list-inside">
                @foreach(array_slice($errors->all(), 0, 3) as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>

            @if($errors->count() > 3)
                <p class="mt-2 text-[11px] font-semibold text-rose-700">
                    Masih ada {{ $errors->count() - 3 }} data lain yang perlu dilengkapi.
                </p>
            @endif
        </div>
    @endif

    <div class="seller-form-surface bg-white rounded-3xl p-5 sm:p-8" x-data="{
        step: {{ $initialStep }},
        tipeLelang: '{{ $tipeLelang }}',
        foto: null,
        preview: null,
        existingFotoUrl: @js($existingFotoUrl),
        video: null,
        videoPreview: null,
        existingVideoUrl: @js($existingVideoUrl),
        videoDurationSeconds: '',
        videoMaxDurationSeconds: 30,
        videoError: '',
        hasDraft: {{ $draftPayload ? 'true' : 'false' }},
        draftData: @js($draftPayload ?? []),
        buyNowEnabled: {{ old('buy_now_enabled', $ikan->buy_now_enabled ? 1 : 0) ? 'true' : 'false' }},
        antiSnipingEnabled: {{ $antiSnipingEnabledInitial ? 'true' : 'false' }},
        antiSnipingWindowSeconds: Number('{{ $antiSnipingDefaults['window_seconds'] }}'),
        antiSnipingExtendSeconds: Number('{{ $antiSnipingDefaults['extend_seconds'] }}'),
        antiSnipingMaxExtensions: Number('{{ $antiSnipingDefaults['max_extensions'] }}'),
        mulaiSekarang: {{ $mulaiSekarangDefault ? 'true' : 'false' }},
        waktuMulaiInput: '{{ $waktuMulai }}',
        waktuSelesaiInput: '{{ $waktuSelesai }}',
        capturedAt: '{{ old('foto_diambil_pada', $ikan->foto_diambil_pada ? $ikan->foto_diambil_pada->format('Y-m-d\TH:i:s') : '') }}',
        errorFields: @js($errors->keys()),
        stepTwoFields: [
            'tipe_lelang',
            'harga_awal',
            'reserve_price',
            'minimal_increment',
            'buy_now_price',
            'waktu_mulai',
            'waktu_selesai',
            'mulai_sekarang',
        ],
        initWizard() {
            if (this.mulaiSekarang && !this.waktuMulaiInput) {
                this.waktuMulaiInput = this.nowInputValue();
            }
            this.syncTipeLelang();
            this.$nextTick(() => this.focusFirstErrorField());
        },
        isStepTwoField(fieldName) {
            return this.stepTwoFields.includes(fieldName);
        },
        findFocusableField(fieldName) {
            const candidates = this.$root.querySelectorAll(`[name='${fieldName}'], [name='${fieldName}[]']`);

            for (const field of candidates) {
                if (!field || field.type === 'hidden' || field.disabled) {
                    continue;
                }

                const style = window.getComputedStyle(field);
                if (style.display === 'none' || style.visibility === 'hidden') {
                    continue;
                }

                return field;
            }

            return null;
        },
        focusFirstErrorField() {
            if (!Array.isArray(this.errorFields) || this.errorFields.length === 0) {
                return;
            }

            const firstErrorField = this.errorFields[0];
            this.step = this.isStepTwoField(firstErrorField) ? 2 : 1;

            this.$nextTick(() => {
                for (const fieldName of this.errorFields) {
                    if (this.isStepTwoField(fieldName) !== (this.step === 2)) {
                        continue;
                    }

                    const target = this.findFocusableField(fieldName);
                    if (!target) {
                        continue;
                    }

                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof target.focus === 'function') {
                        target.focus({ preventScroll: true });
                    }
                    break;
                }
            });
        },
        nowInputValue() {
            const d = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        },
        nextStep() {
            this.step = Math.min(2, this.step + 1);
        },
        prevStep() {
            this.step = Math.max(1, this.step - 1);
        },
        setFieldValue(name, value) {
            const field = this.$root.querySelector(`[name='${name}']`);
            if (!field) return;
            field.value = value ?? '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        },
        useLatestDraft() {
            if (!this.hasDraft || !this.draftData) return;
            this.setFieldValue('metode_tangkap', this.draftData.metode_tangkap);
            this.setFieldValue('deskripsi', this.draftData.deskripsi);
            this.setFieldValue('jenis_kemasan', this.draftData.jenis_kemasan);
        },
        syncTipeLelang() {
            if (this.tipeLelang === 'turun') {
                this.buyNowEnabled = true;
                return;
            }

            this.setFieldValue('reserve_price', '');
        },
        syncMulaiSekarang() {
            if (this.mulaiSekarang) {
                this.waktuMulaiInput = this.nowInputValue();
            }
        },
        handleFoto(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.foto = file.name;
            const reader = new FileReader();
            reader.onload = (ev) => this.preview = ev.target.result;
            reader.readAsDataURL(file);
            this.capturedAt = new Date().toISOString();
        },
        handleVideo(e) {
            const file = e.target.files[0];
            this.videoError = '';

            if (!file) {
                this.resetVideoSelection();
                return;
            }

            const objectUrl = URL.createObjectURL(file);

            this.resolveVideoDuration(objectUrl)
                .then((durationSeconds) => {
                    if (durationSeconds > this.videoMaxDurationSeconds) {
                        URL.revokeObjectURL(objectUrl);
                        this.resetVideoSelection({ clearInput: true });
                        this.videoError = `Durasi video maksimal ${this.videoMaxDurationSeconds} detik. Video yang dipilih sekitar ${this.formatVideoDuration(durationSeconds)}.`;
                        return;
                    }

                    this.revokeObjectUrl(this.videoPreview);
                    this.video = file.name;
                    this.videoPreview = objectUrl;
                    this.videoDurationSeconds = Math.round(durationSeconds * 10) / 10;
                })
                .catch(() => {
                    URL.revokeObjectURL(objectUrl);
                    this.resetVideoSelection({ clearInput: true });
                    this.videoError = 'Durasi video tidak dapat dibaca. Coba pilih file video lain.';
                });
        },
        revokeObjectUrl(url) {
            if (typeof url === 'string' && url.startsWith('blob:')) {
                URL.revokeObjectURL(url);
            }
        },
        resetVideoSelection({ clearInput = false } = {}) {
            this.revokeObjectUrl(this.videoPreview);
            this.video = null;
            this.videoPreview = null;
            this.videoDurationSeconds = '';

            if (clearInput && this.$refs.videoInput) {
                this.$refs.videoInput.value = '';
            }
        },
        resolveVideoDuration(objectUrl) {
            return new Promise((resolve, reject) => {
                const probe = document.createElement('video');
                const cleanup = () => {
                    probe.removeAttribute('src');
                    probe.load();
                };

                probe.preload = 'metadata';
                probe.playsInline = true;

                probe.onloadedmetadata = () => {
                    const durationSeconds = probe.duration;
                    cleanup();

                    if (!Number.isFinite(durationSeconds) || durationSeconds <= 0) {
                        reject(new Error('invalid-duration'));
                        return;
                    }

                    resolve(durationSeconds);
                };

                probe.onerror = () => {
                    cleanup();
                    reject(new Error('metadata-read-failed'));
                };

                probe.src = objectUrl;
            });
        },
        formatVideoDuration(seconds) {
            if (!Number.isFinite(seconds) || seconds <= 0) {
                return '0 detik';
            }

            const roundedSeconds = Math.round(seconds);
            const minutes = Math.floor(roundedSeconds / 60);
            const remainingSeconds = roundedSeconds % 60;

            if (minutes <= 0) {
                return `${remainingSeconds} detik`;
            }

            return `${minutes} menit ${remainingSeconds} detik`;
        },
        videoDurationLabel() {
            const durationSeconds = Number(this.videoDurationSeconds);

            if (!Number.isFinite(durationSeconds) || durationSeconds <= 0) {
                return '-';
            }

            return this.formatVideoDuration(durationSeconds);
        },
        capturedAtLabel() {
            if (!this.capturedAt) return '-';
            const date = new Date(this.capturedAt);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleString('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        },
        potentialEndTime() {
            if (!this.antiSnipingEnabled || !this.waktuSelesaiInput) return null;
            const selesai = new Date(this.waktuSelesaiInput);
            if (isNaN(selesai.getTime())) return null;
            const extraSeconds = Number(this.antiSnipingExtendSeconds || 0) * Number(this.antiSnipingMaxExtensions || 0);
            selesai.setSeconds(selesai.getSeconds() + extraSeconds);
            return selesai.toLocaleString('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        }
    }" x-init="initWizard()">
        <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" class="space-y-8 pb-32 sm:pb-0">
            @csrf
            <input type="hidden" name="return_url" value="{{ $safeReturnUrl }}">
            @if(! $isEdit && $reuseSourceIkanId)
                <input type="hidden" name="reuse_source_ikan_id" value="{{ $reuseSourceIkanId }}">
            @endif
            @if($isEdit)
                @method('PATCH')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button type="button" @click="step = 1" :class="step === 1 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200'" class="w-full border rounded-xl px-4 py-3 text-xs sm:text-sm font-bold transition">
                    <span class="sm:hidden">1. Produk</span>
                    <span class="hidden sm:inline">1. Produk &amp; Media</span>
                </button>
                <button type="button" @click="step = 2" :class="step === 2 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200'" class="w-full border rounded-xl px-4 py-3 text-xs sm:text-sm font-bold transition">
                    <span class="sm:hidden">2. Lelang</span>
                    <span class="hidden sm:inline">2. Pengaturan Lelang</span>
                </button>
            </div>

            <input type="hidden" name="foto_diambil_pada" :value="capturedAt">
            <input type="hidden" name="video_duration_seconds" :value="videoDurationSeconds">

            <div x-show="step === 1" x-transition class="space-y-6">
                <div class="sm:col-span-2 pt-1 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-sm font-black text-gray-800 tracking-wide uppercase">Produk & Media</h2>
                        <p class="text-xs text-gray-500">Isi data lot inti dulu, lalu unggah bukti visual.</p>
                    </div>
                    @if(! $isEdit && $draftPayload)
                        <button type="button" @click="useLatestDraft()" class="inline-flex items-center px-3 py-2 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 text-xs font-bold hover:bg-sky-100 transition">
                            Gunakan Draft Terakhir
                        </button>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Ikan *</label>
                        <input type="text" name="nama_ikan" value="{{ old('nama_ikan', $ikan->nama_ikan) }}" :required="step === 1" placeholder="contoh: Nila Segar" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 @error('nama_ikan') border-red-300 @enderror">
                        @error('nama_ikan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Berat (kg) *</label>
                        <input type="number" name="berat" value="{{ old('berat', $ikan->berat) }}" min="0.1" step="0.1" :required="step === 1" placeholder="contoh: 5.5" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        @error('berat')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Estimasi Jumlah Ekor</label>
                        <input type="number" name="estimasi_jumlah_ekor" value="{{ old('estimasi_jumlah_ekor', $ikan->estimasi_jumlah_ekor) }}" min="1" step="1" placeholder="contoh: 12" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        @error('estimasi_jumlah_ekor')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Kondisi *</label>
                        <select name="kondisi" :required="step === 1" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <option value="">Pilih kondisi...</option>
                            <option value="segar" {{ old('kondisi', $ikan->kondisi) === 'segar' ? 'selected' : '' }}>Segar</option>
                            <option value="beku" {{ old('kondisi', $ikan->kondisi) === 'beku' ? 'selected' : '' }}>Frozen</option>
                        </select>
                        @error('kondisi')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Kemasan</label>
                        <select name="jenis_kemasan" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <option value="">Pilih kemasan...</option>
                            <option value="keranjang" {{ old('jenis_kemasan', $ikan->jenis_kemasan) === 'keranjang' ? 'selected' : '' }}>Keranjang</option>
                            <option value="besek" {{ old('jenis_kemasan', $ikan->jenis_kemasan) === 'besek' ? 'selected' : '' }}>Besek</option>
                            <option value="styrofoam" {{ old('jenis_kemasan', $ikan->jenis_kemasan) === 'styrofoam' ? 'selected' : '' }}>Styrofoam</option>
                        </select>
                        @error('jenis_kemasan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Tangkap</label>
                        <input type="date" name="tanggal_tangkap" value="{{ $tanggalTangkap }}" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        @error('tanggal_tangkap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Metode Budidaya/Tangkap</label>
                        <input type="text" name="metode_tangkap" value="{{ old('metode_tangkap', $ikan->metode_tangkap) }}" placeholder="contoh: panen kolam harian" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        @error('metode_tangkap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" rows="3" maxlength="1000" placeholder="Deskripsikan ikan Anda (opsional)..." class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none">{{ old('deskripsi', $ikan->deskripsi) }}</textarea>
                        @error('deskripsi')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Foto Ikan {{ $isEdit ? '' : '*' }}</label>
                        @if(! $isEdit && $reuseSourceIkanId)
                            <p class="mb-2 text-[11px] text-cyan-700">Kosongkan upload foto jika ingin memakai foto dari lot sumber.</p>
                        @endif

                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-blue-300 transition cursor-pointer" @click="$refs.fotoInput.click()">
                            <template x-if="preview">
                                <img :src="preview" class="mx-auto max-h-48 rounded-lg object-cover mb-2" loading="lazy" decoding="async">
                            </template>

                            <template x-if="!preview && existingFotoUrl">
                                <img :src="existingFotoUrl" class="mx-auto max-h-48 rounded-lg object-cover mb-2" loading="lazy" decoding="async">
                            </template>

                            <template x-if="!preview && !existingFotoUrl">
                                <div>
                                    <p class="text-gray-500 text-sm">Klik untuk upload foto ikan</p>
                                    <p class="text-gray-300 text-xs mt-1">JPG, PNG, max 5MB</p>
                                </div>
                            </template>
                            <p x-show="foto" x-text="'File: ' + foto" class="text-xs text-blue-600 mt-2"></p>
                        </div>
                        <input type="file" name="foto" x-ref="fotoInput" accept="image/*" @change="handleFoto($event)" class="hidden" :required="step === 1 && {{ $fotoRequiredOnCreate ? 'true' : 'false' }}">
                        <div class="mt-2 text-xs text-gray-500 space-y-1">
                            <p>Stamp waktu foto: <strong x-text="capturedAtLabel()"></strong></p>
                        </div>
                        @error('foto')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        @error('foto_diambil_pada')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Video Ikan (Opsional)</label>
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-blue-300 transition cursor-pointer" @click="$refs.videoInput.click()">
                            <template x-if="videoPreview">
                                <video :src="videoPreview" controls preload="metadata" playsinline class="mx-auto max-h-56 rounded-lg mb-2"></video>
                            </template>

                            <template x-if="!videoPreview && existingVideoUrl">
                                <video :src="existingVideoUrl" controls preload="none" playsinline class="mx-auto max-h-56 rounded-lg mb-2"></video>
                            </template>

                            <template x-if="!videoPreview && !existingVideoUrl">
                                <div>
                                    <p class="text-gray-500 text-sm">Klik untuk upload video ikan</p>
                                    <p class="text-gray-300 text-xs mt-1">MP4/MOV/WEBM, max 30MB, maks 30 detik</p>
                                </div>
                            </template>
                            <p x-show="video" x-text="'File: ' + video" class="text-xs text-blue-600 mt-2"></p>
                        </div>
                        <input type="file" name="video" x-ref="videoInput" accept="video/mp4,video/quicktime,video/webm" @change="handleVideo($event)" class="hidden">
                        <div class="mt-2 text-xs text-gray-500 space-y-1">
                            <p x-show="videoDurationSeconds">Durasi video: <strong x-text="videoDurationLabel()"></strong></p>
                            <p x-show="videoError" x-text="videoError" class="text-red-500"></p>
                        </div>
                        @error('video')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        @error('video_duration_seconds')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div x-show="step === 2" x-transition class="space-y-6">
                <div>
                    <h2 class="text-sm font-black text-gray-800 tracking-wide uppercase">Pengaturan Lelang</h2>
                    <p class="text-xs text-gray-500">Pilih mode lelang dan atur aturan bidding.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="sm:col-span-2">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                            <p class="text-xs font-bold text-indigo-800 mb-2">Mode Pelelangan</p>
                            <div class="space-y-2">
                                <label class="flex items-start gap-3 text-sm text-indigo-800">
                                    <input type="radio" name="tipe_lelang" value="naik" x-model="tipeLelang" @change="syncTipeLelang()" class="mt-1 border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <strong>Lelang Naik</strong>
                                        <span class="block text-xs text-indigo-700">pembeli menawar lebih tinggi dari harga saat ini.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3 text-sm text-indigo-800">
                                    <input type="radio" name="tipe_lelang" value="turun" x-model="tipeLelang" @change="syncTipeLelang()" class="mt-1 border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <strong>Lelang Turun</strong>
                                        <span class="block text-xs text-indigo-700">penjual pasang harga dulu, pembeli menawar lebih rendah.</span>
                                    </span>
                                </label>
                            </div>
                            @error('tipe_lelang')<p class="text-red-500 text-xs mt-2">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1" x-text="tipeLelang === 'turun' ? 'Harga Buka (Rp) *' : 'Harga Awal (Rp) *'"></label>
                        <input type="number" name="harga_awal" x-ref="hargaAwalInput" value="{{ old('harga_awal', $ikan->harga_awal) }}" :min="tipeLelang === 'turun' ? 2000 : 1000" step="1000" required placeholder="contoh: 150000" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        @error('harga_awal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="tipeLelang === 'turun'" x-transition>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Reserve Price (Rp)</label>
                        <input type="number" name="reserve_price" value="{{ $reservePrice }}" :disabled="tipeLelang !== 'turun'" min="1000" step="1000" placeholder="contoh: 180000" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <p class="text-[11px] text-gray-400 mt-1">Jika bid terbaik di bawah nilai ini, lot tidak lanjut ke pembayaran.</p>
                        @error('reserve_price')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    @php
                        $paymentDeadlineMinutes = app(\App\Services\SystemSettingService::class)->paymentDeadlineMinutes();
                    @endphp
                    <div class="sm:col-span-2">
                        <div class="rounded-2xl border border-cyan-100 bg-cyan-50/80 px-4 py-4 text-sm text-slate-700">
                            <p class="font-black text-cyan-800">Pembayaran buyer melalui gateway</p>
                            <p class="mt-1 text-xs leading-relaxed">Setelah lelang selesai, pemenang wajib membayar invoice dalam {{ $paymentDeadlineMinutes }} menit.</p>
                        </div>
                    </div>

                    <div x-show="tipeLelang === 'naik'" x-transition>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Minimal Increment (Rp) *</label>
                        <input type="number" name="minimal_increment" :disabled="tipeLelang !== 'naik'" value="{{ old('minimal_increment', $ikan->minimal_increment) }}" min="1000" step="1000" required placeholder="contoh: 5000" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <p class="text-[11px] text-gray-400 mt-1">Saran: 1-10% dari harga awal agar bidding tetap kompetitif.</p>
                        @error('minimal_increment')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="tipeLelang === 'turun'" x-transition>
                        <input type="hidden" name="minimal_increment" value="1000" :disabled="tipeLelang !== 'turun'">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Aturan Bid Turun</label>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            Minimal Increment tidak dipakai di mode ini. Bid pembeli harus kelipatan Rp 1.000, minimal Rp 1.000, di bawah harga patokan, dan harga patokan minimal Rp 2.000.
                        </div>
                    </div>

                    <div class="sm:col-span-2" x-show="tipeLelang === 'naik'" x-transition>
                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <input type="hidden" name="buy_now_enabled" value="0" :disabled="tipeLelang !== 'naik'">
                            <label class="inline-flex items-center gap-3 text-sm font-semibold text-blue-800">
                                <input type="checkbox" name="buy_now_enabled" value="1" x-model="buyNowEnabled" :disabled="tipeLelang !== 'naik'" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                                Aktifkan Beli Sekarang
                            </label>
                            <div class="mt-3" x-show="buyNowEnabled" x-transition>
                                <label class="block text-xs font-semibold text-blue-700 mb-1">Harga Beli Sekarang (Rp)</label>
                                <input type="number" name="buy_now_price" :disabled="tipeLelang !== 'naik'" value="{{ old('buy_now_price', $ikan->buy_now_price) }}" min="1000" step="1000" placeholder="contoh: 325000" class="w-full border border-blue-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                                @error('buy_now_price')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2" x-show="tipeLelang === 'turun'" x-transition>
                        <input type="hidden" name="buy_now_enabled" value="1" :disabled="tipeLelang !== 'turun'">
                        <input type="hidden" name="buy_now_price" :value="$refs.hargaAwalInput?.value || ''" :disabled="tipeLelang !== 'turun'">
                        <div class="rounded-xl border border-cyan-100 bg-cyan-50 p-4 text-xs text-cyan-700">
                            Beli sekarang aktif otomatis pada mode lelang turun, dengan harga sesuai patokan penjual (Harga Buka).
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <label class="inline-flex items-center gap-3 text-sm font-semibold text-orange-800">
                                    <input type="hidden" name="anti_sniping_enabled" value="0">
                                    <input type="checkbox" name="anti_sniping_enabled" value="1" x-model="antiSnipingEnabled" class="rounded border-orange-300 text-orange-600 focus:ring-orange-500" {{ $antiSnipingEnabledInitial ? 'checked' : '' }}>
                                    Aktifkan Anti-Sniping
                                </label>
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-[11px] font-bold text-orange-700 border border-orange-200">
                                    Aturan default dari admin
                                </span>
                            </div>

                            <div class="mt-3 text-xs text-orange-700" x-show="antiSnipingEnabled" x-transition>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="rounded-lg border border-orange-200 bg-white px-3 py-2">
                                        <p class="text-[11px] font-semibold text-orange-600">Window akhir</p>
                                        <p class="mt-1 font-bold text-orange-900">{{ $antiSnipingDefaults['window_seconds'] }} detik</p>
                                    </div>
                                    <div class="rounded-lg border border-orange-200 bg-white px-3 py-2">
                                        <p class="text-[11px] font-semibold text-orange-600">Perpanjangan</p>
                                        <p class="mt-1 font-bold text-orange-900">{{ $antiSnipingDefaults['extend_seconds'] }} detik</p>
                                    </div>
                                    <div class="rounded-lg border border-orange-200 bg-white px-3 py-2">
                                        <p class="text-[11px] font-semibold text-orange-600">Maksimal</p>
                                        <p class="mt-1 font-bold text-orange-900">{{ $antiSnipingDefaults['max_extensions'] }} kali</p>
                                    </div>
                                </div>
                                <p>Estimasi akhir maksimal lelang: <span class="font-bold" x-text="potentialEndTime() || '-' "></span></p>
                                <p class="mt-1 text-[11px] text-orange-700/90">Untuk mengubah angka anti-sniping, admin bisa mengaturnya dari menu Setting Sistem.</p>
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                        <input type="hidden" name="mulai_sekarang" :value="mulaiSekarang ? 1 : 0">
                        <label class="inline-flex items-center gap-3 text-sm font-semibold text-emerald-800">
                            <input type="checkbox" x-model="mulaiSekarang" @change="syncMulaiSekarang()" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                            Mulai sekarang (instan)
                        </label>
                        <p class="text-[11px] text-emerald-700 mt-1">Mode ini cocok untuk lelang detik ini juga. Input manual tidak boleh backdated.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Waktu Mulai *</label>
                        <input type="datetime-local" name="waktu_mulai" x-model="waktuMulaiInput" :readonly="mulaiSekarang" :required="!mulaiSekarang" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400" :class="mulaiSekarang ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''">
                        @error('waktu_mulai')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Waktu Selesai *</label>
                        <input type="datetime-local" name="waktu_selesai" x-model="waktuSelesaiInput" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <p class="text-[11px] text-gray-400 mt-1">Waktu ini dapat bertambah otomatis jika anti-sniping aktif.</p>
                        @error('waktu_selesai')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="hidden sm:flex pt-2 border-t border-gray-100 items-center justify-between gap-3 flex-wrap">
                <button type="button" @click="prevStep()" x-show="step > 1" class="px-5 py-3 rounded-xl border border-gray-200 text-gray-700 font-bold hover:bg-gray-50 transition">
                    Kembali
                </button>
                <div class="ml-auto flex items-center gap-3">
                    <button type="button" @click="nextStep()" x-show="step < 2" class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition">
                        Lanjut
                    </button>
                    <button type="submit" x-show="step === 2" class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black transition text-base">
                        {{ $submitLabel }}
                    </button>
                </div>
            </div>

            <div class="sm:hidden fixed inset-x-0 z-30 px-4" style="bottom: calc(var(--bottom-nav-h) + 12px + env(safe-area-inset-bottom, 0px));">
                <div class="mx-auto max-w-4xl rounded-2xl border border-slate-200/90 bg-white/95 p-3 shadow-[0_18px_36px_-28px_rgba(15,23,42,0.52)] backdrop-blur">
                    <div class="flex items-center gap-3">
                        <button type="button" @click="nextStep()" x-show="step < 2" class="flex-1 px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition">
                            Lanjut
                        </button>
                        <button type="submit" x-show="step === 2" class="flex-1 px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black transition">
                            {{ $submitLabel }}
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection
