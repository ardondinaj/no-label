<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    messageFlash('erreur', 'Commande introuvable.');
    rediriger(BASE_URL . '/admin/commandes.php');
}

$erreur = null;

// --- Transitions autorisees ---
$transitions = [
    'en_attente_paiement' => ['payee', 'annulee'],
    'payee'               => ['expediee', 'annulee'],
    'expediee'            => ['livree'],
    'livree'              => [],
    'annulee'             => [],
];

// --- Traitement du changement de statut ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nouveauStatut = $_POST['statut'] ?? '';

    $stmt = $pdo->prepare('SELECT statut FROM commande WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $statutActuel = $stmt->fetchColumn();

    if ($statutActuel === false) {
        messageFlash('erreur', 'Cette commande n\'existe pas.');
        rediriger(BASE_URL . '/admin/commandes.php');
    }

    if (!in_array($nouveauStatut, $transitions[$statutActuel] ?? [], true)) {
        $erreur = 'Transition impossible depuis le statut « '
                . str_replace('_', ' ', $statutActuel) . ' ».';
    } else {
        $pdo->beginTransaction();

        try {
            // Annulation : on restitue le stock
            if ($nouveauStatut === 'annulee') {
                $stmt = $pdo->prepare(
                    'SELECT produit_id, taille_id, quantite
                     FROM ligne_commande WHERE commande_id = :id'
                );
                $stmt->execute([':id' => $id]);

                $stmtStock = $pdo->prepare(
                    'UPDATE stock SET quantite = quantite + :qte
                     WHERE produit_id = :pid AND taille_id = :tid'
                );

                foreach ($stmt->fetchAll() as $ligne) {
                    $stmtStock->execute([
                        ':qte' => $ligne['quantite'],
                        ':pid' => $ligne['produit_id'],
                        ':tid' => $ligne['taille_id'],
                    ]);
                }
            }

            $stmt = $pdo->prepare('UPDATE commande SET statut = :statut WHERE id = :id');
            $stmt->execute([':statut' => $nouveauStatut, ':id' => $id]);

            $pdo->commit();

            $libelles = [
                'payee'    => 'Paiement enregistre.',
                'expediee' => 'Commande marquee comme expediee.',
                'livree'   => 'Commande marquee comme livree.',
                'annulee'  => 'Commande annulee, le stock a ete restitue.',
            ];

            messageFlash('succes', $libelles[$nouveauStatut] ?? 'Statut mis a jour.');
            rediriger(BASE_URL . '/admin/commande-detail.php?id=' . $id);

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erreur = DEBUG ? $e->getMessage() : 'La mise a jour a echoue.';
        }
    }
}

// --- Lecture de la commande ---
$stmt = $pdo->prepare(
    'SELECT co.*, u.email, u.telephone AS tel_compte, u.date_inscription
     FROM commande co
     JOIN utilisateur u ON u.id = co.utilisateur_id
     WHERE co.id = :id'
);
$stmt->execute([':id' => $id]);
$commande = $stmt->fetch();

if (!$commande) {
    messageFlash('erreur', 'Cette commande n\'existe pas.');
    rediriger(BASE_URL . '/admin/commandes.php');
}

// --- Les lignes ---
$stmt = $pdo->prepare(
    'SELECT lc.quantite, lc.prix_unitaire,
            (lc.quantite * lc.prix_unitaire) AS sous_total,
            p.id AS produit_id, p.nom, p.actif,
            t.code AS taille,
            c.nom  AS categorie,
            s.quantite AS stock_actuel
     FROM ligne_commande lc
     JOIN produit   p ON p.id = lc.produit_id
     JOIN taille    t ON t.id = lc.taille_id
     JOIN categorie c ON c.id = p.categorie_id
     LEFT JOIN stock s ON s.produit_id = lc.produit_id AND s.taille_id = lc.taille_id
     WHERE lc.commande_id = :id
     ORDER BY p.nom'
);
$stmt->execute([':id' => $id]);
$lignes = $stmt->fetchAll();

// --- Historique du client ---
$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS montant
     FROM commande WHERE utilisateur_id = :uid AND statut <> :annulee'
);
$stmt->execute([':uid' => $commande['utilisateur_id'], ':annulee' => 'annulee']);
$historique = $stmt->fetch();

$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande <?= e($commande['numero']) ?> — Administration</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

</head>
<body>

<header class="admin-header">
    <h1>Commande <?= e($commande['numero']) ?></h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/commandes.php">Retour aux commandes</a>
        <a href="<?= BASE_URL ?>/admin/index.php">Tableau de bord</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php foreach ($flash as $message): ?>
        <p class="message message--<?= e($message['type']) ?>"><?= e($message['texte']) ?></p>
    <?php endforeach; ?>

    <?php if ($erreur): ?>
        <p class="message message--erreur"><?= e($erreur) ?></p>
    <?php endif; ?>

    <section class="statut-actuel">
        <h2>Statut : <?= e(str_replace('_', ' ', $commande['statut'])) ?></h2>

        <?php $suivants = $transitions[$commande['statut']] ?? []; ?>

        <?php if (!$suivants): ?>
            <p>Cette commande est terminee. Aucune action possible.</p>
        <?php else: ?>
            <form method="post" action="" class="form-statut">
                <input type="hidden" name="id" value="<?= $id ?>">

                <?php foreach ($suivants as $statut): ?>
                    <button type="submit" name="statut" value="<?= e($statut) ?>">
                        <?php
                        $actions = [
                            'payee'    => 'Encaisser le paiement',
                            'expediee' => 'Marquer comme expediee',
                            'livree'   => 'Marquer comme livree',
                            'annulee'  => 'Annuler la commande',
                        ];
                        echo e($actions[$statut] ?? $statut);
                        ?>
                    </button>
                <?php endforeach; ?>
            </form>

            <?php if (in_array('annulee', $suivants, true)): ?>
                <p class="aide">L'annulation restitue automatiquement les quantites au stock.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="infos-client">
        <h2>Client</h2>
        <dl>
            <dt>Nom</dt>
            <dd><?= e($commande['prenom_livraison']) ?> <?= e($commande['nom_livraison']) ?></dd>
            <dt>Email</dt><dd><?= e($commande['email']) ?></dd>
            <dt>Telephone</dt><dd><?= e($commande['telephone']) ?></dd>
            <dt>Client depuis</dt>
            <dd><?= date('d.m.Y', strtotime($commande['date_inscription'])) ?></dd>
            <dt>Historique</dt>
            <dd><?= (int) $historique['nb'] ?> commande(s),
                <?= number_format((float) $historique['montant'], 2, '.', ' ') ?> CHF au total</dd>
        </dl>
    </section>

    <section class="infos-livraison">
        <h2><?= $commande['mode_retrait'] === 'retrait' ? 'Retrait en boutique' : 'Livraison' ?></h2>
        <?php if ($commande['mode_retrait'] === 'livraison'): ?>
            <p>
                <?= e($commande['adresse']) ?><br>
                <?= e($commande['code_postal']) ?> <?= e($commande['ville']) ?>
            </p>
        <?php else: ?>
            <p>Le client retire sa commande en boutique.</p>
        <?php endif; ?>

        <?php if ($commande['commentaire']): ?>
            <h3>Remarque du client</h3>
            <p><?= nl2br(e($commande['commentaire'])) ?></p>
        <?php endif; ?>
    </section>

    <section class="articles">
        <h2>Articles</h2>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Article</th><th>Taille</th><th>Qte</th>
                    <th>Prix unitaire</th><th>Sous-total</th><th>Stock restant</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/produit-detail.php?id=<?= (int) $ligne['produit_id'] ?>">
                            <?= e($ligne['nom']) ?>
                        </a>
                        <small><?= e($ligne['categorie']) ?></small>
                        <?php if ((int) $ligne['actif'] === 0): ?>
                            <small>produit retire du catalogue</small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($ligne['taille']) ?></td>
                    <td><?= (int) $ligne['quantite'] ?></td>
                    <td><?= number_format((float) $ligne['prix_unitaire'], 2, '.', ' ') ?></td>
                    <td><?= number_format((float) $ligne['sous_total'], 2, '.', ' ') ?></td>
                    <td><?= $ligne['stock_actuel'] === null ? '—' : (int) $ligne['stock_actuel'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">Total</th>
                    <th><?= number_format((float) $commande['total'], 2, '.', ' ') ?> CHF</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </section>

</main>

</body>
</html>