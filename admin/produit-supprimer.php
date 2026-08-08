<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    messageFlash('erreur', 'Produit introuvable.');
    rediriger(BASE_URL . '/admin/produits.php');
}

// --- Le produit et sa categorie ---
$stmt = $pdo->prepare(
    'SELECT p.id, p.nom, p.prix, p.actif, c.nom AS categorie
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

// --- JOINTURE : ce produit est-il engage dans des commandes ? ---
$stmt = $pdo->prepare(
    "SELECT co.numero, co.statut, co.date_commande,
            u.nom, u.prenom,
            t.code AS taille, lc.quantite
     FROM ligne_commande lc
     JOIN commande    co ON co.id = lc.commande_id
     JOIN utilisateur u  ON u.id = co.utilisateur_id
     JOIN taille      t  ON t.id = lc.taille_id
     WHERE lc.produit_id = :id
       AND co.statut IN ('en_attente_paiement', 'payee', 'expediee')
     ORDER BY co.date_commande DESC"
);
$stmt->execute([':id' => $id]);
$commandesEnCours = $stmt->fetchAll();

// --- JOINTURE : stock encore disponible ? ---
$stmt = $pdo->prepare(
    'SELECT t.code, s.quantite
     FROM stock s
     JOIN taille t ON t.id = s.taille_id
     WHERE s.produit_id = :id AND s.quantite > 0
     ORDER BY t.ordre'
);
$stmt->execute([':id' => $id]);
$stockRestant = $stmt->fetchAll();
$totalRestant = array_sum(array_column($stockRestant, 'quantite'));

// --- Historique complet (commandes terminees comprises) ---
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM ligne_commande WHERE produit_id = :id'
);
$stmt->execute([':id' => $id]);
$nbLignesTotal = (int) $stmt->fetchColumn();

$erreur = null;

// --- Traitement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'retirer') {

        if ($commandesEnCours) {
            $erreur = 'Ce produit figure dans ' . count($commandesEnCours)
                    . ' commande(s) non terminee(s). Traitez-les avant de le retirer.';
        } elseif (!isset($_POST['confirmation'])) {
            $erreur = 'Veuillez cocher la case de confirmation.';
        } else {
            $pdo->beginTransaction();
            try {
                // Suppression LOGIQUE : le produit reste en base
                $stmt = $pdo->prepare(
                    'UPDATE produit SET actif = 0 WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);

                // Les stocks sont remis a zero : plus rien n'est reservable
                $stmt = $pdo->prepare(
                    'UPDATE stock SET quantite = 0 WHERE produit_id = :id'
                );
                $stmt->execute([':id' => $id]);

                $pdo->commit();

                messageFlash('succes',
                    'Le produit « ' . $produit['nom'] . ' » a ete retire du catalogue. '
                    . 'Son historique de commandes est conserve.');

                rediriger(BASE_URL . '/admin/produits.php');

            } catch (PDOException $e) {
                $pdo->rollBack();
                $erreur = DEBUG ? $e->getMessage() : 'Le retrait a echoue.';
            }
        }

    } elseif ($action === 'reactiver') {

        $stmt = $pdo->prepare('UPDATE produit SET actif = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);

        messageFlash('succes',
            'Le produit « ' . $produit['nom'] . ' » est de nouveau au catalogue. '
            . 'Pensez a reapprovisionner les stocks.');

        rediriger(BASE_URL . '/admin/produit-detail.php?id=' . $id);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirer un produit — Administration NO LABEL</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

</head>
<body>

<header class="admin-header">
    <h1><?= (int) $produit['actif'] === 1 ? 'Retirer du catalogue' : 'Produit retire' ?></h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/produits.php">Retour a la liste</a>
        <a href="<?= BASE_URL ?>/admin/produit-detail.php?id=<?= $id ?>">Fiche produit</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php if ($erreur): ?>
        <p class="message message--erreur"><?= e($erreur) ?></p>
    <?php endif; ?>

    <section class="fiche">
        <h2><?= e($produit['nom']) ?></h2>
        <p>
            <?= e($produit['categorie']) ?> —
            <?= number_format((float) $produit['prix'], 2, '.', ' ') ?> CHF —
            <?= (int) $produit['actif'] === 1 ? 'Actif' : 'Retire' ?>
        </p>
    </section>

    <?php if ((int) $produit['actif'] === 0): ?>

        <section>
            <p>Ce produit est deja retire du catalogue. Il reste visible dans
               <?= $nbLignesTotal ?> ligne(s) de commande, ce qui preserve l'historique.</p>

            <form method="post" action="">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="reactiver">
                <button type="submit">Remettre au catalogue</button>
            </form>
        </section>

    <?php else: ?>

        <section class="verification">
            <h2>Verifications avant retrait</h2>

            <?php if ($commandesEnCours): ?>
                <p class="message message--erreur">
                    Retrait impossible : ce produit figure dans
                    <?= count($commandesEnCours) ?> commande(s) non terminee(s).
                </p>

                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Commande</th><th>Date</th><th>Client</th>
                            <th>Taille</th><th>Qte</th><th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($commandesEnCours as $ligne): ?>
                        <tr>
                            <td><?= e($ligne['numero']) ?></td>
                            <td><?= date('d.m.Y', strtotime($ligne['date_commande'])) ?></td>
                            <td><?= e($ligne['prenom']) ?> <?= e($ligne['nom']) ?></td>
                            <td><?= e($ligne['taille']) ?></td>
                            <td><?= (int) $ligne['quantite'] ?></td>
                            <td><?= e(str_replace('_', ' ', $ligne['statut'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p>Livrez ou annulez ces commandes, puis revenez sur cette page.</p>

            <?php else: ?>
                <p>Aucune commande en cours ne contient ce produit.</p>
            <?php endif; ?>

            <?php if ($stockRestant): ?>
                <p class="message message--attention">
                    Attention : il reste <?= (int) $totalRestant ?> piece(s) en stock
                    (<?php
                        $details = [];
                        foreach ($stockRestant as $s) {
                            $details[] = e($s['code']) . ' : ' . (int) $s['quantite'];
                        }
                        echo implode(', ', $details);
                    ?>).
                    Le retrait remettra ces quantites a zero.
                </p>
            <?php endif; ?>

            <?php if ($nbLignesTotal > 0): ?>
                <p>Ce produit apparait dans <?= $nbLignesTotal ?> ligne(s) de commande au total.
                   Ces donnees seront conservees : c'est pourquoi la suppression est logique
                   et non definitive.</p>
            <?php endif; ?>
        </section>

        <?php if (!$commandesEnCours): ?>
            <section class="confirmation">
                <h2>Confirmer le retrait</h2>

                <form method="post" action="">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="retirer">

                    <p>
                        <label>
                            <input type="checkbox" name="confirmation" value="1">
                            Je confirme vouloir retirer « <?= e($produit['nom']) ?> » du catalogue.
                        </label>
                    </p>

                    <p class="actions">
                        <button type="submit">Retirer du catalogue</button>
                        <a href="<?= BASE_URL ?>/admin/produits.php">Annuler</a>
                    </p>
                </form>
            </section>
        <?php endif; ?>

    <?php endif; ?>

</main>

</body>
</html>