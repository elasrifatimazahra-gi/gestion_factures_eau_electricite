<?php $page = $_GET['url'] ?? ''; ?>
<aside class="sidebar">
    <div class="brand">
        <strong>Portail</strong>
        <small>EAU &amp; ÉLECTRICITÉ</small>
    </div>
    <p class="menu-label">MON ESPACE</p>
    <ul class="sidebar-menu">
        <li><a class="<?= $page === 'Auth/accueil' ? 'active' : '' ?>" href="<?= BASE_URL ?>Auth/accueil">Vue d'ensemble</a></li>
        <li><a class="<?= $page === 'Consommation/declaration' ? 'active' : '' ?>" href="<?= BASE_URL ?>Consommation/declaration">Ma déclaration</a></li>
        <li><a class="<?= $page === 'Facture/index' ? 'active' : '' ?>" href="<?= BASE_URL ?>Facture/index">Mes factures</a></li>
    </ul>
</aside>