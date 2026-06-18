<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0e3653">
        <link rel="icon" type="image/png" href="{{ asset('images/borgfish.png') }}">

        <title>Borgfish - Lelang Ikan Online</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Sora:wght@700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --bf-font-body: 'Manrope', 'Segoe UI', sans-serif;
                --bf-font-display: 'Sora', 'Manrope', 'Segoe UI', sans-serif;
            }

            body {
                font-family: var(--bf-font-body);
                overflow-x: clip;
            }

            .font-display {
                font-family: var(--bf-font-display);
            }

            .auth-bg {
                background:
                    radial-gradient(circle at 12% -12%, rgba(82, 182, 171, 0.16), transparent 42%),
                    radial-gradient(circle at 90% 5%, rgba(30, 142, 148, 0.1), transparent 38%),
                    linear-gradient(180deg, #f8fbfb 0%, #eff6f8 48%, #f8fcfc 100%);
            }

            .auth-surface {
                background: rgba(255, 255, 255, 0.94);
                border: 1px solid rgba(209, 250, 229, 0.85);
                box-shadow: 0 18px 28px -22px rgba(15, 23, 42, 0.45);
            }

            @media (max-width: 639px) {
                .auth-bg {
                    padding-top: 1.5rem;
                    padding-bottom: 1.5rem;
                }

                .auth-surface {
                    margin-top: 1.25rem;
                    padding: 1.25rem 1rem;
                    border-radius: 1.25rem;
                }
            }

            [x-cloak] {
                display: none !important;
            }
        </style>
    </head>
    <body class="text-slate-900 antialiased">
        <x-flash-toasts />

        <div class="auth-bg min-h-screen flex flex-col items-center px-4 py-8 sm:justify-center sm:py-6">
            @php
                $authLogoClass = request()->routeIs('login', 'register')
                    ? 'w-32 h-32 sm:w-36 sm:h-36'
                    : 'w-24 h-24';
            @endphp

            <div class="mb-2 text-center">
                <a href="/" class="block">
                    <x-application-logo class="{{ $authLogoClass }} drop-shadow-lg" />
                </a>
                <p class="font-display mt-2 text-2xl font-black text-slate-800 tracking-tight">Borgfish</p>
                <p class="text-sm font-semibold text-cyan-700">Lelang Ikan Online</p>
            </div>

            <div class="auth-surface mt-6 w-full overflow-hidden rounded-2xl px-5 py-5 sm:max-w-md sm:px-6">
                {{ $slot }}
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
