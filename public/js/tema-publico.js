(() => {
    const root = document.documentElement;
    const preference = window.matchMedia('(prefers-color-scheme: dark)');
    let savedTheme;
    try { savedTheme = localStorage.getItem('aula-verde-tema'); } catch (_) {}
    const apply = (dark) => {
        root.dataset.theme = dark ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const label = dark ? 'Modo claro' : 'Modo oscuro';
            button.setAttribute('aria-pressed', String(dark));
            button.setAttribute('aria-label', `Activar ${label.toLowerCase()}`);
            button.querySelector('[data-theme-label]').textContent = label;
            button.hidden = false;
        });
    };
    const hasSavedTheme = () => savedTheme === 'dark' || savedTheme === 'light';
    apply(hasSavedTheme() ? savedTheme === 'dark' : preference.matches);
    document.addEventListener('DOMContentLoaded', () => {
        apply(root.dataset.theme === 'dark');
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                savedTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
                apply(savedTheme === 'dark');
                try { localStorage.setItem('aula-verde-tema', savedTheme); } catch (_) {}
            });
        });
    });
    preference.addEventListener('change', (event) => {
        if (!hasSavedTheme()) apply(event.matches);
    });
})();
