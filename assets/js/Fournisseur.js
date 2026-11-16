// Membre 4 : messages intégrés à la page + fenêtre de confirmation (Paiements, Coupures, Journal).
// Chargé par IHM/layouts/fournisseur_footer.php (script "defer" : la page est déjà analysée à l'exécution).
(function () {
    // Messages : bouton de fermeture + disparition automatique des messages de succès
    document.querySelectorAll('.m4-notice').forEach(function (n) {
        var b = n.querySelector('.m4-notice__close');
        if (b) { b.addEventListener('click', function () { n.remove(); }); }
        if (n.classList.contains('m4-notice--success')) {
            setTimeout(function () { n.classList.add('is-hidden'); setTimeout(function () { n.remove(); }, 450); }, 8000);
        }
    });

    // Confirmation : tout <form data-confirm="..."> passe par la fenêtre #m4-confirm
    //   data-confirm         texte de la question
    //   data-confirm-title   titre de la fenêtre
    //   data-confirm-detail  ligne de détail, avec {champ} = valeur du champ du formulaire
    //   data-confirm-label   texte du bouton de validation
    //   data-confirm-danger  "1" = bouton de validation rouge
    var dlg = document.getElementById('m4-confirm');
    var pending = null;
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || !f.dataset || !f.dataset.confirm) { return; }
        var detail = (f.dataset.confirmDetail || '').replace(/\{(\w+)\}/g, function (m, nom) {
            return f.elements[nom] ? f.elements[nom].value : '';
        });
        if (!dlg || typeof dlg.showModal !== 'function') {
            if (!window.confirm(f.dataset.confirm + (detail ? '\n' + detail : ''))) { e.preventDefault(); }
            return;
        }
        e.preventDefault();
        pending = f;
        document.getElementById('m4-confirm-title').textContent = f.dataset.confirmTitle || 'Confirmation';
        document.getElementById('m4-confirm-text').textContent = f.dataset.confirm;
        document.getElementById('m4-confirm-detail').textContent = detail;
        var ok = document.getElementById('m4-confirm-ok');
        ok.textContent = f.dataset.confirmLabel || 'Confirmer';
        ok.classList.toggle('m4-btn-danger', f.dataset.confirmDanger === '1');
        dlg.returnValue = '';
        dlg.showModal();
    });
    if (dlg) {
        dlg.addEventListener('close', function () {
            if (dlg.returnValue === 'ok' && pending) { pending.submit(); }
            pending = null;
        });
    }
})();