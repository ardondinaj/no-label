<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

// Filtres
$recherche  = trim($_GET['q'] ?? '');
$categorieF = $_GET['categorie'] ?? '';
$inactifs   = isset($_GET['inactifs']);

$categories = $pdo->query('SELECT id, nom FROM categorie ORDER BY nom')->fetchAll();

// Requete sur 3 tables : produit + categorie (jointure) + stock (agregation)
$sql = "SELECT p.id, p.nom, p.prix, p.image, p.actif, p.date_ajout,
               c.nom AS categorie,
               COALESCE(SUM(s.quantite), 0) AS stock_total,
               COUNT(DISTINCT s.taille_id)  AS nb_tailles
        FROM produit p
        JOIN categorie c ON c.id = p.categorie_id
        LEFT JOIN stock s ON s.produit_id = p.id
        WHERE 1 = 1";

$params = [];

if (!$inactifs) {
    $sql .= ' AND p.actif = 1';
}

if ($recherche !== '') {
    $sql .= ' AND p.nom LIKE :recherche';
    $params[':recherche'] = '%' . $recherche . '%';
}

if ($categorieF !== '') {
    $sql .= ' AND p.categorie_id = :categorie';
    $params[':categorie'] = $categorieF;
}

$sql .= ' GROUP BY p.id, p.nom, p.prix, p.image, p.actif, p.date_ajout, c.nom
          ORDER BY c.nom, p.nom';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits — Administration NO LABEL</title>
</head>
<body>

<header class="admin-header">
    <h1>Produits</h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/index.php">Tableau de bord</a>
        <a href="<?= BASE_URL ?>/admin/commandes.php">Commandes</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php foreach ($flash as $message): ?>
        <p class="message message--<?= e($message['type']) ?>"><?= e($message['texte']) ?></p>
    <?php endforeach; ?>

    <p class="actions">
        <a href="<?= BASE_URL ?>/admin/produit-nouveau.php" class="bouton">Nouveau produit</a>
    </p>

    <form method="get" action="" class="filtres">
        <label for="q">Rechercher</label>
        <input type="text" id="q" name="q" value="<?= e($recherche) ?>" placeholder="Nom du produit">

        <label for="categorie">Categorie</label>
        <select id="categorie" name="categorie">
            <option value="">Toutes</option>
            <?php foreach ($categories as $categorie): ?>
                <option value="<?= (int) $categorie['id'] ?>"
                    <?= (string) $categorieF === (string) $categorie['id'] ? 'selected' : '' ?>>
                    <?= e($categorie['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>
            <input type="checkbox" name="inactifs" value="1" <?= $inactifs ? 'checked' : '' ?>>
            Afficher les produits retires
        </label>

        <button type="submit">Filtrer</button>
        <a href="<?= BASE_URL ?>/admin/produits.php">Reinitialiser</a>
    </form>

    <p class="resultat"><?= count($produits) ?> produit(s)</p>

    <?php if (!$produits): ?>
        <p>Aucun produit ne correspond a ces criteres.</p>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Categorie</th>
                    <th>Prix</th>
                    <th>Tailles</th>
                    <th>Stock</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produits as $produit): ?>
                <tr class="<?= (int) $produit['actif'] === 0 ? 'ligne--inactive' : '' ?>">
                    <td>
                        <a href="<?= BASE_URL ?>/admin/produit-detail.php?id=<?= (int) $produit['id'] ?>">
                            <?= e($produit['nom']) ?>
                        </a>
                    </td>
                    <td><?= e($produit['categorie']) ?></td>
                    <td><?= number_format((float) $produit['prix'], 2, '.', ' ') ?> CHF</td>
                    <td><?= (int) $produit['nb_tailles'] ?></td>
                    <td class="<?= (int) $produit['stock_total'] === 0 ? 'stock--vide' : '' ?>">
                        <?= (int) $produit['stock_total'] ?>
                    </td>
                    <td><?= (int) $produit['actif'] === 1 ? 'Actif' : 'Retire' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/produit-detail.php?id=<?= (int) $produit['id'] ?>">Detail</a>
                        <a href="<?= BASE_URL ?>/admin/produit-modifier.php?id=<?= (int) $produit['id'] ?>">Modifier</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</main>

</body>
</html>