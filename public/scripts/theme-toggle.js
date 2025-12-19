document.addEventListener('click', async event => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    if (!target.closest('[data-theme-toggle]')) {
        return;
    }

    event.preventDefault();

    try {
        const response = await fetch('/theme/toggle', {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            }
        });
        if (!response.ok) throw new Error();
        const data = await response.json();
        document.documentElement.setAttribute('data-theme', data.theme);
    } catch (error) {
        console.warn('Nie udało się zmienić motywu.', error);
    }
});

