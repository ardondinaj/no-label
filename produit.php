<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/fonctions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// --- Produit + categorie ---
$stmt = $pdo->prepare(
    'SELECT p.*, c.nom AS categorie
     FROM produit p
     JOIN categorie c ON c.id = p.categorie_id
     WHERE p.id = :id AND p.actif = 1'
);
$stmt->execute([':id' => $id]);
$produit = $stmt->fetch();

if (!$produit) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    messageFlash('erreur', 'Cet article n\'est plus disponible.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// --- Tailles disponibles (classe d'association stock) ---
$stmt = $pdo->prepare(
    'SELECT t.id, t.code, s.quantite
     FROM stock s
     JOIN taille t ON t.id = s.taille_id
     WHERE s.produit_id = :id
     ORDER BY t.ordre'
);
$stmt->execute([':id' => $id]);
$tailles = $stmt->fetchAll();

$stockTotal = array_sum(array_column($tailles, 'quantite'));

// --- Suggestions : meme categorie ---
$stmt = $pdo->prepare(
    'SELECT p.id, p.nom, p.prix, p.image
     FROM produit p
     WHERE p.categorie_id = :cat AND p.id <> :id AND p.actif = 1
     ORDER BY RAND()
     LIMIT 4'
);
$stmt->execute([':cat' => $produit['categorie_id'], ':id' => $id]);
$suggestions = $stmt->fetchAll();

$titrePage = $produit['nom'];
require __DIR__ . '/includes/header.php';
?>

<nav class="fil-ariane">
    <a href="<?= BASE_URL ?>/index.php">Boutique</a> /
    <a href="<?= BASE_URL ?>/index.php?categorie=<?= (int) $produit['categorie_id'] ?>">
        <?= e($produit['categorie']) ?>
    </a> /
    <span><?= e($produit['nom']) ?></span>
</nav>

<article class="fiche-produit">

    <div class="fiche-produit__visuel">
        <?php if ($produit['image']): ?>
            <img src="<?= BASE_URL ?>/public/<?= e($produit['image']) ?>"
                 alt="<?= e($produit['nom']) ?>">
        <?php else: ?>
            <span class="image-absente"></span>
        <?php endif; ?>
    </div>

    <div class="fiche-produit__infos">
        <p class="categorie"><?= e($produit['categorie']) ?></p>
        <h1><?= e($produit['nom']) ?></h1>
        <p class="prix"><?= number_format((float) $produit['prix'], 2, '.', ' ') ?> CHF</p>

        <?php if ($produit['description']): ?>
            <div class="description"><?= nl2br(e($produit['description'])) ?></div>
        <?php endif; ?>

        <?php if ($stockTotal === 0): ?>
            <p class="message message--attention">Cet article est actuellement epuise.</p>
        <?php else: ?>

            <form method="post" action="<?= BASE_URL ?>/panier-ajouter.php" class="form-achat">
                <input type="hidden" name="produit_id" value="<?= (int) $produit['id'] ?>">

                <fieldset class="choix-taille">
                    <legend>Taille</legend>

                    <?php foreach ($tailles as $taille): ?>
                        <?php $dispo = (int) $taille['quantite'] > 0; ?>
                        <label class="option-taille<?= $dispo ? '' : ' option-taille--indispo' ?>">
                            <input type="radio" name="taille_id"
                                   value="<?= (int) $taille['id'] ?>"
                                   <?= $dispo ? '' : 'disabled' ?> required>
                            <span><?= e($taille['code']) ?></span>
                            <?php if (!$dispo): ?>
                                <small>epuise</small>
                            <?php elseif ((int) $taille['quantite'] < 3): ?>
                                <small>plus que <?= (int) $taille['quantite'] ?></small>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <p class="champ-quantite">
                    <label for="quantite">Quantite</label>
                    <input type="number" id="quantite" name="quantite"
                           value="1" min="1" max="10">
                </p>

                <button type="submit" class="bouton bouton--principal">Ajouter au panier</button>
            </form>

        <?php endif; ?>

        <div class="infos-pratiques">
            <p>Paiement en boutique ou a la livraison.</p>
            <p>Retours acceptes sous 14 jours.</p>
        </div>
    </div>

</article>

<?php if ($suggestions): ?>
    <section class="suggestions">
        <h2>Dans la meme categorie</h2>
        <div class="grille-produits">
            <?php foreach ($suggestions as $suggestion): ?>
                <article class="carte-produit">
                    <a href="<?= BASE_URL ?>/produit.php?id=<?= (int) $suggestion['id'] ?>">
                        <div class="carte-produit__image">
                            <?php if ($suggestion['image']): ?>
                                <img src="<?= BASE_URL ?>/public/<?= e($suggestion['image']) ?>"
                                     alt="<?= e($suggestion['nom']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="image-absente"></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="carte-produit__nom"><?= e($suggestion['nom']) ?></h3>
                        <p class="carte-produit__prix">
                            <?= number_format((float) $suggestion['prix'], 2, '.', ' ') ?> CHF
                        </p>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>