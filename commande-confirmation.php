<?php
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/config/db.php';

exigerConnexion();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    rediriger(BASE_URL . '/compte.php');
}

// --- La commande : verifie qu'elle appartient bien au client connecte ---
$stmt = $pdo->prepare(
    'SELECT co.*, u.email
     FROM commande co
     JOIN utilisateur u ON u.id = co.utilisateur_id
     WHERE co.id = :id AND co.utilisateur_id = :uid'
);
$stmt->execute([':id' => $id, ':uid' => $_SESSION['utilisateur_id']]);
$commande = $stmt->fetch();

if (!$commande) {
    messageFlash('erreur', 'Commande introuvable.');
    rediriger(BASE_URL . '/compte.php');
}

// --- Les lignes : classe d'association ligne_commande ---
$stmt = $pdo->prepare(
    'SELECT lc.quantite, lc.prix_unitaire,
            (lc.quantite * lc.prix_unitaire) AS sous_total,
            p.nom, p.image,
            t.code AS taille,
            c.nom AS categorie
     FROM ligne_commande lc
     JOIN produit   p ON p.id = lc.produit_id
     JOIN taille    t ON t.id = lc.taille_id
     JOIN categorie c ON c.id = p.categorie_id
     WHERE lc.commande_id = :id
     ORDER BY p.nom'
);
$stmt->execute([':id' => $id]);
$lignes = $stmt->fetchAll();

$titrePage = 'Commande ' . $commande['numero'];
require __DIR__ . '/includes/header.php';
?>

<section class="confirmation">

    <h1>Merci pour votre commande</h1>

    <p class="numero-commande">Commande <strong><?= e($commande['numero']) ?></strong></p>
    <p>Un recapitulatif a ete enregistre sur votre compte
       (<?= e($commande['email']) ?>).</p>

    <div class="etapes-suivantes">
        <h2>Prochaines etapes</h2>
        <ol>
            <li>Notre equipe verifie la disponibilite et confirme votre commande.</li>
            <?php if ($commande['mode_retrait'] === 'retrait'): ?>
                <li>Vous recevrez un message des que votre commande sera prete en boutique.</li>
                <li>Le paiement s'effectue au retrait, a la Rue de Chantepoulet, Geneve.</li>
            <?php else: ?>
                <li>Votre colis est prepare puis expedie a l'adresse indiquee.</li>
                <li>Le paiement s'effectue a la livraison.</li>
            <?php endif; ?>
        </ol>
    </div>

    <section class="details-commande">
        <h2>Detail de la commande</h2>

        <dl>
            <dt>Date</dt>
            <dd><?= date('d.m.Y a\ H:i', strtotime($commande['date_commande'])) ?></dd>

            <dt>Statut</dt>
            <dd><?= e(str_replace('_', ' ', $commande['statut'])) ?></dd>

            <dt>Mode</dt>
            <dd><?= $commande['mode_retrait'] === 'retrait' ? 'Retrait en boutique' : 'Livraison' ?></dd>

            <dt>Destinataire</dt>
            <dd><?= e($commande['prenom_livraison']) ?> <?= e($commande['nom_livraison']) ?></dd>

            <?php if ($commande['mode_retrait'] === 'livraison'): ?>
                <dt>Adresse</dt>
                <dd>
                    <?= e($commande['adresse']) ?><br>
                    <?= e($commande['code_postal']) ?> <?= e($commande['ville']) ?>
                </dd>
            <?php endif; ?>

            <dt>Telephone</dt>
            <dd><?= e($commande['telephone']) ?></dd>

            <?php if ($commande['commentaire']): ?>
                <dt>Remarque</dt>
                <dd><?= nl2br(e($commande['commentaire'])) ?></dd>
            <?php endif; ?>
        </dl>

        <table class="tableau">
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Taille</th>
                    <th>Qte</th>
                    <th>Prix unitaire</th>
                    <th>Sous-total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <tr>
                    <td>
                        <?= e($ligne['nom']) ?>
                        <small><?= e($ligne['categorie']) ?></small>
                    </td>
                    <td><?= e($ligne['taille']) ?></td>
                    <td><?= (int) $ligne['quantite'] ?></td>
                    <td><?= number_format((float) $ligne['prix_unitaire'], 2, '.', ' ') ?> CHF</td>
                    <td><?= number_format((float) $ligne['sous_total'], 2, '.', ' ') ?> CHF</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">Total a payer</th>
                    <th><?= number_format((float) $commande['total'], 2, '.', ' ') ?> CHF</th>
                </tr>
            </tfoot>
        </table>
    </section>

    <p class="actions">
        <a href="<?= BASE_URL ?>/compte.php">Mes commandes</a>
        <a href="<?= BASE_URL ?>/index.php" class="bouton">Continuer mes achats</a>
    </p>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>