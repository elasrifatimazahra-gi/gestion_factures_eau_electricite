<?php
$client = $client ?? [];
$typesLibelles = [
    'FUITE_EXTERNE' => 'Fuite externe',
    'FUITE_INTERNE' => 'Fuite interne',
    'FACTURE' => 'Facture',
    'PROBLEME_COMPTEUR' => 'Problème de compteur',
    'COUPURE_INEXPLIQUEE' => 'Coupure inexpliquée',
    'AUTRE' => 'Autre',
];
$statutsLibelles = ['OUVERTE' => 'Ouverte', 'EN_COURS' => 'En cours', 'TRAITEE' => 'Traitée', 'REJETEE' => 'Rejetée'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes réclamations</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/factures.css?v=<?= filemtime(ROOT . 'assets/css/factures.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/Parametre.css?v=<?= filemtime(ROOT . 'assets/css/Parametre.css') ?>">
</head>
<body>
<div class="appli">
    <?php include ROOT . 'IHM/layouts/sidebar_client.php'; ?>
    <div class="principal">
        <header class="topbar">
            <div class="statut-portail"><span class="point-vert"></span> Amanor opérationnel</div>
            <div class="topbar-droite">
                <?php include ROOT . 'IHM/client/selecteur_contrat.php'; ?>
                <a class="deconnexion" href="<?= BASE_URL ?>Auth/logout">Déconnexion</a>
            </div>
        </header>
        <main class="contenu m1-page">
            <h1>Mes réclamations</h1>
            <p class="sous-titre">Déposez une réclamation et suivez la réponse du fournisseur.</p>
            <?php if ($message !== ''): ?><div class="m1-alert m1-alert--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($erreur !== ''): ?><div class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

            <section class="m1-notifications">
                <article class="m1-notification m1-reclamation-card">
                    <h2>Déposer une réclamation</h2>
                    <form class="m1-reclamation-form" method="post" action="<?= BASE_URL ?>Reclamation/creer">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                        <label class="field" for="type_reclamation">
                            <span class="field__label">Motif</span>
                            <select class="field__input" id="type_reclamation" name="type_reclamation" required>
                                <?php foreach ($typesLibelles as $type => $libelle): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($libelle) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field" for="autre_motif">
                            <span class="field__label">Précisez le motif si vous avez choisi « Autre »</span>
                            <input class="field__input" id="autre_motif" name="autre_motif" maxlength="255">
                        </label>
                        <label class="field" for="id_facture">
                            <span class="field__label">Facture concernée (facultatif)</span>
                            <select class="field__input" id="id_facture" name="id_facture">
                                <option value="">Aucune</option>
                                <?php foreach ($factures as $facture): ?>
                                    <option value="<?= (int) $facture['id_facture'] ?>"><?= htmlspecialchars($facture['numero_facture']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field m1-reclamation-form__description" for="description">
                            <span class="field__label">Description</span>
                            <textarea class="field__input" id="description" name="description" rows="5" maxlength="5000" required></textarea>
                        </label>
                        <div class="m1-actions m1-reclamation-form__actions">
                            <button class="m1-button" type="submit">Envoyer la réclamation</button>
                        </div>
                    </form>
                </article>
            </section>

            <h2>Historique</h2>
            <div class="m1-notifications">
                <?php if (!$reclamations): ?>
                    <div class="m1-empty">Vous n’avez encore déposé aucune réclamation.</div>
                <?php else: foreach ($reclamations as $reclamation): ?>
                    <article class="m1-notification">
                        <div class="m1-notification__heading">
                            <h2><?= htmlspecialchars($reclamation['reference_reclamation']) ?></h2>
                            <span class="m1-tag"><?= htmlspecialchars($statutsLibelles[$reclamation['statut']] ?? $reclamation['statut']) ?></span>
                        </div>
                        <p><strong><?= htmlspecialchars($typesLibelles[$reclamation['type_reclamation']] ?? $reclamation['type_reclamation']) ?></strong>
                            <?= $reclamation['numero_facture'] ? ' · ' . htmlspecialchars($reclamation['numero_facture']) : '' ?></p>
                        <?php if ($reclamation['type_reclamation'] === 'AUTRE' && $reclamation['autre_motif']): ?>
                            <p><?= htmlspecialchars($reclamation['autre_motif']) ?></p>
                        <?php endif; ?>
                        <section class="reclamation-conversation" aria-label="Conversation de la réclamation">
                            <h3>Conversation</h3>
                            <article class="reclamation-message reclamation-message--client">
                                <div class="reclamation-message__meta">
                                    <strong>Vous</strong>
                                    <time><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($reclamation['created_at']))) ?></time>
                                </div>
                                <p><?= nl2br(htmlspecialchars($reclamation['description'])) ?></p>
                            </article>
                            <?php foreach ($reclamation['messages'] as $messageConversation): ?>
                                <?php $estClient = $messageConversation['role_auteur'] === 'CLIENT'; ?>
                                <article class="reclamation-message<?= $estClient ? ' reclamation-message--client' : ' reclamation-message--fournisseur' ?>">
                                    <div class="reclamation-message__meta">
                                        <strong><?= $estClient ? 'Vous' : 'Fournisseur' ?></strong>
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
                        <?php if (in_array($reclamation['statut'], ['OUVERTE', 'EN_COURS'], true)): ?>
                            <form class="m1-reclamation-reply" method="post" action="<?= BASE_URL ?>Reclamation/message">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                <input type="hidden" name="id_reclamation" value="<?= (int) $reclamation['id_reclamation'] ?>">
                                <label class="field" for="message-reclamation-<?= (int) $reclamation['id_reclamation'] ?>">
                                    <span class="field__label">Votre message</span>
                                    <textarea class="field__input" id="message-reclamation-<?= (int) $reclamation['id_reclamation'] ?>" name="message" rows="3" maxlength="5000" required></textarea>
                                </label>
                                <button class="m1-button" type="submit">Envoyer</button>
                            </form>
                        <?php else: ?>
                            <p class="reclamation-conversation__closed">Cette réclamation est clôturée. La conversation est en lecture seule.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
