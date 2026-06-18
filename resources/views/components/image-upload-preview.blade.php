@props([
    'name',
    'id' => null,
    'label',
    'required' => false,
    'accept' => 'image/*',
    'hint' => 'Format JPG, PNG, atau WebP. Maksimal 3 MB.',
    'existingUrl' => null,
    'existingLabel' => 'Foto tersimpan saat ini',
    'maxSizeMb' => 3,
    'labelClass' => 'block text-sm font-semibold text-slate-700',
    'hintClass' => 'mt-1 text-xs text-slate-500',
    'inputClass' => 'mt-1 block min-h-[46px] w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-cyan-700 hover:file:bg-cyan-100',
])

@php
    $inputId = $id ?: $name;
@endphp

<div x-data="imageUploadPreview({
    existingUrl: @js($existingUrl),
    existingLabel: @js($existingLabel),
    maxSizeMb: @js($maxSizeMb)
})">
    <label for="{{ $inputId }}" class="{{ $labelClass }}">{{ $label }}</label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="file"
        accept="{{ $accept }}"
        class="{{ $inputClass }}"
        x-ref="input"
        x-on:change="update($event)"
        @required($required)
    >

    @if($hint)
        <p class="{{ $hintClass }}">{{ $hint }}</p>
    @endif

    <p x-show="errorText" x-cloak class="mt-2 text-xs font-semibold text-rose-600" x-text="errorText"></p>

    <template x-if="previewUrl || existingUrl">
        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-3">
            <div class="flex items-start gap-3">
                <img
                    x-bind:src="previewUrl || existingUrl"
                    alt="Preview foto"
                    loading="lazy"
                    decoding="async"
                    class="h-20 w-20 shrink-0 rounded-xl border border-slate-100 object-cover"
                >
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-extrabold text-slate-800" x-text="previewUrl ? 'Preview file baru' : existingLabel"></p>
                    <p class="mt-1 truncate text-xs text-slate-500" x-show="fileName" x-text="fileName"></p>
                    <p class="mt-1 text-xs text-slate-500" x-show="fileSizeText" x-text="fileSizeText"></p>
                    <button
                        type="button"
                        class="mt-2 inline-flex rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:bg-slate-100"
                        x-show="previewUrl"
                        x-on:click="clear()"
                    >
                        Hapus pilihan
                    </button>
                </div>
            </div>
        </div>
    </template>

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>

@once
    @push('scripts')
        <script>
            function imageUploadPreview(config) {
                return {
                    previewUrl: '',
                    fileName: '',
                    fileSizeText: '',
                    errorText: '',
                    existingUrl: config.existingUrl || '',
                    existingLabel: config.existingLabel || 'Foto tersimpan saat ini',
                    maxSizeMb: Number(config.maxSizeMb || 3),
                    update(event) {
                        const file = event.target.files && event.target.files[0]
                            ? event.target.files[0]
                            : null;

                        this.revokePreview();
                        this.previewUrl = '';
                        this.fileName = '';
                        this.fileSizeText = '';
                        this.errorText = '';

                        if (! file) {
                            return;
                        }

                        if (! file.type || ! file.type.startsWith('image/')) {
                            this.fileName = file.name;
                            this.errorText = 'File bukan gambar. Pilih JPG, PNG, atau WebP.';
                            event.target.value = '';
                            return;
                        }

                        if (this.maxSizeMb > 0 && file.size > this.maxSizeMb * 1024 * 1024) {
                            this.fileName = file.name;
                            this.errorText = `Ukuran file ${this.formatSize(file.size)}. Maksimal ${this.maxSizeMb} MB.`;
                            event.target.value = '';
                            return;
                        }

                        this.previewUrl = URL.createObjectURL(file);
                        this.fileName = file.name;
                        this.fileSizeText = this.formatSize(file.size);
                    },
                    clear() {
                        this.revokePreview();
                        this.previewUrl = '';
                        this.fileName = '';
                        this.fileSizeText = '';
                        this.errorText = '';

                        if (this.$refs.input) {
                            this.$refs.input.value = '';
                        }
                    },
                    revokePreview() {
                        if (this.previewUrl) {
                            URL.revokeObjectURL(this.previewUrl);
                        }
                    },
                    formatSize(bytes) {
                        if (! bytes) {
                            return '0 KB';
                        }

                        const megabytes = bytes / 1024 / 1024;

                        if (megabytes >= 1) {
                            return `${megabytes.toFixed(2)} MB`;
                        }

                        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
                    },
                };
            }
        </script>
    @endpush
@endonce
