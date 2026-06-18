@props([
    'transactionId' => null,
    'userName' => null,
    'userEmail' => null,
    'userPhone' => null,
    'userRole' => null,
    'variant' => 'full',
])

@php
    $currentUser = auth()->user();
    $settingsService = app(\App\Services\SystemSettingService::class);

    $userName = $userName ?: $currentUser?->name;
    $userEmail = $userEmail ?: $currentUser?->email;
    $userPhone = $userPhone ?: $currentUser?->whatsapp_number;
    $userRole = $userRole ?: $currentUser?->displayRoleLabel() ?: 'Pengunjung';

    $settings = $settingsService->all();
    $adminContact = trim((string) ($settings['site_admin_contact'] ?? $settings['admin_contact'] ?? ''));
    $number = preg_replace('/\D+/', '', (string) $adminContact);
    $message = $settingsService->renderWhatsappAdminContactMessage([
        'user_role' => $userRole,
        'user_name' => $userName,
        'user_email' => $userEmail,
        'user_phone' => $userPhone,
        'transaction_id' => $transactionId,
        'current_url' => request()->fullUrl(),
    ]);
    $userAgent = strtolower((string) request()->userAgent());
    $isMobileDevice = str_contains($userAgent, 'android')
        || str_contains($userAgent, 'iphone')
        || str_contains($userAgent, 'ipad')
        || str_contains($userAgent, 'mobile');

    $baseUrl = $isMobileDevice
        ? 'https://api.whatsapp.com/send'
        : 'https://web.whatsapp.com/send';

    $link = $baseUrl . '?' . http_build_query([
        'phone' => $number,
        'text' => $message,
    ]);
@endphp

@if($number !== '' && $variant === 'icon')
    <a
        href="{{ $link }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Hubungi admin via WhatsApp"
        title="WhatsApp Borgfish"
        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-emerald-200/80 bg-emerald-50/80 text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100/80 focus:outline-none focus:ring-2 focus:ring-cyan-100"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2a9.93 9.93 0 0 0-8.6 14.9L2 22l5.25-1.37a9.94 9.94 0 0 0 4.76 1.21h.01c5.49 0 9.96-4.46 9.97-9.94A9.86 9.86 0 0 0 19.05 4.91Zm-7.03 15.25h-.01a8.22 8.22 0 0 1-4.19-1.15l-.3-.18-3.12.81.83-3.04-.2-.31a8.24 8.24 0 0 1-1.27-4.4c0-4.56 3.72-8.28 8.29-8.28 2.21 0 4.29.86 5.85 2.43a8.2 8.2 0 0 1 2.42 5.85c0 4.57-3.72 8.28-8.3 8.28Zm4.54-6.2c-.25-.12-1.48-.73-1.71-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.98-.15.17-.29.19-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.45-1.37-1.7-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.12-.15.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.41-.56-.42h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.09s.9 2.42 1.02 2.59c.12.17 1.76 2.69 4.26 3.77.6.26 1.06.42 1.43.54.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.18.21-.58.21-1.08.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
        </svg>
    </a>
@elseif($number !== '')
    <div class="flex w-full flex-col items-start">
        <a href="{{ $link }}"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Hubungi admin via WhatsApp"
           class="group inline-flex w-full items-center justify-between gap-4 rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-left text-slate-900 shadow-sm transition duration-200 hover:border-emerald-300 hover:bg-emerald-100/80 focus:outline-none focus:ring-2 focus:ring-emerald-100">
            <span class="flex min-w-0 items-center gap-3">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2a9.93 9.93 0 0 0-8.6 14.9L2 22l5.25-1.37a9.94 9.94 0 0 0 4.76 1.21h.01c5.49 0 9.96-4.46 9.97-9.94A9.86 9.86 0 0 0 19.05 4.91Zm-7.03 15.25h-.01a8.22 8.22 0 0 1-4.19-1.15l-.3-.18-3.12.81.83-3.04-.2-.31a8.24 8.24 0 0 1-1.27-4.4c0-4.56 3.72-8.28 8.29-8.28 2.21 0 4.29.86 5.85 2.43a8.2 8.2 0 0 1 2.42 5.85c0 4.57-3.72 8.28-8.3 8.28Zm4.54-6.2c-.25-.12-1.48-.73-1.71-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.98-.15.17-.29.19-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.45-1.37-1.7-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.12-.15.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.41-.56-.42h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.09s.9 2.42 1.02 2.59c.12.17 1.76 2.69 4.26 3.77.6.26 1.06.42 1.43.54.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.18.21-.58.21-1.08.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
                    </svg>
                </span>
                <span class="min-w-0">
                    <span class="block text-base font-bold leading-5 text-slate-900">Chat Admin</span>
                    <span class="mt-1 block text-xs font-medium leading-4 text-slate-600">Butuh bantuan cepat soal akun atau transaksi?</span>
                </span>
            </span>
            <span class="inline-flex shrink-0 items-center rounded-2xl bg-white px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 transition group-hover:bg-emerald-600 group-hover:text-white group-hover:ring-emerald-600">
                Buka WhatsApp
            </span>
        </a>
    </div>
@endif
