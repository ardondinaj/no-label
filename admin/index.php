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
</head>
<body>
    <h1>Administration NO LABEL</h1>

    <p>
        Connecté en tant que <?= e($_SESSION['utilisateur_prenom']) ?>
        <?= e($_SESSION['utilisateur_nom']) ?>
        — <a href="<?= BASE_URL ?>/logout.php">Déconnexion</a>
    </p>

    <?php foreach ($flash as $message): ?>
        <p><strong><?= e($message['texte']) ?></strong></p>
    <?php endforeach; ?>

    <h2>Vue d'ensemble</h2>
    <ul>
        <li><?= (int) $nbProduits ?> produits actifs</li>
        <li><?= (int) $nbCommandes ?> commandes</li>
        <li><?= (int) $nbClients ?> clients</li>
    </ul>

    <h2>Ruptures de stock</h2>
    <?php if ($rupture): ?>
        <ul>
        <?php foreach ($rupture as $r): ?>
            <li><?= e($r['nom']) ?> — taille <?= e($r['code']) ?></li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Aucune rupture.</p>
    <?php endif; ?>

    <h2>Gestion</h2>
    <ul>
        <li><a href="<?= BASE_URL ?>/admin/produits.php">Produits</a></li>
        <li><a href="<?= BASE_URL ?>/admin/commandes.php">Commandes</a></li>
    </ul>
</body>
</html>