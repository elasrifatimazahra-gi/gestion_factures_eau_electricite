<?php
$prenom = $client['prenom'] ?? '';
$nom = $client['nom'] ?? '';
$initiales = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes notifications</title>
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
                <a class="cloche m1-bell" href="<?= BASE_URL ?>Notification/liste" aria-label="Notifications non lues : <?= (int) $notificationsNonLues ?>">
                    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <?php if ($notificationsNonLues > 0): ?><span class="m1-bell-count"><?= (int) $notificationsNonLues ?></span><?php endif; ?>
                </a>
                <span class="separateur"></span>
                <a class="deconnexion" href="<?= BASE_URL ?>Auth/logout">Déconnexion</a>
            </div>
        </header>
        <main class="contenu m1-page">
            <h1>Mes notifications</h1>
            <p class="sous-titre">Retrouvez les informations concernant votre compte.</p>
            <?php if ($message): ?><div class="m1-alert m1-alert--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($erreur): ?><div class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

            <div class="m1-notifications">
                <?php if (!$notifications): ?>
                    <div class="m1-empty">Vous n’avez aucune notification.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="m1-notification<?= $notification['lu'] ? '' : ' m1-notification--unread' ?>">
                            <div class="m1-notification__heading">
                                <h2><?= htmlspecialchars($notification['titre']) ?></h2>
                                <span class="m1-tag"><?= htmlspecialchars(str_replace('_', ' ', $notification['type'])) ?></span>
                            </div>
                            <p><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                            <div class="m1-notification__footer">
                                <time datetime="<?= htmlspecialchars($notification['date_envoi']) ?>">
                                    <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($notification['date_envoi']))) ?>
                                </time>
                                <?php if (!$notification['lu']): ?>
                                    <form method="post" action="<?= BASE_URL ?>Notification/lire">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                        <input type="hidden" name="id_notification" value="<?= (int) $notification['id_notification'] ?>">
                                        <button class="m1-button m1-button--small" type="submit">Marquer comme lue</button>
                                    </form>
                                <?php else: ?>
                                    <span class="m1-read-label">Lue</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
