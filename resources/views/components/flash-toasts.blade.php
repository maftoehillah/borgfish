@props([
    'includeErrors' => true,
])

@php
    $flashToasts = [];

    $statusLabels = [
        'profile-updated' => 'Profil berhasil diperbarui.',
        'seller-profile-updated' => 'Profil toko berhasil diperbarui.',
        'password-updated' => 'Kata sandi berhasil diperbarui.',
        'verification-link-sent' => 'Link verifikasi baru sudah dikirim.',
    ];
    $errorBag = $errors ?? null;

    $pushToast = function (string $type, mixed $message, ?string $title = null, array $items = []) use (&$flashToasts): void {
        $message = trim((string) $message);

        if ($message === '' && $items === []) {
            return;
        }

        $flashToasts[] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'items' => $items,
        ];
    };

    if (session()->has('status')) {
        $status = (string) session('status');
        $pushToast('info', $statusLabels[$status] ?? $status);
    }

    if (session()->has('success')) {
        $pushToast('success', session('success'));
    }

    if (session()->has('sukses')) {
        $pushToast('success', session('sukses'));
    }

    if (session()->has('error')) {
        $pushToast('error', session('error'));
    }

    if ($includeErrors && $errorBag && $errorBag->any()) {
        $validationErrors = $errorBag->all();

        if (count($validationErrors) === 1) {
            $pushToast('error', $validationErrors[0]);
        } else {
            $pushToast(
                'error',
                'Periksa kembali ' . count($validationErrors) . ' kolom yang ditandai.',
                'Ada data yang perlu diperbaiki',
                $validationErrors,
            );
        }
    }
@endphp

@once
    <style>
        .bf-toast-stack {
            position: fixed;
            top: calc(env(safe-area-inset-top, 0px) + 82px);
            right: 16px;
            z-index: 1200;
            display: flex;
            width: min(420px, calc(100vw - 32px));
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .bf-toast {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) 28px;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            border: 1px solid #dbe7eb;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 18px 34px -24px rgba(15, 23, 42, 0.38);
            color: #0f172a;
            pointer-events: auto;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .bf-toast.is-entering,
        .bf-toast.is-leaving {
            opacity: 0;
            transform: translateY(-8px) scale(0.98);
        }

        .bf-toast-icon {
            display: inline-flex;
            width: 34px;
            height: 34px;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
            flex-shrink: 0;
        }

        .bf-toast-icon::before {
            content: 'i';
        }

        .bf-toast--success {
            border-color: #bbf7d0;
        }

        .bf-toast--success .bf-toast-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .bf-toast--success .bf-toast-icon::before {
            width: 13px;
            height: 7px;
            border-bottom: 3px solid currentColor;
            border-left: 3px solid currentColor;
            content: '';
            transform: rotate(-45deg) translate(1px, -1px);
        }

        .bf-toast--error {
            border-color: #fecdd3;
        }

        .bf-toast--error .bf-toast-icon {
            background: #ffe4e6;
            color: #be123c;
        }

        .bf-toast--error .bf-toast-icon::before {
            content: '!';
        }

        .bf-toast--info .bf-toast-icon {
            background: #cffafe;
            color: #0e7490;
        }

        .bf-toast-content {
            min-width: 0;
            padding-top: 1px;
        }

        .bf-toast-title {
            margin: 0 0 2px;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
            letter-spacing: 0;
        }

        .bf-toast-message {
            margin: 0;
            color: #334155;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.45;
            letter-spacing: 0;
        }

        .bf-toast-list {
            margin: 7px 0 0;
            padding-left: 16px;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.45;
        }

        .bf-toast-close {
            display: inline-flex;
            width: 28px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            transition: background 0.14s ease, color 0.14s ease;
        }

        .bf-toast-close:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .bf-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 1300;
            display: grid;
            place-items: center;
            padding: 16px;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }

        .bf-confirm-overlay[hidden] {
            display: none !important;
        }

        .bf-confirm-dialog {
            width: min(420px, 100%);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 28px 54px -28px rgba(15, 23, 42, 0.5);
            color: #0f172a;
            overflow: hidden;
        }

        .bf-confirm-body {
            padding: 20px 20px 16px;
        }

        .bf-confirm-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .bf-confirm-title {
            margin: 12px 0 0;
            color: #0f172a;
            font-size: 20px;
            font-weight: 900;
            line-height: 1.2;
        }

        .bf-confirm-message {
            margin: 8px 0 0;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
        }

        .bf-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 20px 20px;
        }

        .bf-confirm-button {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 12px;
            padding: 11px 16px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
        }

        .bf-confirm-cancel {
            background: #f1f5f9;
            color: #334155;
        }

        .bf-confirm-cancel:hover {
            background: #e2e8f0;
        }

        .bf-confirm-submit {
            background: #0e7490;
            color: #fff;
        }

        .bf-confirm-submit:hover {
            background: #155e75;
        }

        .bf-confirm-submit.is-danger {
            background: #dc2626;
        }

        .bf-confirm-submit.is-danger:hover {
            background: #b91c1c;
        }

        @media (max-width: 639px) {
            .bf-toast-stack {
                top: calc(env(safe-area-inset-top, 0px) + 72px);
                right: 12px;
                left: 12px;
                width: auto;
            }

            .bf-toast {
                grid-template-columns: 32px minmax(0, 1fr) 28px;
                gap: 10px;
                padding: 11px;
                border-radius: 14px;
            }

            .bf-toast-icon {
                width: 32px;
                height: 32px;
                border-radius: 10px;
            }

            .bf-confirm-actions {
                flex-direction: column-reverse;
            }

            .bf-confirm-button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .bf-toast {
                transition: none;
            }
        }
    </style>
@endonce

<div class="bf-toast-stack" aria-live="polite" aria-atomic="false" data-bf-toast-stack>
    @foreach($flashToasts as $toast)
        @php
            $isError = $toast['type'] === 'error';
            $duration = $isError ? 9000 : 5200;
            $items = $toast['items'];
        @endphp

        <div
            class="bf-toast bf-toast--{{ $toast['type'] }}"
            role="{{ $isError ? 'alert' : 'status' }}"
            data-bf-toast
            data-duration="{{ $duration }}"
        >
            <span class="bf-toast-icon" aria-hidden="true"></span>
            <div class="bf-toast-content">
                @if($toast['title'])
                    <p class="bf-toast-title">{{ $toast['title'] }}</p>
                @endif

                @if($toast['message'] !== '')
                    <p class="bf-toast-message">{{ $toast['message'] }}</p>
                @endif

                @if($items !== [])
                    <ul class="bf-toast-list">
                        @foreach(array_slice($items, 0, 3) as $item)
                            <li>{{ $item }}</li>
                        @endforeach

                        @if(count($items) > 3)
                            <li>{{ count($items) - 3 }} error lainnya.</li>
                        @endif
                    </ul>
                @endif
            </div>
            <button type="button" class="bf-toast-close" aria-label="Tutup notifikasi" data-bf-toast-close>
                &times;
            </button>
        </div>
    @endforeach
</div>

@once
    <div class="bf-confirm-overlay" data-bf-confirm-overlay hidden>
        <div class="bf-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="bf-confirm-title" aria-describedby="bf-confirm-message">
            <div class="bf-confirm-body">
                <span class="bf-confirm-kicker" data-bf-confirm-kicker>Konfirmasi</span>
                <h2 id="bf-confirm-title" class="bf-confirm-title" data-bf-confirm-title>Konfirmasi aksi</h2>
                <p id="bf-confirm-message" class="bf-confirm-message" data-bf-confirm-message>Pastikan data sudah benar sebelum melanjutkan.</p>
            </div>
            <div class="bf-confirm-actions">
                <button type="button" class="bf-confirm-button bf-confirm-cancel" data-bf-confirm-cancel>Batal</button>
                <button type="button" class="bf-confirm-button bf-confirm-submit" data-bf-confirm-submit>Lanjutkan</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const toastTypes = new Set(['success', 'error', 'info']);

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            const ensureToastStack = () => {
                let stack = document.querySelector('[data-bf-toast-stack]');

                if (!(stack instanceof HTMLElement)) {
                    stack = document.createElement('div');
                    stack.className = 'bf-toast-stack';
                    stack.setAttribute('aria-live', 'polite');
                    stack.setAttribute('aria-atomic', 'false');
                    stack.dataset.bfToastStack = '';
                    document.body.appendChild(stack);
                }

                return stack;
            };

            const dismissToast = (toast) => {
                if (!(toast instanceof HTMLElement) || toast.dataset.dismissed === 'true') {
                    return;
                }

                toast.dataset.dismissed = 'true';
                toast.classList.add('is-leaving');

                window.setTimeout(() => {
                    toast.remove();
                }, 190);
            };

            const armToast = (toast) => {
                if (!(toast instanceof HTMLElement) || toast.dataset.armed === 'true') {
                    return;
                }

                toast.dataset.armed = 'true';
                const duration = Number(toast.dataset.duration || 0);

                if (duration > 0) {
                    window.setTimeout(() => dismissToast(toast), duration);
                }
            };

            const createToast = (options) => {
                const type = toastTypes.has(options?.type) ? options.type : 'info';
                const message = String(options?.message ?? '').trim();
                const title = String(options?.title ?? '').trim();

                if (message === '' && title === '') {
                    return null;
                }

                const stack = ensureToastStack();
                const toast = document.createElement('div');
                toast.className = `bf-toast bf-toast--${type} is-entering`;
                toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
                toast.dataset.bfToast = '';
                toast.dataset.duration = String(Number.isFinite(options?.duration) ? Number(options.duration) : (type === 'error' ? 9000 : 5200));
                toast.innerHTML = `
                    <span class="bf-toast-icon" aria-hidden="true"></span>
                    <div class="bf-toast-content">
                        ${title ? `<p class="bf-toast-title">${escapeHtml(title)}</p>` : ''}
                        ${message ? `<p class="bf-toast-message">${escapeHtml(message)}</p>` : ''}
                    </div>
                    <button type="button" class="bf-toast-close" aria-label="Tutup notifikasi" data-bf-toast-close>&times;</button>
                `;

                stack.appendChild(toast);

                window.requestAnimationFrame(() => {
                    toast.classList.remove('is-entering');
                    armToast(toast);
                });

                return toast;
            };

            window.BorgfishToast = {
                show(payload, type = 'info') {
                    const options = typeof payload === 'string'
                        ? { message: payload, type }
                        : { ...(payload ?? {}) };

                    return createToast(options);
                },
                success(message, title = '') {
                    return createToast({ type: 'success', title, message });
                },
                error(message, title = '') {
                    return createToast({ type: 'error', title, message });
                },
                info(message, title = '') {
                    return createToast({ type: 'info', title, message });
                },
            };

            document.querySelectorAll('[data-bf-toast]').forEach(armToast);

            document.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : null;
                const closeButton = target?.closest('[data-bf-toast-close]');
                const toast = closeButton?.closest('[data-bf-toast]');

                if (toast instanceof HTMLElement) {
                    dismissToast(toast);
                }
            });

            const overlay = document.querySelector('[data-bf-confirm-overlay]');
            const title = document.querySelector('[data-bf-confirm-title]');
            const message = document.querySelector('[data-bf-confirm-message]');
            const kicker = document.querySelector('[data-bf-confirm-kicker]');
            const cancelButton = document.querySelector('[data-bf-confirm-cancel]');
            const submitButton = document.querySelector('[data-bf-confirm-submit]');
            let pendingForm = null;
            let pendingSubmitter = null;
            let previousFocus = null;

            const resetSubmitter = (submitter) => {
                if (!(submitter instanceof HTMLElement)) {
                    return;
                }

                submitter.removeAttribute('aria-busy');
                submitter.removeAttribute('disabled');
            };

            const closeConfirm = (resetPendingSubmitter = true) => {
                if (!(overlay instanceof HTMLElement)) {
                    return;
                }

                overlay.hidden = true;

                if (resetPendingSubmitter) {
                    resetSubmitter(pendingSubmitter);
                }

                pendingForm = null;
                pendingSubmitter = null;

                if (previousFocus instanceof HTMLElement) {
                    previousFocus.focus();
                }
            };

            const openConfirm = (form, submitter) => {
                if (!(overlay instanceof HTMLElement) || !(submitButton instanceof HTMLButtonElement)) {
                    return false;
                }

                pendingForm = form;
                pendingSubmitter = submitter instanceof HTMLElement ? submitter : form.querySelector('button[type="submit"], input[type="submit"]');
                previousFocus = document.activeElement;
                resetSubmitter(pendingSubmitter);

                if (title) {
                    title.textContent = form.dataset.confirmTitle || 'Konfirmasi aksi';
                }

                if (message) {
                    message.textContent = form.dataset.confirmMessage || 'Pastikan data sudah benar sebelum melanjutkan.';
                }

                if (kicker) {
                    kicker.textContent = form.dataset.confirmKicker || 'Konfirmasi';
                }

                submitButton.textContent = form.dataset.confirmConfirmLabel || 'Lanjutkan';
                submitButton.classList.toggle('is-danger', form.dataset.confirmVariant === 'danger');
                overlay.hidden = false;
                submitButton.focus();

                return true;
            };

            document.addEventListener('submit', (event) => {
                const form = event.target;

                if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-message]')) {
                    return;
                }

                if (form.dataset.confirmed === 'true') {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                openConfirm(form, event.submitter);
            }, true);

            cancelButton?.addEventListener('click', closeConfirm);

            overlay?.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    closeConfirm();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && overlay instanceof HTMLElement && !overlay.hidden) {
                    closeConfirm();
                }
            });

            submitButton?.addEventListener('click', () => {
                if (!(pendingForm instanceof HTMLFormElement)) {
                    closeConfirm();
                    return;
                }

                const form = pendingForm;
                const submitter = pendingSubmitter instanceof HTMLElement
                    ? pendingSubmitter
                    : form.querySelector('button[type="submit"], input[type="submit"]');

                form.dataset.confirmed = 'true';
                closeConfirm(false);

                if (form.requestSubmit) {
                    if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
                        form.requestSubmit(submitter);
                    } else {
                        form.requestSubmit();
                    }

                    return;
                }

                if (submitter instanceof HTMLElement) {
                    submitter.setAttribute('aria-busy', 'true');

                    if ('disabled' in submitter) {
                        submitter.setAttribute('disabled', 'disabled');
                    }
                }

                form.submit();
            });
        })();
    </script>
@endonce
