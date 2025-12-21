document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach(element => {
        setTimeout(() => {
            element.classList.add('is-hidden');
        }, parseInt(element.dataset.autoDismiss || '4000', 10));
    });

    const navToggle = document.querySelector('[data-nav-toggle]');
    const navMenu = document.querySelector('[data-nav-menu]');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            const isExpanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', !isExpanded);
            
            if (!isExpanded) {
                navMenu.setAttribute('data-nav-menu-open', '');
            } else {
                navMenu.removeAttribute('data-nav-menu-open');
            }
        });

        navMenu.querySelectorAll('.app-nav__item').forEach(item => {
            item.addEventListener('click', () => {
                navToggle.setAttribute('aria-expanded', 'false');
                navMenu.removeAttribute('data-nav-menu-open');
            });
        });

        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navToggle.setAttribute('aria-expanded', 'false');
                navMenu.removeAttribute('data-nav-menu-open');
            }
        });
    }
});