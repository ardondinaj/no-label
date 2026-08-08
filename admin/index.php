<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

$nbProduits  = $pdo->query('SELECT COUNT(*) FROM produit WHERE actif = 1')->fetchColumn();
$nbCommandes = $pdo->query('SELECT COUNT(*) FROM commande')->fetchColumn();
$nbClients   = $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'client' AND actif = 1")->fetchColumn();

$rupture = $pdo->query(
    'SELECT p.nom, t.code
     FROM stock s
     JOIN produit p ON p.id = s.produit_id
     JOIN taille  t ON t.id = s.taille_id
     WHERE s.quantite = 0 AND p.actif = 1
     ORDER BY p.nom, t.ordre'
)->fetchAll();

$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — NO LABEL</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="admin-header">
    <h1>Administration NO LABEL</h1>
    <nav>
        <a href="<?= BASE_URL ?>/index.php">Voir la boutique</a>
        <a href="<?= BASE_URL ?>/admin/produits.php">Produits</a>
        <a href="<?= BASE_URL ?>/admin/commandes.php">Commandes</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php foreach ($flash as $message): ?>
        <p class="message message--<?= e($message['type']) ?>"><?= e($message['texte']) ?></p>
    <?php endforeach; ?>

    <p class="aide">
        Connecte en tant que <?= e($_SESSION['utilisateur_prenom']) ?>
        <?= e($_SESSION['utilisateur_nom']) ?>
    </p>

    <section class="apercu">
        <h2>Vue d'ensemble</h2>
        <dl>
            <dt>Produits actifs</dt><dd><?= (int) $nbProduits ?></dd>
            <dt>Commandes</dt><dd><?= (int) $nbCommandes ?></dd>
            <dt>Clients</dt><dd><?= (int) $nbClients ?></dd>
        </dl>
    </section>

    <section class="ruptures">
        <h2>Ruptures de stock</h2>

        <?php if ($rupture): ?>
            <table class="tableau">
                <thead>
                    <tr><th>Produit</th><th>Taille</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rupture as $r): ?>
                    <tr>
                        <td><?= e($r['nom']) ?></td>
                        <td class="stock--vide"><?= e($r['code']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucune rupture.</p>
        <?php endif; ?>
    </section>

    <section class="gestion">
        <h2>Gestion</h2>
        <p class="actions">
            <a href="<?= BASE_URL ?>/admin/produits.php" class="bouton">Produits</a>
            <a href="<?= BASE_URL ?>/admin/commandes.php" class="bouton">Commandes</a>
            <a href="<?= BASE_URL ?>/admin/produit-nouveau.php" class="bouton">Nouveau produit</a>
        </p>
    </section>

</main>

</body>
</html>