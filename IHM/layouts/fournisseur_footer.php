        </main>
    </div>
</div>

<dialog id="m4-confirm" class="m4-modal">
    <form method="dialog">
        <div class="m4-modal__body">
            <h3 id="m4-confirm-title">Confirmation</h3>
            <p id="m4-confirm-text"></p>
            <p id="m4-confirm-detail" class="m4-modal__detail"></p>
        </div>
        <div class="m4-modal__actions">
            <button class="m1-button m1-button--secondary" type="submit" value="cancel">Annuler</button>
            <button class="m1-button" id="m4-confirm-ok" type="submit" value="ok">Confirmer</button>
        </div>
    </form>
</dialog>
<script src="<?= BASE_URL ?>assets/js/Fournisseur.js?v=<?= filemtime(ROOT . 'assets/js/Fournisseur.js') ?>" defer></script>
</body>
</html>