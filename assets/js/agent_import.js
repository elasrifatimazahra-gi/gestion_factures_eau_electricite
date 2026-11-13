(() => {
    const zone = document.querySelector('[data-agent-dropzone]');
    if (!zone) return;

    const input = zone.querySelector('input[type="file"]');
    const filename = zone.querySelector('[data-file-name]');
    if (!(input instanceof HTMLInputElement) || !(filename instanceof HTMLElement)) return;

    const formatsAcceptes = ['txt', 'csv'];
    const tailleMaximale = 10 * 1024 * 1024;

    const afficherFichier = (fichier) => {
        zone.classList.remove('is-invalid');
        input.setCustomValidity('');

        if (!fichier) {
            filename.textContent = 'Aucun fichier sélectionné';
            return;
        }

        const extension = fichier.name.split('.').pop().toLowerCase();
        if (!formatsAcceptes.includes(extension)) {
            input.value = '';
            input.setCustomValidity('Choisissez un fichier TXT ou CSV.');
            zone.classList.add('is-invalid');
            filename.textContent = 'Format refusé · TXT ou CSV uniquement';
            return;
        }

        if (fichier.size > tailleMaximale) {
            input.value = '';
            input.setCustomValidity('Le fichier dépasse la limite de 10 Mo.');
            zone.classList.add('is-invalid');
            filename.textContent = 'Fichier trop volumineux · 10 Mo maximum';
            return;
        }

        filename.textContent = fichier.name;
    };

    input.addEventListener('change', () => afficherFichier(input.files?.[0]));

    zone.addEventListener('dragover', (event) => {
        event.preventDefault();
        zone.classList.add('is-dragover');
    });

    zone.addEventListener('dragleave', (event) => {
        if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragover');
    });

    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragover');

        const fichier = event.dataTransfer?.files?.[0];
        if (!fichier) return;

        const transfert = new DataTransfer();
        transfert.items.add(fichier);
        input.files = transfert.files;
        afficherFichier(fichier);
    });
})();
