import './bootstrap';
import 'flowbite';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.disableSubmitLoading === 'false') return;

    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const targets = submitter
        ? [submitter]
        : Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

    targets.forEach((el) => {
        if ('disabled' in el) {
            el.disabled = true;
        }

        if (!(el instanceof HTMLButtonElement)) return;
        if (el.dataset.originalText) return;

        const loadingText = el.dataset.loadingText;
        if (!loadingText) return;

        el.dataset.originalText = el.innerHTML;
        el.innerHTML = loadingText;
    });
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
