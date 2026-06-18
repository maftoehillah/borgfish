<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Borgfish - Lelang Ikan Online')</title>
    <meta name="theme-color" content="#0e3653">
    <link rel="icon" type="image/png" href="{{ asset('images/borgfish.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/borgfish.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Sora:wght@700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --bf-navy: #0e3653;
            --bf-ocean: #0a6a86;
            --bf-teal: #1e8e94;
            --bf-aqua: #52b6ab;
            --bf-cyan: #0081a1;
            --bf-cyan-hover: #006f88;
            --bf-yellow: #facc15;
            --bf-yellow-hover: #fde047;
            --bf-font-body: 'Manrope', 'Segoe UI', sans-serif;
            --bf-font-display: 'Sora', 'Manrope', 'Segoe UI', sans-serif;
            --bottom-nav-h: 68px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            min-height: 100svh;
            font-family: var(--bf-font-body);
            color: #0f172a;
            overflow-x: clip;
            background:
                radial-gradient(circle at 12% -12%, rgba(82, 182, 171, 0.14), transparent 42%),
                radial-gradient(circle at 90% 5%, rgba(30, 142, 148, 0.09), transparent 38%),
                linear-gradient(180deg, #f8fbfb 0%, #eff6f8 48%, #f8fcfc 100%);
        }

        html {
            scroll-behavior: smooth;
        }

        .font-display {
            font-family: var(--bf-font-display);
        }

        [x-cloak] {
            display: none !important;
        }

        .skip-link {
            position: absolute;
            left: -9999px;
            top: auto;
        }

        .skip-link:focus {
            left: 1rem;
            top: 1rem;
            z-index: 9999;
            padding: 0.5rem 0.75rem;
            background: #fff;
            color: #0f172a;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(2, 6, 23, 0.12);
        }

        .laravel-exceptions-renderer .shiki .mr-6,
        .laravel-exceptions-renderer .shiki code .mr-6,
        .laravel-exceptions-renderer .shiki .inline-block .mr-6 {
            color: #475569 !important;
        }

        .dark .laravel-exceptions-renderer .shiki .mr-6,
        .dark .laravel-exceptions-renderer .shiki code .mr-6,
        .dark .laravel-exceptions-renderer .shiki .inline-block .mr-6 {
            color: #cbd5e1 !important;
        }

        .laravel-exceptions-renderer .text-neutral-500 {
            color: #475569 !important;
        }

        .dark .laravel-exceptions-renderer .text-neutral-500 {
            color: #cbd5e1 !important;
        }

        .bf-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fff;
            border-bottom: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }

        .bf-nav-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .bf-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            text-decoration: none;
            flex-shrink: 0;
        }

        .bf-brand-logo {
            display: block;
            width: 38px;
            height: 38px;
            object-fit: contain;
            flex-shrink: 0;
            image-rendering: -webkit-optimize-contrast;
            filter: contrast(1.14) saturate(1.18) brightness(1.03) drop-shadow(0 2px 7px rgba(3, 28, 46, 0.24));
        }

        .bf-brand-wordmark {
            min-width: 0;
        }

        .bf-brand-name {
            display: block;
            font-family: var(--bf-font-display);
            font-size: 17px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.3px;
            color: #1e293b;
        }

        .bf-brand-sub {
            display: block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--bf-cyan);
        }

        .bf-nav-links {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex: 1;
        }

        .bf-nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 10px;
            text-decoration: none;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            transition: background 0.14s ease, color 0.14s ease;
        }

        .bf-nav-link:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .bf-nav-link.is-active {
            background: rgba(82, 182, 171, 0.14);
            color: var(--bf-cyan);
            font-weight: 700;
        }

        .bf-nav-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .bf-icon-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border: none;
            border-radius: 14px;
            background: #f1f5f9;
            color: #334155;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.14s ease;
        }

        .bf-icon-btn:hover {
            background: #e2e8f0;
        }

        .bf-icon-btn svg {
            width: 18px;
            height: 18px;
        }

        .bf-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 17px;
            height: 17px;
            padding: 0 3px;
            border: 2px solid #fff;
            border-radius: 999px;
            background: #f43f5e;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            line-height: 1;
        }

        .bf-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 48px;
            padding: 12px 16px;
            border: none;
            border-radius: 14px;
            background: var(--bf-yellow);
            color: #1e293b;
            text-decoration: none;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 700;
            transition: background 0.14s ease, box-shadow 0.14s ease;
        }

        .bf-upload-btn:hover {
            background: var(--bf-yellow-hover);
        }

        .bf-upload-btn.is-active {
            background: #fde047;
            box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.35);
        }

        .bf-upload-btn svg {
            width: 13px;
            height: 13px;
        }

        .bf-sa-wrap {
            display: none;
            align-items: center;
            gap: 5px;
        }

        .bf-sa-label {
            user-select: none;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
        }

        .bf-sa-track {
            position: relative;
            width: 40px;
            height: 24px;
            padding: 0;
            border: none;
            border-radius: 10px;
            background: #4ade80;
            cursor: pointer;
            transition: background 0.2s ease;
            flex-shrink: 0;
        }

        .bf-sa-knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
        }

        .bf-user-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 48px;
            padding: 8px 12px 8px 8px;
            border: none;
            border-radius: 14px;
            background: #f1f5f9;
            cursor: pointer;
            font-family: var(--bf-font-body);
            transition: background 0.14s ease;
        }

        .bf-user-btn:hover {
            background: #e2e8f0;
        }

        .bf-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--bf-ocean), var(--bf-teal));
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .bf-uname {
            display: none;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
        }

        .bf-role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
        }

        .bf-role-pembeli {
            background: #4ade80;
            color: #1e293b;
        }

        .bf-role-penjual {
            background: var(--bf-yellow);
            color: #1e293b;
        }

        .bf-role-admin {
            background: #f87171;
            color: #1e293b;
        }

        .bf-user-btn .caret {
            width: 12px;
            height: 12px;
            color: #94a3b8;
        }

        .bf-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 200;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 16px 28px -18px rgba(15, 23, 42, 0.18);
        }

        .bf-dropdown-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 11px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }

        .bf-dropdown-head-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }

        .bf-dropdown-head-action {
            display: inline-flex;
            align-items: center;
            min-height: 40px;
            border: none;
            background: none;
            color: var(--bf-cyan);
            cursor: pointer;
            font-size: 11.5px;
            font-weight: 600;
        }

        .bf-dropdown-item {
            display: flex;
            align-items: center;
            width: 100%;
            min-height: 48px;
            padding: 12px 16px;
            border: none;
            background: none;
            color: #334155;
            text-align: left;
            text-decoration: none;
            cursor: pointer;
            font-family: var(--bf-font-body);
            font-size: 13px;
            transition: background 0.12s ease;
        }

        .bf-dropdown-item:hover {
            background: #f8fafc;
        }

        .bf-dropdown-item.danger {
            color: #dc2626;
            border-top: 1px solid #f1f5f9;
        }

        .bf-dropdown-item.danger:hover {
            background: #fff5f5;
        }

    
        .bf-notif-pop {
            position: fixed !important;
            top: 75px !important;    
            left: 14px !important;  
            right: 14px !important;  
            width: auto !important;  
            margin: 0 auto !important;
            max-width: 500px !important;
            z-index: 9999 !important;
        }

        .bf-notif-list {
            max-height: 340px;
            overflow-y: auto;
        }

        .bf-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .bf-notif-dot {
            width: 7px;
            height: 7px;
            margin-top: 5px;
            border-radius: 50%;
            background: #f43f5e;
            flex-shrink: 0;
        }

        .bf-notif-content {
            min-width: 0;
            flex: 1;
        }

        .bf-notif-title {
            display: block;
            overflow: hidden;
            color: #1e293b;
            text-decoration: none;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12.5px;
            font-weight: 600;
        }

        .bf-notif-title:hover {
            color: var(--bf-cyan);
        }

        .bf-notif-msg {
            margin-top: 1px;
            color: #64748b;
            font-size: 11px;
        }

        .bf-notif-time {
            margin-top: 2px;
            color: #94a3b8;
            font-size: 10.5px;
        }

        .bf-notif-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
            flex-shrink: 0;
        }

        .bf-notif-link {
            color: #64748b;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
        }

        .bf-notif-link:hover {
            color: var(--bf-cyan);
        }

        .bf-notif-read-btn {
            border: none;
            background: none;
            color: var(--bf-cyan);
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
        }

        .bf-notif-footer {
            display: block;
            padding: 9px 14px;
            background: #f8fafc;
            color: var(--bf-cyan);
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.12s ease;
        }

        .bf-notif-footer:hover {
            background: #f1f5f9;
        }

        .bf-ghost-btn,
        .bf-primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.14s ease, color 0.14s ease;
        }

        .bf-ghost-btn {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #334155;
            font-weight: 600;
        }

        .bf-ghost-btn:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .bf-primary-btn {
            border: none;
            background: var(--bf-cyan);
            color: #fff;
            font-weight: 700;
        }

        .bf-primary-btn:hover {
            background: var(--bf-cyan-hover);
        }

        button[aria-busy="true"],
        input[aria-busy="true"] {
            cursor: wait;
            opacity: 0.82;
        }

        button[aria-busy="true"]::after {
            content: '';
            display: inline-block;
            box-sizing: border-box;
            width: 14px;
            height: 14px;
            margin-left: 8px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 999px;
            vertical-align: middle;
            animation: bf-spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        @keyframes bf-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .bf-main {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 16px 32px;
        }

        .bf-main img,
        .bf-main video {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .bf-has-mobile-nav .bf-main {
            padding-bottom: calc(var(--bottom-nav-h) + 24px);
        }

        .bf-bottom-nav {
            position: fixed;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 40;
            display: block;
            padding: 8px 10px;
            padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
            background: rgba(255, 255, 255, 0.97);
            border-top: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 -12px 28px -22px rgba(15, 23, 42, 0.38);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .bf-bottom-grid {
            display: grid;
            gap: 4px;
            position: relative;
        }

        .bf-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 58px;
            padding: 8px 6px 9px;
            border-radius: 14px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: 0.02em;
            transition: color 0.14s ease, background 0.14s ease;
        }

        .bf-tab svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .bf-tab.is-active {
            background: rgba(82, 182, 171, 0.14);
            color: var(--bf-cyan);
        }

        .bf-tab.is-active svg {
            stroke: var(--bf-cyan);
        }

        .bf-tab-upload {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            min-height: 58px;
            padding-bottom: 8px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.15;
        }

        .bf-fab {
            position: absolute;
            bottom: 18px;
            left: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border: 3px solid #fff;
            border-radius: 50%;
            background: var(--bf-yellow);
            box-shadow: 0 4px 18px rgba(250, 204, 21, 0.55);
            transform: translateX(-50%);
            transition: background 0.14s ease, transform 0.12s ease;
        }

        .bf-tab-upload:active .bf-fab,
        .bf-fab:active {
            background: var(--bf-yellow-hover);
            transform: translateX(-50%) scale(0.93);
        }

        .bf-fab.is-active {
            background: #fde047;
            box-shadow: 0 0 0 3px rgba(250, 204, 21, 0.3);
        }

        .bf-fab svg {
            width: 20px;
            height: 20px;
            color: #1e293b;
        }

        .bf-tab-upload span:last-child {
            margin-top: 40px;
            letter-spacing: 0.02em;
        }

        .bf-scroll-top {
            position: fixed;
            right: 16px;
            bottom: calc(var(--bottom-nav-h) + 20px + env(safe-area-inset-bottom, 0px));
            z-index: 35;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border: none;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.94);
            color: #fff;
            box-shadow: 0 16px 30px -18px rgba(15, 23, 42, 0.62);
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px) scale(0.96);
            transition: opacity 0.18s ease, transform 0.18s ease, background 0.18s ease;
        }

        .bf-scroll-top:hover {
            background: #0f172a;
        }

        .bf-scroll-top.is-visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .bf-scroll-top svg {
            width: 18px;
            height: 18px;
        }

        .bf-footer {
            margin-top: 64px;
            padding: 20px 16px 24px;
            background: #fff;
            border-top: 1px solid #e2e8f0;
        }

        .bf-has-mobile-nav .bf-footer {
            padding-bottom: calc(var(--bottom-nav-h) + 20px + env(safe-area-inset-bottom, 0px));
        }

        .bf-footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .bf-footer-primary {
            flex: 1 1 20rem;
            min-width: 0;
        }

        .bf-footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bf-footer-logo {
            display: block;
            width: 34px;
            height: 34px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .bf-footer-meta {
            min-width: 0;
        }

        .bf-footer-meta p {
            color: #64748b;
            line-height: 1.65;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .bf-footer-meta .copy {
            color: #334155;
            font-weight: 700;
        }

        .bf-footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
            margin-top: 8px;
            justify-content: center;
        }

        .bf-footer-links a {
            color: #64748b;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: color 0.13s ease;
        }

        .bf-footer-links a:hover {
            color: var(--bf-cyan);
        }

        .bf-footer-social {
            display: flex;
            width: 100%;
            flex-direction: column;
            align-items: center;
        }

        .bf-footer-social .label {
            margin-bottom: 6px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 10.5px;
            font-weight: 700;
            text-align: center;
        }

        .bf-footer-socials {
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        @media (max-width: 639px) {
            .bf-nav-inner {
                padding: 11px 14px;
                gap: 10px;
            }

            .bf-brand-name {
                font-size: 16px;
            }

            .bf-brand-sub {
                font-size: 8px;
            }

            .bf-main,
            .bf-has-mobile-nav .bf-main {
                padding: 20px 14px 32px;
            }

            .bf-has-mobile-nav .bf-main {
                padding-bottom: calc(var(--bottom-nav-h) + 28px);
            }

            .bf-main input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
            .bf-main select,
            .bf-main textarea {
                font-size: 16px;
            }

            .bf-scroll-top {
                right: 14px;
                width: 46px;
                height: 46px;
            }

            .bf-footer {
                margin-top: 48px;
                padding: 18px 14px 22px;
            }

            .bf-has-mobile-nav .bf-footer {
                padding-bottom: calc(var(--bottom-nav-h) + 18px + env(safe-area-inset-bottom, 0px));
            }

            .bf-footer-inner {
                gap: 16px;
            }

            .bf-footer-brand {
                align-items: flex-start;
            }

            .bf-footer-logo {
                width: 30px;
                height: 30px;
            }

            .bf-footer-links {
                justify-content: flex-start;
                gap: 6px 10px;
            }

            .bf-footer-social {
                align-items: flex-start;
            }

            .bf-footer-social .label,
            .bf-footer-socials {
                text-align: left;
                justify-content: flex-start;
            }
        }

        @media (min-width: 640px) {
            .bf-brand-logo {
                width: 42px;
                height: 42px;
            }

            .bf-brand-name {
                font-size: 20px;
            }

            .bf-notif-pop {
                width: 300px;
            }

            .bf-footer-links {
                justify-content: flex-start;
            }

            .bf-footer-social {
                width: auto;
                align-items: flex-start;
            }

            .bf-footer-social .label {
                text-align: left;
            }

            .bf-footer-socials {
                justify-content: flex-start;
            }
        }

        @media (min-width: 1024px) {
            .bf-uname {
                display: block;
            }

            .bf-main,
            .bf-has-mobile-nav .bf-main {
                padding-bottom: 32px;
            }

            .bf-footer,
            .bf-has-mobile-nav .bf-footer {
                padding-bottom: 24px;
            }

            .bf-nav-links {
                display: flex;
            }

            .bf-sa-wrap {
                display: inline-flex;
            }

            .bf-bottom-nav {
                display: none;
            }

            .bf-scroll-top {
                bottom: 24px;
            }
        }

        @media (max-width: 1023px) {
            .bf-upload-btn {
                display: none;
            }

            .bf-user-btn {
                min-width: 48px;
                padding-right: 10px;
            }

            .bf-role-badge {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .bf-scroll-top,
            button[aria-busy="true"]::after {
                transition: none;
                animation: none;
            }
        }

    </style>
</head>
<body class="@auth bf-has-mobile-nav @endauth">

<a href="#main" class="skip-link">Lewati ke konten</a>

<script>
    (function () {
        const currentPath = window.location.pathname;
        const currentSearch = window.location.search || '';
        const currentLocationKey = currentPath + currentSearch;
        const scrollKey = 'bf-scroll:' + currentLocationKey;
        const legacyScrollKey = 'bf-scroll:' + currentPath;
        const restoreKey = 'bf-scroll-restore:' + currentLocationKey;
        const legacyRestoreKey = 'bf-scroll-restore:' + currentPath;

        const normalizeLocationKey = function (urlLike) {
            try {
                const url = new URL(urlLike, window.location.origin);

                if (url.origin !== window.location.origin) {
                    return null;
                }

                return url.pathname + (url.search || '');
            } catch (_) {
                return null;
            }
        };

        const saveScroll = function () {
            try {
                const top = String(window.scrollY || window.pageYOffset || 0);
                sessionStorage.setItem(scrollKey, top);
                sessionStorage.setItem(legacyScrollKey, top);
            } catch (_) {
                // Ignore storage failures.
            }
        };

        const consumeRestoreFlag = function () {
            try {
                const shouldRestore = sessionStorage.getItem(restoreKey) === '1'
                    || sessionStorage.getItem(legacyRestoreKey) === '1';

                sessionStorage.removeItem(restoreKey);
                sessionStorage.removeItem(legacyRestoreKey);

                return shouldRestore;
            } catch (_) {
                return false;
            }
        };

        const refPath = (function () {
            if (!document.referrer) {
                return null;
            }

            try {
                return new URL(document.referrer).pathname;
            } catch (_) {
                return null;
            }
        })();

        const navEntry = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
        const navType = navEntry ? navEntry.type : 'navigate';
        const shouldRestore = !window.location.hash && (
            navType === 'reload'
            || navType === 'back_forward'
            || refPath === currentPath
            || consumeRestoreFlag()
        );

        if (shouldRestore) {
            try {
                const stored = sessionStorage.getItem(scrollKey) ?? sessionStorage.getItem(legacyScrollKey);
                const top = stored === null ? null : parseInt(stored, 10);

                if (top !== null && Number.isFinite(top) && top > 0) {
                    window.requestAnimationFrame(function () {
                        window.scrollTo(0, top);
                    });

                    window.setTimeout(function () {
                        window.scrollTo(0, top);
                    }, 120);
                }
            } catch (_) {
                // Ignore storage failures.
            }
        }

        window.addEventListener('beforeunload', saveScroll, { capture: true });
        window.addEventListener('pagehide', saveScroll, { capture: true });

        document.addEventListener('click', function (event) {
            const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;

            if (!anchor) {
                return;
            }

            if (anchor.dataset.restoreScrollTarget === 'true') {
                try {
                    const targetLocationKey = normalizeLocationKey(anchor.href);

                    if (targetLocationKey) {
                        sessionStorage.setItem('bf-scroll-restore:' + targetLocationKey, '1');
                    }
                } catch (_) {
                    // Ignore storage failures.
                }
            }

            saveScroll();
        }, { capture: true });

        document.addEventListener('submit', function () {
            saveScroll();
        }, { capture: true });
    })();
</script>

<nav class="bf-nav" role="navigation" aria-label="Navigasi utama">
    <div class="bf-nav-inner">
        <a href="{{ route('ikans.index') }}" class="bf-brand" aria-label="Borgfish - Halaman utama">
            <img src="{{ asset('images/borgfish.png') }}" alt="" aria-hidden="true" class="bf-brand-logo" decoding="async" fetchpriority="high">
            <span class="bf-brand-wordmark">
                <span class="bf-brand-name">Borgfish</span>
                <span class="bf-brand-sub">Lelang Ikan Online</span>
            </span>
        </a>

        @auth
            @php
                $headerNotifications = $headerNotifications ?? collect();
                $headerUnreadNotificationCount = (int) ($headerUnreadNotificationCount ?? 0);
                $isSuperAdmin = auth()->user()->isSuperAdmin();
                $superMode = session('superadmin_view_mode', 'PEMBELI');
                $showSellerNav = (!$isSuperAdmin && auth()->user()->isPenjual()) || ($isSuperAdmin && $superMode === 'PENJUAL');
                $showBuyerNav = (!$isSuperAdmin && auth()->user()->isPembeli()) || ($isSuperAdmin && $superMode === 'PEMBELI');

                $isMarketplaceActive = request()->routeIs('ikans.*');
                $isPenjualDashboardActive = request()->routeIs('penjual.dashboard');
                $isPenjualLotActive = request()->routeIs('penjual.ikans.index') || request()->routeIs('penjual.ikans.show');
                $isUploadIkanActive = request()->routeIs('penjual.ikans.create') || request()->routeIs('penjual.ikans.edit');
                $isAktivitasBidActive = request()->routeIs('pembeli.aktivitas*');
                $isRiwayatPembelianActive = request()->routeIs('pembeli.riwayat');

                $primaryRole = null;
                if ($isSuperAdmin) {
                    $primaryRole = $superMode === 'PENJUAL' ? 'penjual' : 'pembeli';
                } else {
                    $isPenjualUser = auth()->user()->isPenjual();
                    $isPembeliUser = auth()->user()->isPembeli();

                    if ($isPenjualUser && ! $isPembeliUser) {
                        $primaryRole = 'penjual';
                    } elseif ($isPembeliUser && ! $isPenjualUser) {
                        $primaryRole = 'pembeli';
                    } else {
                        if ($isPenjualDashboardActive || $isPenjualLotActive || $isUploadIkanActive) {
                            $primaryRole = 'penjual';
                        } elseif ($isAktivitasBidActive) {
                            $primaryRole = 'pembeli';
                        }
                    }
                }

                $showUploadPrimary = $primaryRole === 'penjual'
                    || ($primaryRole === null && auth()->user()->isPenjual() && ! auth()->user()->isPembeli());
            @endphp

            <div class="bf-nav-links" aria-label="Menu desktop">
                <a
                    href="{{ route('ikans.index') }}"
                    class="bf-nav-link {{ $isMarketplaceActive ? 'is-active' : '' }}"
                    @if($isMarketplaceActive) aria-current="page" @endif
                >
                    Marketplace
                </a>

                @if($showSellerNav)
                    <a
                        href="{{ route('penjual.dashboard') }}"
                        class="bf-nav-link {{ $isPenjualDashboardActive ? 'is-active' : '' }}"
                        @if($isPenjualDashboardActive) aria-current="page" @endif
                    >
                        Dashboard Penjual
                    </a>
                    <a
                        href="{{ route('penjual.ikans.index') }}"
                        class="bf-nav-link {{ $isPenjualLotActive ? 'is-active' : '' }}"
                        @if($isPenjualLotActive) aria-current="page" @endif
                    >
                        Aktivitas Lot
                    </a>
                @endif

                @if($showBuyerNav)
                    <a
                        href="{{ route('pembeli.aktivitas') }}"
                        class="bf-nav-link {{ $isAktivitasBidActive ? 'is-active' : '' }}"
                        @if($isAktivitasBidActive) aria-current="page" @endif
                    >
                        Aktivitas Bid
                    </a>
                    <a
                        href="{{ route('pembeli.riwayat') }}"
                        class="bf-nav-link {{ $isRiwayatPembelianActive ? 'is-active' : '' }}"
                        @if($isRiwayatPembelianActive) aria-current="page" @endif
                    >
                        Riwayat
                    </a>
                @endif
            </div>

            <div class="bf-nav-right">
                @if($isSuperAdmin)
                    <form method="POST" action="{{ route('admin.toggle_view_mode') }}" class="bf-sa-wrap">
                        @csrf
                        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                        <span class="bf-sa-label">{{ $superMode }}</span>
                        <button
                            type="button"
                            class="bf-sa-track"
                            data-mode="{{ $superMode }}"
                            aria-label="Toggle mode tampilan superadmin"
                            style="{{ $superMode === 'PENJUAL' ? 'background:#facc15;' : 'background:#4ade80;' }}"
                            onclick="(function (btn) {
                                var form = btn.closest('form');
                                var label = form.querySelector('.bf-sa-label');
                                var knob = btn.querySelector('.bf-sa-knob');
                                var currentMode = btn.dataset.mode || 'PEMBELI';
                                var nextMode = currentMode === 'PENJUAL' ? 'PEMBELI' : 'PENJUAL';
                                var fd = new FormData(form);

                                btn.dataset.mode = nextMode;
                                label.textContent = nextMode;
                                btn.style.background = nextMode === 'PENJUAL' ? '#facc15' : '#4ade80';
                                knob.style.transform = nextMode === 'PENJUAL' ? 'translateX(16px)' : 'translateX(0)';

                                fetch(form.action, {
                                    method: 'POST',
                                    body: fd,
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                    .then(function (response) { return response.json(); })
                                    .then(function (json) {
                                        if (json && json.redirect) {
                                            window.location.replace(json.redirect);
                                        } else {
                                            window.location.reload();
                                        }
                                    })
                                    .catch(function () {
                                        setTimeout(function () {
                                            form.submit();
                                        }, 180);
                                    });
                            })(this)"
                        >
                            <span
                                class="bf-sa-knob"
                                style="{{ $superMode === 'PENJUAL' ? 'transform:translateX(16px)' : 'transform:translateX(0)' }}"
                            ></span>
                        </button>
                    </form>

                    @if(request()->is('admin*'))
                        <x-back-button
                            :href="route('ikans.index')"
                            label="Kembali ke Marketplace"
                            class="hidden sm:inline-flex min-h-[36px] rounded-lg px-3 py-1.5 text-xs shadow-none"
                        />
                    @endif
                @endif

                @if($showSellerNav)
                    @if($showUploadPrimary)
                        <a
                            href="{{ route('penjual.ikans.create', ['return_url' => request()->fullUrl()]) }}"
                            class="bf-upload-btn {{ $isUploadIkanActive ? 'is-active' : '' }}"
                            aria-label="Upload ikan baru"
                        >
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                            </svg>
                            <span>Upload Ikan</span>
                        </a>
                    @else
                        <a href="{{ route('penjual.ikans.create', ['return_url' => request()->fullUrl()]) }}" class="bf-nav-link">
                            Upload
                        </a>
                    @endif
                @endif

                <div class="relative" x-data="{ openNotif: false }" @keydown.escape.window="openNotif = false">
                    <button
                        @click="openNotif = !openNotif"
                        type="button"
                        class="bf-icon-btn"
                        aria-label="Notifikasi{{ $headerUnreadNotificationCount > 0 ? ' (' . $headerUnreadNotificationCount . ' belum dibaca)' : '' }}"
                        data-unread-count="{{ $headerUnreadNotificationCount }}"
                        :aria-expanded="openNotif"
                    >
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9" />
                        </svg>
                        @if($headerUnreadNotificationCount > 0)
                            <span class="bf-badge" aria-hidden="true">
                                {{ $headerUnreadNotificationCount > 99 ? '99+' : $headerUnreadNotificationCount }}
                            </span>
                        @endif
                    </button>

                    <div
                        x-cloak
                        x-show="openNotif"
                        @click.outside="openNotif = false"
                        x-transition
                        class="bf-dropdown bf-notif-pop"
                        role="dialog"
                        aria-label="Panel notifikasi"
                    >
                        <div class="bf-dropdown-head">
                            <span class="bf-dropdown-head-title">Notifikasi</span>
                            @if($headerUnreadNotificationCount > 0)
                                <form method="POST" action="{{ route('notifications.read_all') }}">
                                    @csrf
                                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                    <button type="submit" class="bf-dropdown-head-action">Tandai semua dibaca</button>
                                </form>
                            @endif
                        </div>

                        @if($headerNotifications->isEmpty())
                            <p style="padding:16px 14px;font-size:13px;color:#64748b;">Belum ada notifikasi baru.</p>
                        @else
                            <ul class="bf-notif-list" role="list">
                                @foreach($headerNotifications as $headerNotif)
                                    <li class="bf-notif-item">
                                        @if($headerNotif->read_at === null)
                                            <span class="bf-notif-dot" aria-hidden="true"></span>
                                        @else
                                            <span style="width:7px;flex-shrink:0;" aria-hidden="true"></span>
                                        @endif
                                        <div class="bf-notif-content">
                                            <a href="{{ route('notifications.open', $headerNotif) }}" class="bf-notif-title">
                                                {{ $headerNotif->title }}
                                            </a>
                                            <p class="bf-notif-msg">{{ $headerNotif->message }}</p>
                                            <p class="bf-notif-time">{{ $headerNotif->created_at?->diffForHumans() }}</p>
                                        </div>
                                        <div class="bf-notif-actions">
                                            <a href="{{ route('notifications.open', $headerNotif) }}" class="bf-notif-link">Buka</a>
                                            @if($headerNotif->read_at === null)
                                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f43f5e;" aria-hidden="true"></span>
                                                <form method="POST" action="{{ route('notifications.read', $headerNotif) }}">
                                                    @csrf
                                                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                                    <button type="submit" class="bf-notif-read-btn">Dibaca</button>
                                                </form>
                                            @else
                                                <span style="font-size:11px;font-weight:600;color:#059669;">Sudah dibaca</span>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('notifications.index') }}" class="bf-notif-footer">
                                Lihat semua notifikasi
                            </a>
                        @endif
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button @click="open = !open" type="button" class="bf-user-btn" :aria-expanded="open" aria-haspopup="true">
                        <span class="bf-avatar" aria-hidden="true">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </span>
                        <span class="bf-uname">{{ auth()->user()->name }}</span>
                        <span class="bf-role-badge {{ $isSuperAdmin ? 'bf-role-admin' : (auth()->user()->role === 'penjual' ? 'bf-role-penjual' : 'bf-role-pembeli') }}">
                            {{ $isSuperAdmin ? 'ADMIN' : strtoupper(auth()->user()->role) }}
                        </span>
                        <svg class="caret" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div
                        x-cloak
                        x-show="open"
                        @click.outside="open = false"
                        x-transition
                        class="bf-dropdown"
                        style="width:210px;"
                        role="menu"
                    >
                        <div class="bf-dropdown-head" style="flex-direction:column;align-items:flex-start;gap:2px;">
                            <span class="bf-dropdown-head-title">{{ auth()->user()->name }}</span>
                            <span style="max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#64748b;">
                                {{ auth()->user()->email }}
                            </span>
                        </div>

                        @if($isSuperAdmin)
                            <form method="POST" action="{{ route('admin.toggle_view_mode') }}" style="border-bottom:1px solid #f1f5f9;">
                                @csrf
                                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                <button
                                    type="submit"
                                    class="bf-dropdown-item"
                                    role="menuitem"
                                    style="display:flex;justify-content:space-between;align-items:center;gap:8px;"
                                >
                                    <span>
                                        <span style="display:block;font-size:13px;font-weight:600;color:#1e293b;">Mode Tampilan</span>
                                        <span style="display:block;font-size:11px;color:#64748b;">Aktif: {{ $superMode === 'PENJUAL' ? 'Penjual' : 'Pembeli' }}</span>
                                    </span>
                                    <span
                                        style="position:relative;display:inline-flex;width:36px;height:20px;border-radius:10px;flex-shrink:0;{{ $superMode === 'PENJUAL' ? 'background:#facc15;' : 'background:#4ade80;' }}"
                                        aria-hidden="true"
                                    >
                                        <span
                                            style="position:absolute;top:2px;left:{{ $superMode === 'PENJUAL' ? '18px' : '2px' }};width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);"
                                        ></span>
                                    </span>
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="bf-dropdown-item" role="menuitem">Pengaturan Profil</a>

                        @if($isSuperAdmin)
                            <a href="/admin" class="bf-dropdown-item" role="menuitem">Admin Panel</a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="bf-dropdown-item danger" role="menuitem">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <div class="bf-nav-right">
                <a href="{{ route('login') }}" class="bf-ghost-btn">Masuk</a>
                <a href="{{ route('register') }}" class="bf-primary-btn">Daftar</a>
            </div>
        @endauth
    </div>
</nav>

<x-flash-toasts />

<main id="main" class="bf-main" tabindex="-1">
    @yield('content')
</main>

@auth
    @php
        $mobileShowSellerNav = $showSellerNav ?? false;
        $mobileShowBuyerNav = $showBuyerNav ?? false;
        $mobileHomeActive = request()->routeIs('ikans.*');
        $mobileSellerDashActive = request()->routeIs('penjual.dashboard');
        $mobileSellerLotActive = request()->routeIs('penjual.ikans.index') || request()->routeIs('penjual.ikans.show');
        $mobileBuyerActivityActive = request()->routeIs('pembeli.aktivitas*');
        $mobileBuyerHistoryActive = request()->routeIs('pembeli.riwayat');
        $mobileUploadActive = request()->routeIs('penjual.ikans.create') || request()->routeIs('penjual.ikans.edit');
        $mobileProfileActive = request()->routeIs('profile.*');

        $mobileNavItems = [
            [
                'href' => route('ikans.index'),
                'label' => 'Market',
                'active' => $mobileHomeActive,
                'icon' => 'market',
            ],
        ];

        if ($mobileShowSellerNav) {
            $mobileNavItems[] = [
                'href' => route('penjual.dashboard'),
                'label' => 'Dashboard',
                'active' => $mobileSellerDashActive,
                'icon' => 'dashboard',
            ];

            $mobileNavItems[] = [
                'href' => route('penjual.ikans.create', ['return_url' => request()->fullUrl()]),
                'label' => 'Upload',
                'active' => $mobileUploadActive,
                'icon' => 'upload',
                'is_upload' => true,
            ];
        }

        if ($mobileShowBuyerNav) {
            $mobileNavItems[] = [
                'href' => route('pembeli.aktivitas'),
                'label' => 'Aktivitas',
                'active' => $mobileBuyerActivityActive,
                'icon' => 'activity',
            ];

            $mobileNavItems[] = [
                'href' => route('pembeli.riwayat'),
                'label' => 'Riwayat',
                'active' => $mobileBuyerHistoryActive,
                'icon' => 'history',
            ];
        } elseif ($mobileShowSellerNav) {
            $mobileNavItems[] = [
                'href' => route('penjual.ikans.index'),
                'label' => 'Lot',
                'active' => $mobileSellerLotActive,
                'icon' => 'lot',
            ];
        }

        $mobileNavItems[] = [
            'href' => route('profile.edit'),
            'label' => 'Akun',
            'active' => $mobileProfileActive,
            'icon' => 'account',
        ];

        $colCount = count($mobileNavItems);
    @endphp

    <div class="bf-bottom-nav" role="navigation" aria-label="Navigasi mobile">
        <div class="bf-bottom-grid" style="grid-template-columns: repeat({{ $colCount }}, 1fr);">
            @foreach($mobileNavItems as $item)
                @php $isUpload = $item['is_upload'] ?? false; @endphp

                @if($isUpload)
                    <a href="{{ $item['href'] }}" class="bf-tab-upload" aria-label="Upload ikan baru">
                        <span class="bf-fab {{ $item['active'] ? 'is-active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                            </svg>
                        </span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <a
                        href="{{ $item['href'] }}"
                        class="bf-tab {{ $item['active'] ? 'is-active' : '' }}"
                        @if($item['active']) aria-current="page" @endif
                        aria-label="{{ $item['label'] }}"
                    >
                        @if($item['icon'] === 'market')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M7 12h10m-7 5h4" />
                            </svg>
                        @elseif($item['icon'] === 'dashboard')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h6V5H4v8Zm10 6h6V5h-6v14ZM4 19h6v-2H4v2Z" />
                            </svg>
                        @elseif($item['icon'] === 'activity')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11M5 6h.01M5 12h.01M5 18h.01" />
                            </svg>
                        @elseif($item['icon'] === 'history')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l2.5 2.5M22 12A10 10 0 1112 2a10 10 0 0110 10z" />
                            </svg>
                        @elseif($item['icon'] === 'lot')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5h14v4H5zM5 11h14v8H5z" />
                            </svg>
                        @elseif($item['icon'] === 'account')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.88 17.8M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        @endif

                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endauth

<button type="button" class="bf-scroll-top" data-scroll-top hidden aria-label="Kembali ke atas">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 5l-6 6m6-6l6 6m-6-6v14" />
    </svg>
</button>

<footer class="bf-footer">
    @php $footerSettings = app(\App\Services\SystemSettingService::class)->all(); @endphp
    <div class="bf-footer-inner">
        <div class="bf-footer-primary">
            <div class="bf-footer-brand">
                <img src="{{ asset('images/borgfish.png') }}" alt="Borgfish" class="bf-footer-logo" loading="lazy" decoding="async">
                <div class="bf-footer-meta">
                    <p class="copy">&copy; {{ date('Y') }} Borgfish</p>
                    <p>Sistem Lelang Ikan Online Indonesia</p>
                    <p>{{ $footerSettings['site_address'] ?? 'Indonesia' }} &mdash; {{ $footerSettings['site_email'] ?? 'admin@borgfish.test' }}</p>
                </div>
            </div>
            <nav class="bf-footer-links" aria-label="Tautan footer">
                <a href="{{ route('pages.about') }}">Tentang Kami</a>
                <a href="{{ route('pages.contact') }}">Kontak</a>
                <a href="{{ route('pages.payment_policy') }}">Pembayaran &amp; Dana</a>
                <a href="{{ route('pages.privacy') }}">Kebijakan Privasi</a>
                <a href="{{ route('pages.terms') }}">Syarat &amp; Ketentuan</a>
            </nav>
        </div>

        <div class="bf-footer-social">
            <p class="label">Ikuti Borgfish</p>
            <div class="bf-footer-socials">
                <x-social-links variant="footer" />
            </div>
        </div>
    </div>
</footer>

<script>
    document.addEventListener('submit', function (event) {
        const submitter = event.submitter instanceof HTMLElement
            ? event.submitter
            : null;

        if (!submitter) {
            return;
        }

        queueMicrotask(function () {
            if (!event.defaultPrevented) {
                return;
            }

            submitter.removeAttribute('aria-busy');
            submitter.removeAttribute('disabled');
        });
    });
</script>

@stack('scripts')
</body>
</html>
