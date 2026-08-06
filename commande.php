<?php
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/config/db.php';

exigerConnexion();   // il faut un compte pour commander

$panier = $_SESSION['panier'] ?? [];

if (!$panier) {
    messageFlash('erreur', 'Votre panier est vide.');
    rediriger(BASE_URL . '/panier.php');
}

// --- Relecture du panier depuis la base ---
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
               p.nom, p.prix, p.actif, t.code AS taille
        FROM stock s
        JOIN produit p ON p.id = s.produit_id
        JOIN taille  t ON t.id = s.taille_id
        WHERE ' . implode(' OR ', $conditions);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$articles = [];
foreach ($stmt->fetchAll() as $article) {
    $articles[$article['produit_id'] . '-' . $article['taille_id']] = $article;
}

$lignes     = [];
$total      = 0.0;
$problemes  = [];

foreach ($panier as $cle => $ligne) {
    if (!isset($articles[$cle]) || (int) $articles[$cle]['actif'] !== 1) {
        $problemes[] = 'Un article de votre panier n\'est plus disponible.';
        unset($_SESSION['panier'][$cle]);
        continue;
    }

    $article  = $articles[$cle];
    $quantite = (int) $ligne['quantite'];

    if ($quantite > (int) $article['stock']) {
        $problemes[] = $article['nom'] . ' (taille ' . $article['taille'] . ') : '
                     . 'seulement ' . (int) $article['stock'] . ' piece(s) disponible(s).';
        continue;
    }

    $sousTotal = $quantite * (float) $article['prix'];
    $total    += $sousTotal;

    $lignes[$cle] = $article + ['quantite' => $quantite, 'sous_total' => $sousTotal];
}

// --- Informations du client connecte ---
$stmt = $pdo->prepare(
    'SELECT nom, prenom, telephone FROM utilisateur WHERE id = :id'
);
$stmt->execute([':id' => $_SESSION['utilisateur_id']]);
$client = $stmt->fetch();

$erreurs = [];
$valeurs = [
    'mode_retrait' => 'livraison',
    'nom'          => $client['nom'],
    'prenom'       => $client['prenom'],
    'adresse'      => '',
    'code_postal'  => '',
    'ville'        => '',
    'telephone'    => $client['telephone'] ?? '',
    'commentaire'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$problemes) {

    foreach ($valeurs as $champ => $defaut) {
        $valeurs[$champ] = trim($_POST[$champ] ?? '');
    }

    // --- Validation ---
    if (!in_array($valeurs['mode_retrait'], ['livraison', 'retrait'], true)) {
        $erreurs['mode_retrait'] = 'Mode de retrait invalide.';
    }

    if ($valeurs['nom'] === '') {
        $erreurs['nom'] = 'Le nom est obligatoire.';
    } elseif (mb_strlen($valeurs['nom']) > 80) {
        $erreurs['nom'] = 'Le nom est trop long.';
    }

    if ($valeurs['prenom'] === '') {
        $erreurs['prenom'] = 'Le prenom est obligatoire.';
    } elseif (mb_strlen($valeurs['prenom']) > 80) {
        $erreurs['prenom'] = 'Le prenom est trop long.';
    }

    if ($valeurs['telephone'] === '') {
        $erreurs['telephone'] = 'Le telephone est obligatoire.';
    } elseif (!preg_match('/^[\d\s\+\-\.\/\(\)]{8,30}$/', $valeurs['telephone'])) {
        $erreurs['telephone'] = 'Numero de telephone invalide.';
    }

    if ($valeurs['mode_retrait'] === 'livraison') {
        if ($valeurs['adresse'] === '') {
            $erreurs['adresse'] = 'L\'adresse est obligatoire pour une livraison.';
        } elseif (mb_strlen($valeurs['adresse']) > 255) {
            $erreurs['adresse'] = 'L\'adresse est trop longue.';
        }

        if ($valeurs['code_postal'] === '') {
            $erreurs['code_postal'] = 'Le code postal est obligatoire.';
        } elseif (!preg_match('/^\d{4,10}$/', $valeurs['code_postal'])) {
            $erreurs['code_postal'] = 'Code postal invalide (4 a 10 chiffres).';
        }

        if ($valeurs['ville'] === '') {
            $erreurs['ville'] = 'La ville est obligatoire.';
        } elseif (mb_strlen($valeurs['ville']) > 100) {
            $erreurs['ville'] = 'Le nom de ville est trop long.';
        }
    }

    if (mb_strlen($valeurs['commentaire']) > 500) {
        $erreurs['commentaire'] = 'Le commentaire ne peut pas depasser 500 caracteres.';
    }

    // --- Enregistrement en transaction ---
    if (!$erreurs) {
        $pdo->beginTransaction();

        try {
            // Numero de commande unique
            $annee = date('Y');
            $stmt  = $pdo->prepare(
                "SELECT COUNT(*) FROM commande WHERE numero LIKE :prefixe"
            );
            $stmt->execute([':prefixe' => 'NL-' . $annee . '-%']);
            $numero = 'NL-' . $annee . '-' . str_pad((string) ($stmt->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);

            // 1. La commande
            $stmt = $pdo->prepare(
                "INSERT INTO commande
                    (utilisateur_id, numero, statut, mode_retrait,
                     nom_livraison, prenom_livraison, adresse, code_postal, ville,
                     telephone, commentaire, total)
                 VALUES
                    (:uid, :numero, 'en_attente_paiement', :mode,
                     :nom, :prenom, :adresse, :cp, :ville,
                     :tel, :commentaire, :total)"
            );
            $stmt->execute([
                ':uid'         => $_SESSION['utilisateur_id'],
                ':numero'      => $numero,
                ':mode'        => $valeurs['mode_retrait'],
                ':nom'         => $valeurs['nom'],
                ':prenom'      => $valeurs['prenom'],
                ':adresse'     => $valeurs['mode_retrait'] === 'livraison' ? $valeurs['adresse'] : null,
                ':cp'          => $valeurs['mode_retrait'] === 'livraison' ? $valeurs['code_postal'] : null,
                ':ville'       => $valeurs['mode_retrait'] === 'livraison' ? $valeurs['ville'] : null,
                ':tel'         => $valeurs['telephone'],
                ':commentaire' => $valeurs['commentaire'] !== '' ? $valeurs['commentaire'] : null,
                ':total'       => $total,
            ]);

            $commandeId = (int) $pdo->lastInsertId();

            // 2. Les lignes + decrement du stock
            $stmtLigne = $pdo->prepare(
                'INSERT INTO ligne_commande (commande_id, produit_id, taille_id, quantite, prix_unitaire)
                 VALUES (:cid, :pid, :tid, :qte, :prix)'
            );
            $stmtStock = $pdo->prepare(
                'UPDATE stock SET quantite = quantite - :qte
                 WHERE produit_id = :pid AND taille_id = :tid AND quantite >= :qte2'
            );

            foreach ($lignes as $ligne) {
                $stmtLigne->execute([
                    ':cid'  => $commandeId,
                    ':pid'  => $ligne['produit_id'],
                    ':tid'  => $ligne['taille_id'],
                    ':qte'  => $ligne['quantite'],
                    ':prix' => $ligne['prix'],
                ]);

                $stmtStock->execute([
                    ':qte'  => $ligne['quantite'],
                    ':pid'  => $ligne['produit_id'],
                    ':tid'  => $ligne['taille_id'],
                    ':qte2' => $ligne['quantite'],
                ]);

                // Si aucune ligne mise a jour : le stock a change entre-temps
                if ($stmtStock->rowCount() === 0) {
                    throw new RuntimeException(
                        'Le stock de ' . $ligne['nom'] . ' (taille ' . $ligne['taille']
                        . ') est insuffisant.'
                    );
                }
            }

            $pdo->commit();

            unset($_SESSION['panier']);

            messageFlash('succes', 'Votre commande ' . $numero . ' a bien ete enregistree.');
            rediriger(BASE_URL . '/commande-confirmation.php?id=' . $commandeId);

        } catch (Exception $ex) {
            $pdo->rollBack();
            $erreurs['general'] = DEBUG
                ? $ex->getMessage()
                : 'La commande n\'a pas pu etre enregistree. Veuillez reessayer.';
        }
    }
}

$titrePage = 'Commande';
require __DIR__ . '/includes/header.php';
?>

<h1>Finaliser la commande</h1>

<?php if ($problemes): ?>
    <div class="message message--erreur">
        <p>Votre panier a ete modifie :</p>
        <ul>
            <?php foreach ($problemes as $probleme): ?>
                <li><?= e($probleme) ?></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="<?= BASE_URL ?>/panier.php">Revoir mon panier</a></p>
    </div>
<?php endif; ?>

<?php if (isset($erreurs['general'])): ?>
    <p class="message message--erreur"><?= e($erreurs['general']) ?></p>
<?php endif; ?>

<?php if ($erreurs && !isset($erreurs['general'])): ?>
    <div class="message message--erreur">
        <p>Le formulaire contient <?= count($erreurs) ?> erreur(s) :</p>
        <ul>
            <?php foreach ($erreurs as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="recapitulatif">
    <h2>Recapitulatif</h2>
    <table class="tableau">
        <thead>
            <tr><th>Article</th><th>Taille</th><th>Qte</th><th>Prix</th><th>Sous-total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $ligne): ?>
            <tr>
                <td><?= e($ligne['nom']) ?></td>
                <td><?= e($ligne['taille']) ?></td>
                <td><?= (int) $ligne['quantite'] ?></td>
                <td><?= number_format((float) $ligne['prix'], 2, '.', ' ') ?></td>
                <td><?= number_format($ligne['sous_total'], 2, '.', ' ') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4">Total</th><th><?= number_format($total, 2, '.', ' ') ?> CHF</th></tr>
        </tfoot>
    </table>
</section>

<?php if ($lignes && !$problemes): ?>
<form method="post" action="" class="form-commande" novalidate>

    <fieldset>
        <legend>Mode de retrait</legend>
        <label>
            <input type="radio" name="mode_retrait" value="livraison"
                   <?= $valeurs['mode_retrait'] === 'livraison' ? 'checked' : '' ?>>
            Livraison a domicile
        </label>
        <label>
            <input type="radio" name="mode_retrait" value="retrait"
                   <?= $valeurs['mode_retrait'] === 'retrait' ? 'checked' : '' ?>>
            Retrait en boutique (Geneve)
        </label>
    </fieldset>

    <fieldset>
        <legend>Coordonnees</legend>

        <p class="champ<?= isset($erreurs['prenom']) ? ' champ--erreur' : '' ?>">
            <label for="prenom">Prenom *</label>
            <input type="text" id="prenom" name="prenom" value="<?= e($valeurs['prenom']) ?>">
            <?php if (isset($erreurs['prenom'])): ?>
                <span class="erreur"><?= e($erreurs['prenom']) ?></span>
            <?php endif; ?>
        </p>

        <p class="champ<?= isset($erreurs['nom']) ? ' champ--erreur' : '' ?>">
            <label for="nom">Nom *</label>
            <input type="text" id="nom" name="nom" value="<?= e($valeurs['nom']) ?>">
            <?php if (isset($erreurs['nom'])): ?>
                <span class="erreur"><?= e($erreurs['nom']) ?></span>
            <?php endif; ?>
        </p>

        <p class="champ<?= isset($erreurs['telephone']) ? ' champ--erreur' : '' ?>">
            <label for="telephone">Telephone *</label>
            <input type="tel" id="telephone" name="telephone" value="<?= e($valeurs['telephone']) ?>">
            <?php if (isset($erreurs['telephone'])): ?>
                <span class="erreur"><?= e($erreurs['telephone']) ?></span>
            <?php endif; ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Adresse de livraison</legend>
        <p class="aide">A remplir uniquement en cas de livraison.</p>

        <p class="champ<?= isset($erreurs['adresse']) ? ' champ--erreur' : '' ?>">
            <label for="adresse">Adresse</label>
            <input type="text" id="adresse" name="adresse" value="<?= e($valeurs['adresse']) ?>">
            <?php if (isset($erreurs['adresse'])): ?>
                <span class="erreur"><?= e($erreurs['adresse']) ?></span>
            <?php endif; ?>
        </p>

        <p class="champ<?= isset($erreurs['code_postal']) ? ' champ--erreur' : '' ?>">
            <label for="code_postal">Code postal</label>
            <input type="text" id="code_postal" name="code_postal" value="<?= e($valeurs['code_postal']) ?>">
            <?php if (isset($erreurs['code_postal'])): ?>
                <span class="erreur"><?= e($erreurs['code_postal']) ?></span>
            <?php endif; ?>
        </p>

        <p class="champ<?= isset($erreurs['ville']) ? ' champ--erreur' : '' ?>">
            <label for="ville">Ville</label>
            <input type="text" id="ville" name="ville" value="<?= e($valeurs['ville']) ?>">
            <?php if (isset($erreurs['ville'])): ?>
                <span class="erreur"><?= e($erreurs['ville']) ?></span>
            <?php endif; ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Remarque</legend>
        <p class="champ<?= isset($erreurs['commentaire']) ? ' champ--erreur' : '' ?>">
            <label for="commentaire">Commentaire (facultatif)</label>
            <textarea id="commentaire" name="commentaire" rows="3"><?= e($valeurs['commentaire']) ?></textarea>
            <?php if (isset($erreurs['commentaire'])): ?>
                <span class="erreur"><?= e($erreurs['commentaire']) ?></span>
            <?php endif; ?>
        </p>
    </fieldset>

    <p class="paiement-info">
        Le paiement s'effectue en boutique ou a la livraison.
        Votre commande sera confirmee par l'equipe NO LABEL.
    </p>

    <p class="actions">
        <button type="submit" class="bouton bouton--principal">Confirmer la commande</button>
        <a href="<?= BASE_URL ?>/panier.php">Retour au panier</a>
    </p>
</form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>