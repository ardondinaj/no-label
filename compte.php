<?php
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/config/db.php';

exigerConnexion();

$uid = $_SESSION['utilisateur_id'];

// --- Le compte ---
$stmt = $pdo->prepare(
    'SELECT nom, prenom, email, telephone, date_inscription
     FROM utilisateur WHERE id = :id'
);
$stmt->execute([':id' => $uid]);
$utilisateur = $stmt->fetch();

// --- Ses commandes, avec le nombre d'articles ---
$stmt = $pdo->prepare(
    'SELECT co.id, co.numero, co.date_commande, co.statut,
            co.mode_retrait, co.total,
            SUM(lc.quantite) AS nb_articles
     FROM commande co
     LEFT JOIN ligne_commande lc ON lc.commande_id = co.id
     WHERE co.utilisateur_id = :uid
     GROUP BY co.id, co.numero, co.date_commande, co.statut, co.mode_retrait, co.total
     ORDER BY co.date_commande DESC'
);
$stmt->execute([':uid' => $uid]);
$commandes = $stmt->fetchAll();

// --- Totaux ---
$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS montant
     FROM commande WHERE utilisateur_id = :uid AND statut <> :annulee'
);
$stmt->execute([':uid' => $uid, ':annulee' => 'annulee']);
$totaux = $stmt->fetch();

$titrePage = 'Mon compte';
require __DIR__ . '/includes/header.php';
?>

<h1>Mon compte</h1>

<section class="infos-compte">
    <h2><?= e($utilisateur['prenom']) ?> <?= e($utilisateur['nom']) ?></h2>
    <dl>
        <dt>Email</dt><dd><?= e($utilisateur['email']) ?></dd>
        <dt>Telephone</dt>
        <dd><?= $utilisateur['telephone'] ? e($utilisateur['telephone']) : 'Non renseigne' ?></dd>
        <dt>Client depuis</dt>
        <dd><?= date('d.m.Y', strtotime($utilisateur['date_inscription'])) ?></dd>
        <dt>Commandes</dt>
        <dd><?= (int) $totaux['nb'] ?> pour
            <?= number_format((float) $totaux['montant'], 2, '.', ' ') ?> CHF</dd>
    </dl>
</section>

<section class="mes-commandes">
    <h2>Mes commandes</h2>

    <?php if (!$commandes): ?>
        <p class="vide">Vous n'avez pas encore passe de commande.</p>
        <p><a href="<?= BASE_URL ?>/index.php" class="bouton">Decouvrir la boutique</a></p>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Numero</th><th>Date</th><th>Articles</th>
                    <th>Total</th><th>Mode</th><th>Statut</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($commandes as $commande): ?>
                <tr>
                    <td><?= e($commande['numero']) ?></td>
                    <td><?= date('d.m.Y', strtotime($commande['date_commande'])) ?></td>
                    <td><?= (int) $commande['nb_articles'] ?></td>
                    <td><?= number_format((float) $commande['total'], 2, '.', ' ') ?> CHF</td>
                    <td><?= $commande['mode_retrait'] === 'retrait' ? 'Retrait' : 'Livraison' ?></td>
                    <td><?= e(str_replace('_', ' ', $commande['statut'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/commande-confirmation.php?id=<?= (int) $commande['id'] ?>">
                            Detail
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>