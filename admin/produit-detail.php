<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    messageFlash('erreur', 'Produit introuvable.');
    rediriger(BASE_URL . '/admin/produits.php');
}

// --- MAITRE : le produit et sa categorie ---
$stmt = $pdo->prepare(
    'SELECT p.*, c.nom AS categorie
     FROM produit p
     JOIN categorie c ON c.id = p.categorie_id
     WHERE p.id = :id'
);
$stmt->execute([':id' => $id]);
$produit = $stmt->fetch();

if (!$produit) {
    messageFlash('erreur', 'Ce produit n\'existe pas.');
    rediriger(BASE_URL . '/admin/produits.php');
}

// --- DETAIL 1 : stocks par taille (classe d'association stock) ---
$stmt = $pdo->prepare(
    'SELECT t.code, t.ordre, s.quantite
     FROM stock s
     JOIN taille t ON t.id = s.taille_id
     WHERE s.produit_id = :id
     ORDER BY t.ordre'
);
$stmt->execute([':id' => $id]);
$stocks = $stmt->fetchAll();

// --- DETAIL 2 : commandes contenant ce produit (classe d'association ligne_commande) ---
$stmt = $pdo->prepare(
    'SELECT co.id, co.numero, co.date_commande, co.statut,
            u.nom, u.prenom,
            t.code AS taille,
            lc.quantite, lc.prix_unitaire,
            (lc.quantite * lc.prix_unitaire) AS sous_total
     FROM ligne_commande lc
     JOIN commande    co ON co.id = lc.commande_id
     JOIN utilisateur u  ON u.id = co.utilisateur_id
     JOIN taille      t  ON t.id = lc.taille_id
     WHERE lc.produit_id = :id
     ORDER BY co.date_commande DESC'
);
$stmt->execute([':id' => $id]);
$commandes = $stmt->fetchAll();

// Totaux
$stockTotal = array_sum(array_column($stocks, 'quantite'));
$vendues    = array_sum(array_column($commandes, 'quantite'));
$chiffre    = array_sum(array_column($commandes, 'sous_total'));

$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($produit['nom']) ?> — Administration NO LABEL</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

</head>
<body>

<header class="admin-header">
    <h1><?= e($produit['nom']) ?></h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/produits.php">Retour a la liste</a>
        <a href="<?= BASE_URL ?>/admin/index.php">Tableau de bord</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php foreach ($flash as $message): ?>
        <p class="message message--<?= e($message['type']) ?>"><?= e($message['texte']) ?></p>
    <?php endforeach; ?>

    <section class="fiche">
        <h2>Fiche produit</h2>
        <dl>
            <dt>Categorie</dt><dd><?= e($produit['categorie']) ?></dd>
            <dt>Prix</dt><dd><?= number_format((float) $produit['prix'], 2, '.', ' ') ?> CHF</dd>
            <dt>Statut</dt><dd><?= (int) $produit['actif'] === 1 ? 'Actif' : 'Retire du catalogue' ?></dd>
            <dt>Ajoute le</dt>
            <dd><?= date('d.m.Y', strtotime($produit['date_ajout'])) ?></dd>
            <dt>Image</dt><dd><?= $produit['image'] ? e($produit['image']) : 'Aucune' ?></dd>
            <dt>Description</dt>
            <dd><?= $produit['description'] ? nl2br(e($produit['description'])) : 'Aucune' ?></dd>
        </dl>

        <p class="actions">
            <a href="<?= BASE_URL ?>/admin/produit-modifier.php?id=<?= (int) $produit['id'] ?>">Modifier</a>
        </p>
    </section>

    <section class="stocks">
        <h2>Stock par taille (<?= (int) $stockTotal ?> pieces)</h2>

        <?php if (!$stocks): ?>
            <p>Aucune taille enregistree pour ce produit.</p>
        <?php else: ?>
            <table class="tableau">
                <thead>
                    <tr><th>Taille</th><th>Quantite</th><th>Etat</th></tr>
                </thead>
                <tbody>
                <?php foreach ($stocks as $stock): ?>
                    <tr>
                        <td><?= e($stock['code']) ?></td>
                        <td><?= (int) $stock['quantite'] ?></td>
                        <td>
                            <?php if ((int) $stock['quantite'] === 0): ?>
                                Rupture
                            <?php elseif ((int) $stock['quantite'] < 3): ?>
                                Stock faible
                            <?php else: ?>
                                Disponible
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="ventes">
        <h2>Commandes contenant ce produit</h2>

        <?php if (!$commandes): ?>
            <p>Ce produit n'a encore jamais ete commande.</p>
        <?php else: ?>
            <p><?= (int) $vendues ?> piece(s) vendue(s) pour
               <?= number_format((float) $chiffre, 2, '.', ' ') ?> CHF.</p>

            <table class="tableau">
                <thead>
                    <tr>
                        <th>Commande</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Taille</th>
                        <th>Qte</th>
                        <th>Prix unit.</th>
                        <th>Sous-total</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($commandes as $ligne): ?>
                    <tr>
                        <td><?= e($ligne['numero']) ?></td>
                        <td><?= date('d.m.Y', strtotime($ligne['date_commande'])) ?></td>
                        <td><?= e($ligne['prenom']) ?> <?= e($ligne['nom']) ?></td>
                        <td><?= e($ligne['taille']) ?></td>
                        <td><?= (int) $ligne['quantite'] ?></td>
                        <td><?= number_format((float) $ligne['prix_unitaire'], 2, '.', ' ') ?></td>
                        <td><?= number_format((float) $ligne['sous_total'], 2, '.', ' ') ?></td>
                        <td><?= e(str_replace('_', ' ', $ligne['statut'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</main>

</body>
</html>