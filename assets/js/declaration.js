document.addEventListener('DOMContentLoaded', function () {

    const champs = [
        { saisie: 'index_eau',  precedent: 'eau-prec',  resultat: 'conso-eau',  unite: 'm³' },
        { saisie: 'index_elec', precedent: 'elec-prec', resultat: 'conso-elec', unite: 'kWh' }
    ];

    champs.forEach(function (c) {
        const saisie    = document.getElementById(c.saisie);
        const precedent = document.getElementById(c.precedent);
        const resultat  = document.getElementById(c.resultat);

        if (!saisie || !precedent || !resultat) return;

        saisie.addEventListener('input', function () {
            const nouvel = parseInt(saisie.value, 10);
            const ancien = parseInt(precedent.dataset.valeur, 10);

            if (isNaN(nouvel)) {
                resultat.textContent = '—';
                resultat.className   = '';
            } else {
                const diff = nouvel - ancien;
                resultat.textContent = diff + ' ' + c.unite;
                resultat.className   = diff < 0 ? 'anomalie' : '';
            }
        });
    });

    
    function apercu(inputId, imgId) {
        const input = document.getElementById(inputId);
        const img   = document.getElementById(imgId);
        if (!input || !img) return;

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                const url    = URL.createObjectURL(input.files[0]);
                img.src      = url;
                img.style.display = 'block';
                img.onload   = function () { URL.revokeObjectURL(url); };
            } else {
                img.src           = '';
                img.style.display = 'none';
            }
        });
    }

    apercu('photo_eau',  'apercu-eau');
    apercu('photo_elec', 'apercu-elec');
});