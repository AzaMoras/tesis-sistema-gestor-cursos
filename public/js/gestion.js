(() => {
    const nombre = document.getElementById('nombre');
    const slug = document.querySelector('[data-slug-sugerido]');
    if (!nombre || !slug) return;

    let manual = slug.dataset.slugManual === 'true';
    const sugerir = () => {
        if (manual) return;
        slug.value = nombre.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
            .slice(0, 150).replace(/-+$/g, '');
    };
    slug.addEventListener('input', () => { manual = true; });
    nombre.addEventListener('input', sugerir);
    sugerir();
})();
