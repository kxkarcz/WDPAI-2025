document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach(element => {
        setTimeout(() => {
            element.classList.add('is-hidden');
        }, parseInt(element.dataset.autoDismiss || '4000', 10));
    });
});