<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/fonctions.php';

// Nombre d'articles dans le panier
$nbPanier = 0;
foreach ($_SESSION['panier'] ?? [] as $ligne) {
    $nbPanier += (int) $ligne['quantite'];
}

$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titrePage) ? e($titrePage) . ' — NO LABEL' : 'NO LABEL' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site-header">
    <a href="<?= BASE_URL ?>/index.php" class="logo">NO LABEL</a>

    <nav class="nav-principale">
        <a href="<?= BASE_URL ?>/index.php">Boutique</a>
        <a href="<?= BASE_URL ?>/panier.php">Panier<?= $nbPanier > 0 ? ' (' . $nbPanier . ')' : '' ?></a>

        <?php if (isset($_SESSION['utilisateur_id'])): ?>
            <a href="<?= BASE_URL ?>/compte.php">Mon compte</a>
            <?php if (($_SESSION['utilisateur_role'] ?? '') === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/index.php">Administration</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php">Connexion</a>
            <a href="<?= BASE_URL ?>/inscription.php">Creer un compte</a>
        <?php endif; ?>
    </nav>
</header>

<?php if ($flash): ?>
    <div class="flash-zone">
        <?php foreach ($flash as $message): ?>
            <p class="message message--<?= e($message['type']) ?>"><?= e($message['texte']) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="contenu">