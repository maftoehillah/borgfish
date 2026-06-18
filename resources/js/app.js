import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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

    if (!(stack instanceof HTMLElement) && document.body) {
        stack = document.createElement('div');
        stack.className = 'bf-toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'false');
        stack.dataset.bfToastStack = '';
        document.body.appendChild(stack);
    }

    return stack instanceof HTMLElement ? stack : null;
};

const dismissToast = (toast) => {
    if (!(toast instanceof HTMLElement) || toast.dataset.dismissed === 'true') {
        return;
    }

    toast.dataset.dismissed = 'true';
    toast.classList.add('bf-toast-leave', 'is-leaving');

    window.setTimeout(() => {
        toast.remove();
    }, 180);
};

const createToast = (options) => {
    const type = toastTypes.has(options?.type) ? options.type : 'info';
    const message = String(options?.message ?? '').trim();
    const title = String(options?.title ?? '').trim();

    if (message === '' && title === '') {
        return null;
    }

    const stack = ensureToastStack();

    if (!stack) {
        window.addEventListener('DOMContentLoaded', () => createToast(options), { once: true });
        return null;
    }

    const toast = document.createElement('div');
    toast.className = `bf-toast bf-toast--${type} bf-toast-enter bf-toast-enter-start`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.dataset.bfToast = '';
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
        toast.classList.remove('bf-toast-enter-start');
        toast.classList.add('bf-toast-enter-end');
    });

    window.setTimeout(() => {
        toast.classList.remove('bf-toast-enter', 'bf-toast-enter-end');
    }, 220);

    const duration = Number.isFinite(options?.duration)
        ? Number(options.duration)
        : type === 'error'
            ? 9000
            : 5200;

    if (duration > 0) {
        window.setTimeout(() => dismissToast(toast), duration);
    }

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

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const closeButton = target?.closest('[data-bf-toast-close]');
    const toast = closeButton?.closest('[data-bf-toast]');

    if (toast instanceof HTMLElement) {
        dismissToast(toast);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const scrollTopButton = document.querySelector('[data-scroll-top]');

    if (scrollTopButton instanceof HTMLButtonElement) {
        let ticking = false;

        const syncScrollTopButton = () => {
            const shouldShow = window.scrollY > 520;
            scrollTopButton.hidden = !shouldShow;
            scrollTopButton.classList.toggle('is-visible', shouldShow);
            ticking = false;
        };

        syncScrollTopButton();

        window.addEventListener('scroll', () => {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(syncScrollTopButton);
        }, { passive: true });

        scrollTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        });
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        const submitter = event.submitter instanceof HTMLElement
            ? event.submitter
            : form instanceof HTMLFormElement
                ? form.querySelector('button[type="submit"], input[type="submit"]')
                : null;

        if (!(form instanceof HTMLFormElement) || !(submitter instanceof HTMLElement)) {
            return;
        }

        if (form.matches('[data-confirm-message]') && form.dataset.confirmed !== 'true') {
            return;
        }

        if (submitter.getAttribute('aria-busy') === 'true' || submitter.dataset.disableSubmitLock === 'true') {
            return;
        }

        queueMicrotask(() => {
            if (event.defaultPrevented) {
                submitter.removeAttribute('aria-busy');
                submitter.removeAttribute('disabled');
                return;
            }

            submitter.setAttribute('aria-busy', 'true');

            if ('disabled' in submitter) {
                submitter.setAttribute('disabled', 'disabled');
            }
        });
    });
});
