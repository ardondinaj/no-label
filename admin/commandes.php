<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

$statutF   = $_GET['statut'] ?? '';
$recherche = trim($_GET['q'] ?? '');

$statuts = ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'];

// --- Requete sur 4 tables ---
$sql = "SELECT co.id, co.numero, co.date_commande, co.statut, co.mode_retrait,
               co.total, co.ville,
               u.nom, u.prenom, u.email,
               COUNT(lc.id)          AS nb_lignes,
               SUM(lc.quantite)      AS nb_articles
        FROM commande co
        JOIN utilisateur    u  ON u.id  = co.utilisateur_id
        LEFT JOIN ligne_commande lc ON lc.commande_id = co.id
        WHERE 1 = 1";

$params = [];

if ($statutF !== '' && in_array($statutF, $statuts, true)) {
    $sql .= ' AND co.statut = :statut';
    $params[':statut'] = $statutF;
}

if ($recherche !== '') {
    $sql .= ' AND (co.numero LIKE :r OR u.nom LIKE :r2 OR u.prenom LIKE :r3)';
    $params[':r']  = '%' . $recherche . '%';
    $params[':r2'] = '%' . $recherche . '%';
    $params[':r3'] = '%' . $recherche . '%';
}

$sql .= ' GROUP BY co.id, co.numero, co.date_commande, co.statut, co.mode_retrait,
                   co.total, co.ville, u.nom, u.prenom, u.email
          ORDER BY co.date_commande DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// --- Compteurs par statut ---
$compteurs = [];
foreach ($pdo->query('SELECT statut, COUNT(*) AS n FROM commande GROUP BY statut') as $ligne) {
    $compteurs[$ligne['statut']] = (int) $ligne['n'];
}

$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes — Administration NO LABEL</title>
</head>
<body>

<header class="admin-header">
    <h1>Commandes</h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/index.php">Tableau de bord</a>
        <a href="<?= BASE_URL ?>/admin/produits.php">Produits</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php foreach ($flash as $message): ?>
        <p class="message message--<?= e($message['type']) ?>"><?= e($message['texte']) ?></p>
    <?php endforeach; ?>

    <nav class="onglets-statut">
        <a href="<?= BASE_URL ?>/admin/commandes.php"
           class="<?= $statutF === '' ? 'actif' : '' ?>">Toutes</a>
        <?php foreach ($statuts as $statut): ?>
            <a href="<?= BASE_URL ?>/admin/commandes.php?statut=<?= e($statut) ?>"
               class="<?= $statutF === $statut ? 'actif' : '' ?>">
                <?= e(str_replace('_', ' ', $statut)) ?>
                (<?= $compteurs[$statut] ?? 0 ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="" class="filtres">
        <?php if ($statutF !== ''): ?>
            <input type="hidden" name="statut" value="<?= e($statutF) ?>">
        <?php endif; ?>
        <label for="q">Rechercher</label>
        <input type="text" id="q" name="q" value="<?= e($recherche) ?>"
               placeholder="Numero ou nom du client">
        <button type="submit">Filtrer</button>
        <a href="<?= BASE_URL ?>/admin/commandes.php">Reinitialiser</a>
    </form>

    <p class="resultat"><?= count($commandes) ?> commande(s)</p>

    <?php if (!$commandes): ?>
        <p>Aucune commande ne correspond a ces criteres.</p>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Numero</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Articles</th>
                    <th>Total</th>
                    <th>Mode</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($commandes as $commande): ?>
                <tr class="statut-<?= e($commande['statut']) ?>">
                    <td>
                        <a href="<?= BASE_URL ?>/admin/commande-detail.php?id=<?= (int) $commande['id'] ?>">
                            <?= e($commande['numero']) ?>
                        </a>
                    </td>
                    <td><?= date('d.m.Y H:i', strtotime($commande['date_commande'])) ?></td>
                    <td>
                        <?= e($commande['prenom']) ?> <?= e($commande['nom']) ?>
                        <small><?= e($commande['email']) ?></small>
                    </td>
                    <td><?= (int) $commande['nb_articles'] ?></td>
                    <td><?= number_format((float) $commande['total'], 2, '.', ' ') ?> CHF</td>
                    <td>
                        <?= $commande['mode_retrait'] === 'retrait'
                            ? 'Retrait'
                            : 'Livraison' . ($commande['ville'] ? ' — ' . e($commande['ville']) : '') ?>
                    </td>
                    <td><?= e(str_replace('_', ' ', $commande['statut'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/commande-detail.php?id=<?= (int) $commande['id'] ?>">
                            Ouvrir
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</main>

</body>
</html>