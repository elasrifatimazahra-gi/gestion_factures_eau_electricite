<?php
$titreFournisseur = 'Détail de la réclamation';
$sousTitreFournisseur = 'Répondez au client et indiquez la décision.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
$types = ['FUITE_EXTERNE' => 'Fuite externe', 'FUITE_INTERNE' => 'Fuite interne', 'FACTURE' => 'Facture',
    'PROBLEME_COMPTEUR' => 'Problème de compteur', 'COUPURE_INEXPLIQUEE' => 'Coupure inexpliquée', 'AUTRE' => 'Autre'];
$statuts = ['OUVERTE' => 'Ouverte', 'EN_COURS' => 'En cours', 'TRAITEE' => 'Traitée', 'REJETEE' => 'Rejetée'];
?>
<a class="retour-clients" href="<?= BASE_URL ?>Reclamation/index">&larr; Retour aux réclamations</a>
<?php if ($message !== ''): ?><div class="clients-message clients-message--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="clients-message clients-message--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
<section class="dash-carte anomalie-detail-card">
    <h2><?= htmlspecialchars($reclamation['reference_reclamation']) ?></h2>
    <p><strong>Client :</strong> <?= htmlspecialchars(trim($reclamation['prenom'] . ' ' . $reclamation['nom'])) ?> (<?= htmlspecialchars($reclamation['numero_client']) ?>)</p>
    <p><strong>Motif :</strong> <?= htmlspecialchars($types[$reclamation['type_reclamation']] ?? $reclamation['type_reclamation']) ?>
        <?= $reclamation['autre_motif'] ? ' — ' . htmlspecialchars($reclamation['autre_motif']) : '' ?></p>
    <p><strong>Facture :</strong> <?= htmlspecialchars($reclamation['numero_facture'] ?? '—') ?></p>
    <p><strong>Statut :</strong> <?= htmlspecialchars($statuts[$reclamation['statut']] ?? $reclamation['statut']) ?></p>
    <section class="reclamation-conversation" aria-label="Conversation de la réclamation">
        <h3>Conversation</h3>
        <article class="reclamation-message reclamation-message--client">
            <div class="reclamation-message__meta">
                <strong><?= htmlspecialchars(trim($reclamation['prenom'] . ' ' . $reclamation['nom'])) ?></strong>
                <time><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($reclamation['created_at']))) ?></time>
            </div>
            <p><?= nl2br(htmlspecialchars($reclamation['description'])) ?></p>
        </article>
        <?php foreach ($reclamation['messages'] as $messageConversation): ?>
            <?php $estClient = $messageConversation['role_auteur'] === 'CLIENT'; ?>
            <article class="reclamation-message<?= $estClient ? ' reclamation-message--client' : ' reclamation-message--fournisseur' ?>">
                <div class="reclamation-message__meta">
                    <strong><?= $estClient ? htmlspecialchars($messageConversation['auteur']) : 'Vous' ?></strong>
                    <time><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($messageConversation['created_at']))) ?></time>
                </div>
                <p><?= nl2br(htmlspecialchars($messageConversation['message'])) ?></p>
            </article>
        <?php endforeach; ?>
        <?php if (!$reclamation['messages'] && $reclamation['reponse_fournisseur']): ?>
            <article class="reclamation-message reclamation-message--fournisseur">
                <div class="reclamation-message__meta">
                    <strong>Fournisseur</strong>
                    <time><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($reclamation['date_reponse'] ?: $reclamation['updated_at']))) ?></time>
                </div>
                <p><?= nl2br(htmlspecialchars($reclamation['reponse_fournisseur'])) ?></p>
            </article>
        <?php endif; ?>
    </section>
</section>
<?php if (in_array($reclamation['statut'], ['OUVERTE', 'EN_COURS'], true)): ?>
    <section class="dash-carte anomalie-action-card">
        <h2>Répondre au client</h2>
        <form class="reclamation-reponse-form" method="post" action="<?= BASE_URL ?>Reclamation/repondre/<?= (int) $reclamation['id_reclamation'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
            <label class="field" for="reponse_fournisseur">
                <span class="field__label">Réponse</span>
                <textarea class="field__input" id="reponse_fournisseur" name="reponse_fournisseur" rows="6" maxlength="5000" required></textarea>
            </label>
            <div class="reclamation-reponse-form__actions">
                <button type="submit" name="decision" value="continuer" class="btn">Envoyer une réponse</button>
                <button type="submit" name="decision" value="traiter" class="btn btn--primary">Répondre et clôturer</button>
                <button type="submit" name="decision" value="rejeter" class="btn">Répondre et rejeter</button>
            </div>
        </form>
    </section>
<?php else: ?>
    <p class="reclamation-conversation__closed">Cette réclamation est clôturée. La conversation est en lecture seule.</p>
<?php endif; ?>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
