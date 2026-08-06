<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/fonctions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$panier = $_SESSION['panier'] ?? [];
$lignes = [];
$total  = 0.0;

if ($panier) {
    // Une seule requete pour tout le panier
    $conditions = [];
    $params     = [];
    $i          = 0;

    foreach ($panier as $ligne) {
        $conditions[] = '(s.produit_id = :p' . $i . ' AND s.taille_id = :t' . $i . ')';
        $params[':p' . $i] = $ligne['produit_id'];
        $params[':t' . $i] = $ligne['taille_id'];
        $i++;
    }

    $sql = 'SELECT s.produit_id, s.taille_id, s.quantite AS stock,
                   p.nom, p.prix, p.image, p.actif,
                   t.code AS taille,
                   c.nom AS categorie
            FROM stock s
            JOIN produit   p ON p.id = s.produit_id
            JOIN taille    t ON t.id = s.taille_id
            JOIN categorie c ON c.id = p.categorie_id
            WHERE ' . implode(' OR ', $conditions);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $articles = [];
    foreach ($stmt->fetchAll() as $article) {
        $articles[$article['produit_id'] . '-' . $article['taille_id']] = $article;
    }

    foreach ($panier as $cle => $ligne) {
        if (!isset($articles[$cle]) || (int) $articles[$cle]['actif'] !== 1) {
            unset($_SESSION['panier'][$cle]);
            continue;
        }

        $article    = $articles[$cle];
        $quantite   = (int) $ligne['quantite'];
        $sousTotal  = $quantite * (float) $article['prix'];
        $total     += $sousTotal;

        $lignes[$cle] = $article + [
            'quantite'   => $quantite,
            'sous_total' => $sousTotal,
            'alerte'     => $quantite > (int) $article['stock'],
        ];
    }
}

$titrePage = 'Panier';
require __DIR__ . '/includes/header.php';
?>

<h1>Votre panier</h1>

<?php if (!$lignes): ?>

    <p class="vide">Votre panier est vide.</p>
    <p><a href="<?= BASE_URL ?>/index.php" class="bouton">Voir la boutique</a></p>

<?php else: ?>

    <table class="tableau tableau--panier">
        <thead>
            <tr>
                <th>Article</th>
                <th>Taille</th>
                <th>Prix</th>
                <th>Quantite</th>
                <th>Sous-total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $cle => $ligne): ?>
            <tr>
                <td>
                    <a href="<?= BASE_URL ?>/produit.php?id=<?= (int) $ligne['produit_id'] ?>">
                        <?= e($ligne['nom']) ?>
                    </a>
                    <small><?= e($ligne['categorie']) ?></small>
                    <?php if ($ligne['alerte']): ?>
                        <span class="erreur">Stock insuffisant (<?= (int) $ligne['stock'] ?> restant)</span>
                    <?php endif; ?>
                </td>
                <td><?= e($ligne['taille']) ?></td>
                <td><?= number_format((float) $ligne['prix'], 2, '.', ' ') ?> CHF</td>
                <td>
                    <form method="post" action="<?= BASE_URL ?>/panier-modifier.php" class="form-inline">
                        <input type="hidden" name="action" value="quantite">
                        <input type="hidden" name="cle" value="<?= e($cle) ?>">
                        <input type="number" name="quantite" value="<?= (int) $ligne['quantite'] ?>"
                               min="1" max="<?= (int) $ligne['stock'] ?>" size="3">
                        <button type="submit">OK</button>
                    </form>
                </td>
                <td><?= number_format($ligne['sous_total'], 2, '.', ' ') ?> CHF</td>
                <td>
                    <form method="post" action="<?= BASE_URL ?>/panier-modifier.php" class="form-inline">
                        <input type="hidden" name="action" value="retirer">
                        <input type="hidden" name="cle" value="<?= e($cle) ?>">
                        <button type="submit">Retirer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4">Total</th>
                <th><?= number_format($total, 2, '.', ' ') ?> CHF</th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="actions-panier">
        <form method="post" action="<?= BASE_URL ?>/panier-modifier.php" class="form-inline">
            <input type="hidden" name="action" value="vider">
            <button type="submit">Vider le panier</button>
        </form>

        <a href="<?= BASE_URL ?>/index.php">Continuer mes achats</a>

        <a href="<?= BASE_URL ?>/commande.php" class="bouton bouton--principal">
            Passer commande
        </a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>