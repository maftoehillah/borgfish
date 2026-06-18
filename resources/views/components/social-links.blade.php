@props([
    'variant' => 'footer',
])

@php
    $settings = app(\App\Services\SystemSettingService::class)->all();
    $socialLinks = [
        [
            'key' => 'facebook',
            'label' => 'Facebook',
            'href' => trim((string) ($settings['social_facebook_url'] ?? '')),
            'aria' => 'Kunjungi Facebook Borgfish',
        ],
        [
            'key' => 'instagram',
            'label' => 'Instagram',
            'href' => trim((string) ($settings['social_instagram_url'] ?? '')),
            'aria' => 'Kunjungi Instagram Borgfish',
        ],
        [
            'key' => 'tiktok',
            'label' => 'TikTok',
            'href' => trim((string) ($settings['social_tiktok_url'] ?? '')),
            'aria' => 'Kunjungi TikTok Borgfish',
        ],
    ];

    $isCompact = $variant === 'compact';
    $hasActiveLinks = collect($socialLinks)->contains(
        fn (array $social): bool => $social['href'] !== ''
    );
    $wrapperClass = $isCompact
        ? 'flex flex-wrap items-center justify-center gap-2 sm:justify-start'
        : 'flex flex-wrap items-center justify-center gap-2 sm:justify-end';
    $activeLinkClass = 'inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-200/90 bg-white text-slate-700 shadow-sm shadow-cyan-900/10 transition duration-200 active:scale-[0.98] hover:-translate-y-0.5 hover:border-cyan-300 hover:bg-cyan-50/80 hover:text-cyan-700 hover:shadow-md hover:shadow-cyan-900/10 focus:outline-none focus:ring-2 focus:ring-cyan-100 sm:h-11 sm:w-11';
    $inactiveLinkClass = 'inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-300 shadow-sm shadow-slate-200/70 sm:h-11 sm:w-11';
    $iconClass = $isCompact ? 'h-[22px] w-[22px] sm:h-5 sm:w-5' : 'h-[22px] w-[22px] sm:h-5 sm:w-5';
@endphp

<div class="{{ $wrapperClass }}">
    @foreach($socialLinks as $social)
        @php
            $isAvailable = $social['href'] !== '';
            $placeholderLabel = $social['label'] . ' segera hadir';
        @endphp

        @if($isAvailable)
            <a
                href="{{ $social['href'] }}"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="{{ $social['aria'] }}"
                title="{{ $social['label'] }}"
                class="{{ $activeLinkClass }}"
            >
                @if($social['key'] === 'facebook')
                    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M13.5 21v-7.2h2.42l.36-2.8H13.5V9.22c0-.81.23-1.36 1.39-1.36H16.5V5.35c-.78-.08-1.57-.12-2.36-.11-2.34 0-3.94 1.42-3.94 4.05V11H7.8v2.8h2.4V21h3.3Z"/>
                    </svg>
                @elseif($social['key'] === 'instagram')
                    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3.25" y="3.25" width="17.5" height="17.5" rx="5.25" stroke="currentColor" stroke-width="1.9"/>
                        <circle cx="12" cy="12" r="4.1" stroke="currentColor" stroke-width="1.9"/>
                        <circle cx="17.25" cy="6.75" r="1.15" fill="currentColor"/>
                    </svg>
                @else
                    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M18.02 7.91c-1.22-.08-2.34-.48-3.28-1.15V14.3a4.73 4.73 0 1 1-4.73-4.73c.28 0 .55.03.82.08v2.35a2.42 2.42 0 1 0 1.6 2.3V2.99h2.31c.22 2.08 1.87 3.72 3.96 3.89v1.03Z"/>
                    </svg>
                @endif
            </a>
        @else
            <span
                class="{{ $inactiveLinkClass }}"
                role="img"
                aria-label="{{ $placeholderLabel }}"
                title="{{ $placeholderLabel }}"
            >
                @if($social['key'] === 'facebook')
                    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M13.5 21v-7.2h2.42l.36-2.8H13.5V9.22c0-.81.23-1.36 1.39-1.36H16.5V5.35c-.78-.08-1.57-.12-2.36-.11-2.34 0-3.94 1.42-3.94 4.05V11H7.8v2.8h2.4V21h3.3Z"/>
                    </svg>
                @elseif($social['key'] === 'instagram')
                    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3.25" y="3.25" width="17.5" height="17.5" rx="5.25" stroke="currentColor" stroke-width="1.9"/>
                        <circle cx="12" cy="12" r="4.1" stroke="currentColor" stroke-width="1.9"/>
                        <circle cx="17.25" cy="6.75" r="1.15" fill="currentColor"/>
                    </svg>
                @else
                    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M18.02 7.91c-1.22-.08-2.34-.48-3.28-1.15V14.3a4.73 4.73 0 1 1-4.73-4.73c.28 0 .55.03.82.08v2.35a2.42 2.42 0 1 0 1.6 2.3V2.99h2.31c.22 2.08 1.87 3.72 3.96 3.89v1.03Z"/>
                    </svg>
                @endif
            </span>
        @endif
    @endforeach

    @if(! $hasActiveLinks)
        <p class="w-full text-center text-[11px] font-semibold text-slate-400 sm:text-left">
            Akun resmi sedang disiapkan.
        </p>
    @endif
</div>
