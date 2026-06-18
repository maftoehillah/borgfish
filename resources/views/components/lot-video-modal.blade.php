@props([
    'videoUrl' => null,
    'modalId' => 'lot-video-modal',
    'title' => 'Video Lot',
    'description' => 'Buka video dalam pop-up.',
    'buttonLabel' => 'Lihat Video',
])

@if($videoUrl)
    <div {{ $attributes->merge(['class' => 'border-t border-slate-100 bg-slate-50/80 p-5']) }}
        x-data="{
            showVideo: false,
            closeVideo() {
                this.showVideo = false;

                if (this.$refs.videoPlayer) {
                    this.$refs.videoPlayer.pause();
                    this.$refs.videoPlayer.currentTime = 0;
                }
            },
        }"
        x-init="$watch('showVideo', value => document.body.classList.toggle('overflow-y-hidden', value))"
        x-on:keydown.escape.window="if (showVideo) closeVideo()"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500">{{ $title }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $description }}</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800"
                @click="showVideo = true"
                aria-haspopup="dialog"
                aria-controls="{{ $modalId }}"
            >
                {{ $buttonLabel }}
            </button>
        </div>

        <div
            x-cloak
            x-show="showVideo"
            x-transition.opacity
            id="{{ $modalId }}"
            class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
            role="dialog"
            aria-modal="true"
            aria-label="{{ $title }}"
        >
            <div class="absolute inset-0 bg-slate-950/75" @click="closeVideo()"></div>

            <div
                x-show="showVideo"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                class="relative w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-2xl sm:max-w-xl lg:max-w-lg xl:max-w-md"
            >
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-black text-slate-900">{{ $title }}</h3>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        @click="closeVideo()"
                        aria-label="Tutup video"
                    >
                        <span class="text-lg leading-none">&times;</span>
                    </button>
                </div>

                <div class="bg-slate-950 p-3 sm:p-4">
                    <video
                        x-ref="videoPlayer"
                        src="{{ $videoUrl }}"
                        controls
                        preload="metadata"
                        playsinline
                        class="mx-auto max-h-[75vh] w-full rounded-2xl bg-black sm:w-auto sm:max-w-full lg:max-h-[50vh] xl:max-h-[46vh]"
                    ></video>
                </div>
            </div>
        </div>
    </div>
@endif
