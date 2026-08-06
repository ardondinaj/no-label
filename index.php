<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/fonctions.php';

// --- Filtres ---
$recherche  = trim($_GET['q'] ?? '');
$categorieF = $_GET['categorie'] ?? '';
$tri        = $_GET['tri'] ?? 'nouveaute';

$categories = $pdo->query('SELECT id, nom, slug FROM categorie ORDER BY nom')->fetchAll();

// --- Requete : produit + categorie (jointure) + stock (agregation) ---
$sql = "SELECT p.id, p.nom, p.prix, p.image, p.description,
               c.nom AS categorie,
               COALESCE(SUM(s.quantite), 0) AS stock_total
        FROM produit p
        JOIN categorie c ON c.id = p.categorie_id
        LEFT JOIN stock s ON s.produit_id = p.id
        WHERE p.actif = 1";

$params = [];

if ($recherche !== '') {
    $sql .= ' AND p.nom LIKE :recherche';
    $params[':recherche'] = '%' . $recherche . '%';
}

if ($categorieF !== '') {
    $sql .= ' AND p.categorie_id = :categorie';
    $params[':categorie'] = $categorieF;
}

$sql .= ' GROUP BY p.id, p.nom, p.prix, p.image, p.description, c.nom';

// Tri
$tris = [
    'nouveaute'  => 'p.date_ajout DESC',
    'prix_asc'   => 'p.prix ASC',
    'prix_desc'  => 'p.prix DESC',
    'nom'        => 'p.nom ASC',
];
$sql .= ' ORDER BY ' . ($tris[$tri] ?? $tris['nouveaute']);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$titrePage = 'Boutique';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>NO LABEL</h1>
    <p>Vetements sans compromis. Series limitees, fabrication soignee.</p>
</section>

<section class="catalogue">

    <form method="get" action="" class="filtres-catalogue">
        <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="Rechercher">

        <select name="categorie">
            <option value="">Toutes categories</option>
            <?php foreach ($categories as $categorie): ?>
                <option value="<?= (int) $categorie['id'] ?>"
                    <?= (string) $categorieF === (string) $categorie['id'] ? 'selected' : '' ?>>
                    <?= e($categorie['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="tri">
            <option value="nouveaute" <?= $tri === 'nouveaute' ? 'selected' : '' ?>>Nouveautes</option>
            <option value="prix_asc"  <?= $tri === 'prix_asc'  ? 'selected' : '' ?>>Prix croissant</option>
            <option value="prix_desc" <?= $tri === 'prix_desc' ? 'selected' : '' ?>>Prix decroissant</option>
            <option value="nom"       <?= $tri === 'nom'       ? 'selected' : '' ?>>Nom</option>
        </select>

        <button type="submit">Filtrer</button>
        <?php if ($recherche !== '' || $categorieF !== ''): ?>
            <a href="<?= BASE_URL ?>/index.php">Reinitialiser</a>
        <?php endif; ?>
    </form>

    <p class="compteur"><?= count($produits) ?> article<?= count($produits) > 1 ? 's' : '' ?></p>

    <?php if (!$produits): ?>
        <p class="vide">Aucun article ne correspond a votre recherche.</p>
    <?php else: ?>
        <div class="grille-produits">
            <?php foreach ($produits as $produit): ?>
                <article class="carte-produit">
                    <a href="<?= BASE_URL ?>/produit.php?id=<?= (int) $produit['id'] ?>">
                        <div class="carte-produit__image">
                            <?php if ($produit['image']): ?>
                                <img src="<?= BASE_URL ?>/public/<?= e($produit['image']) ?>"
                                     alt="<?= e($produit['nom']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="image-absente"></span>
                            <?php endif; ?>

                            <?php if ((int) $produit['stock_total'] === 0): ?>
                                <span class="etiquette">Epuise</span>
                            <?php endif; ?>
                        </div>

                        <h2 class="carte-produit__nom"><?= e($produit['nom']) ?></h2>
                        <p class="carte-produit__categorie"><?= e($produit['categorie']) ?></p>
                        <p class="carte-produit__prix">
                            <?= number_format((float) $produit['prix'], 2, '.', ' ') ?> CHF
                        </p>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>