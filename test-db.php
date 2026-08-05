<?php
require_once __DIR__ . '/config/db.php';

$sql = "SELECT p.id, p.nom, p.prix, c.nom AS categorie
        FROM produit p
        JOIN categorie c ON c.id = p.categorie_id
        WHERE p.actif = 1
        ORDER BY c.nom, p.nom";

$produits = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Test connexion</title></head>
<body>
    <h1>Test de connexion</h1>
    <p><?= count($produits) ?> produits trouvés.</p>
    <ul>
    <?php foreach ($produits as $p): ?>
        <li>
            <?= htmlspecialchars($p['nom']) ?>
            — <?= number_format($p['prix'], 2) ?> CHF
            (<?= htmlspecialchars($p['categorie']) ?>)
        </li>
    <?php endforeach; ?>
    </ul>
</body>
</html>