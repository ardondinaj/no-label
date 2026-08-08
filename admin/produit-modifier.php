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

$categories = $pdo->query('SELECT id, nom FROM categorie ORDER BY nom')->fetchAll();
$tailles    = $pdo->query('SELECT id, code FROM taille ORDER BY ordre')->fetchAll();

// --- Lecture avec jointure : le produit et sa categorie ---
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

// --- Stocks actuels, indexes par taille ---
$stmt = $pdo->prepare('SELECT taille_id, quantite FROM stock WHERE produit_id = :id');
$stmt->execute([':id' => $id]);
$stocksActuels = [];
foreach ($stmt->fetchAll() as $ligne) {
    $stocksActuels[(int) $ligne['taille_id']] = (int) $ligne['quantite'];
}

// --- JOINTURE : tailles referencees dans une commande, tous statuts confondus ---
// Une taille ayant un historique ne peut pas etre supprimee : la ligne de commande
// pointerait vers un couple produit/taille inexistant.
$stmt = $pdo->prepare(
    'SELECT lc.taille_id, COUNT(*) AS nb_lignes
     FROM ligne_commande lc
     JOIN commande co ON co.id = lc.commande_id
     WHERE lc.produit_id = :id
     GROUP BY lc.taille_id'
);
$stmt->execute([':id' => $id]);
$engagees = [];
foreach ($stmt->fetchAll() as $ligne) {
    $engagees[(int) $ligne['taille_id']] = (int) $ligne['nb_lignes'];
}

$erreurs = [];

// Valeurs affichees : celles de la base au premier chargement
$valeurs = [
    'nom'          => $produit['nom'],
    'categorie_id' => $produit['categorie_id'],
    'description'  => $produit['description'] ?? '',
    'prix'         => $produit['prix'],
    'image'        => $produit['image'] ?? '',
    'stocks'       => $stocksActuels,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $valeurs['nom']          = trim($_POST['nom'] ?? '');
    $valeurs['categorie_id'] = trim($_POST['categorie_id'] ?? '');
    $valeurs['description']  = trim($_POST['description'] ?? '');
    $valeurs['prix']         = trim($_POST['prix'] ?? '');
    $valeurs['image']        = trim($_POST['image'] ?? '');
    $valeurs['stocks']       = $_POST['stock'] ?? [];

    // --- Validation : nom ---
    if ($valeurs['nom'] === '') {
        $erreurs['nom'] = 'Le nom du produit est obligatoire.';
    } elseif (mb_strlen($valeurs['nom']) < 3) {
        $erreurs['nom'] = 'Le nom doit contenir au moins 3 caracteres.';
    } elseif (mb_strlen($valeurs['nom']) > 150) {
        $erreurs['nom'] = 'Le nom ne peut pas depasser 150 caracteres.';
    }

    // --- Validation : categorie ---
    if ($valeurs['categorie_id'] === '') {
        $erreurs['categorie_id'] = 'Veuillez choisir une categorie.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorie WHERE id = :id');
        $stmt->execute([':id' => $valeurs['categorie_id']]);
        if ((int) $stmt->fetchColumn() === 0) {
            $erreurs['categorie_id'] = 'Cette categorie n\'existe pas.';
        }
    }

    // --- Validation : prix ---
    if ($valeurs['prix'] === '') {
        $erreurs['prix'] = 'Le prix est obligatoire.';
    } elseif (!is_numeric($valeurs['prix'])) {
        $erreurs['prix'] = 'Le prix doit etre un nombre.';
    } elseif ((float) $valeurs['prix'] <= 0) {
        $erreurs['prix'] = 'Le prix doit etre superieur a zero.';
    } elseif ((float) $valeurs['prix'] > 99999.99) {
        $erreurs['prix'] = 'Le prix est trop eleve.';
    }

    // --- Validation : description ---
    if (mb_strlen($valeurs['description']) > 2000) {
        $erreurs['description'] = 'La description ne peut pas depasser 2000 caracteres.';
    }

    // --- Validation : image ---
    if ($valeurs['image'] !== '' && !preg_match('/^[\w\-]+\.(jpg|jpeg|png|webp)$/i', $valeurs['image'])) {
        $erreurs['image'] = 'Nom de fichier invalide (attendu : nom-image.jpg).';
    }

    // --- Validation : stocks ---
    $stocksValides = [];

    foreach ($tailles as $taille) {
        $tid    = (int) $taille['id'];
        $saisie = trim((string) ($valeurs['stocks'][$tid] ?? ''));

        if ($saisie === '') {
            // Taille retiree : interdit si elle apparait dans une commande
            if (isset($engagees[$tid])) {
                $erreurs['stock'] = 'La taille ' . $taille['code']
                    . ' apparait dans ' . $engagees[$tid] . ' commande(s) et ne peut pas etre retiree. '
                    . 'Mettez sa quantite a 0 pour la rendre indisponible a la vente.';
                break;
            }
            continue;
        }

        if (!ctype_digit($saisie)) {
            $erreurs['stock'] = 'Les quantites doivent etre des nombres entiers positifs.';
            break;
        }

        $quantite = (int) $saisie;

        if ($quantite > 9999) {
            $erreurs['stock'] = 'Une quantite ne peut pas depasser 9999.';
            break;
        }

        $stocksValides[$tid] = $quantite;
    }

    if (!isset($erreurs['stock']) && !$stocksValides) {
        $erreurs['stock'] = 'Renseignez au moins une taille avec sa quantite.';
    }

    // --- Enregistrement ---
    if (!$erreurs) {
        $pdo->beginTransaction();

        try {
            // 1. Mise a jour du produit
            $stmt = $pdo->prepare(
                'UPDATE produit
                 SET categorie_id = :cat, nom = :nom, description = :desc,
                     prix = :prix, image = :image
                 WHERE id = :id'
            );
            $stmt->execute([
                ':cat'   => $valeurs['categorie_id'],
                ':nom'   => $valeurs['nom'],
                ':desc'  => $valeurs['description'] !== '' ? $valeurs['description'] : null,
                ':prix'  => $valeurs['prix'],
                ':image' => $valeurs['image'] !== '' ? $valeurs['image'] : null,
                ':id'    => $id,
            ]);

            // 2. Stocks : trois cas a traiter
            $stmtUpdate = $pdo->prepare(
                'UPDATE stock SET quantite = :qte WHERE produit_id = :pid AND taille_id = :tid'
            );
            $stmtInsert = $pdo->prepare(
                'INSERT INTO stock (produit_id, taille_id, quantite) VALUES (:pid, :tid, :qte)'
            );
            $stmtDelete = $pdo->prepare(
                'DELETE FROM stock WHERE produit_id = :pid AND taille_id = :tid'
            );

            foreach ($stocksValides as $tid => $quantite) {
                if (isset($stocksActuels[$tid])) {
                    $stmtUpdate->execute([':qte' => $quantite, ':pid' => $id, ':tid' => $tid]);
                } else {
                    $stmtInsert->execute([':pid' => $id, ':tid' => $tid, ':qte' => $quantite]);
                }
            }

            // Tailles presentes en base mais absentes du formulaire : suppression
            foreach ($stocksActuels as $tid => $ancienne) {
                if (!isset($stocksValides[$tid])) {
                    $stmtDelete->execute([':pid' => $id, ':tid' => $tid]);
                }
            }

            $pdo->commit();

            messageFlash('succes',
                'Le produit « ' . $valeurs['nom'] . ' » a ete mis a jour ('
                . count($stocksValides) . ' taille(s), '
                . array_sum($stocksValides) . ' pieces).');

            rediriger(BASE_URL . '/admin/produit-detail.php?id=' . $id);

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erreurs['general'] = DEBUG
                ? 'Erreur : ' . $e->getMessage()
                : 'La modification a echoue.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier <?= e($produit['nom']) ?> — Administration NO LABEL</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

</head>
<body>

<header class="admin-header">
    <h1>Modifier un produit</h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/produits.php">Retour a la liste</a>
        <a href="<?= BASE_URL ?>/admin/produit-detail.php?id=<?= $id ?>">Fiche produit</a>
        <a href="<?= BASE_URL ?>/logout.php">Deconnexion</a>
    </nav>
</header>

<main>

    <?php if (isset($erreurs['general'])): ?>
        <p class="message message--erreur"><?= e($erreurs['general']) ?></p>
    <?php endif; ?>

    <?php if ($erreurs): ?>
        <div class="message message--erreur">
            <p>Le formulaire contient <?= count($erreurs) ?> erreur(s) :</p>
            <ul>
                <?php foreach ($erreurs as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ((int) $produit['actif'] === 0): ?>
        <p class="message message--attention">
            Ce produit est actuellement retire du catalogue.
        </p>
    <?php endif; ?>

    <form method="post" action="" class="form-produit" novalidate>
        <input type="hidden" name="id" value="<?= $id ?>">

        <fieldset>
            <legend>Informations</legend>

            <p class="champ<?= isset($erreurs['nom']) ? ' champ--erreur' : '' ?>">
                <label for="nom">Nom du produit *</label>
                <input type="text" id="nom" name="nom"
                       value="<?= e($valeurs['nom']) ?>" maxlength="150">
                <?php if (isset($erreurs['nom'])): ?>
                    <span class="erreur"><?= e($erreurs['nom']) ?></span>
                <?php endif; ?>
            </p>

            <p class="champ<?= isset($erreurs['categorie_id']) ? ' champ--erreur' : '' ?>">
                <label for="categorie_id">Categorie *</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="">— Choisir —</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?= (int) $categorie['id'] ?>"
                            <?= (string) $valeurs['categorie_id'] === (string) $categorie['id'] ? 'selected' : '' ?>>
                            <?= e($categorie['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erreurs['categorie_id'])): ?>
                    <span class="erreur"><?= e($erreurs['categorie_id']) ?></span>
                <?php endif; ?>
            </p>

            <p class="champ<?= isset($erreurs['prix']) ? ' champ--erreur' : '' ?>">
                <label for="prix">Prix (CHF) *</label>
                <input type="text" id="prix" name="prix"
                       value="<?= e($valeurs['prix']) ?>" inputmode="decimal">
                <?php if (isset($erreurs['prix'])): ?>
                    <span class="erreur"><?= e($erreurs['prix']) ?></span>
                <?php endif; ?>
            </p>

            <p class="champ<?= isset($erreurs['description']) ? ' champ--erreur' : '' ?>">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= e($valeurs['description']) ?></textarea>
                <?php if (isset($erreurs['description'])): ?>
                    <span class="erreur"><?= e($erreurs['description']) ?></span>
                <?php endif; ?>
            </p>

            <p class="champ<?= isset($erreurs['image']) ? ' champ--erreur' : '' ?>">
                <label for="image">Fichier image</label>
                <input type="text" id="image" name="image"
                       value="<?= e($valeurs['image']) ?>" placeholder="hoodie-noir.jpg">
                <?php if (isset($erreurs['image'])): ?>
                    <span class="erreur"><?= e($erreurs['image']) ?></span>
                <?php endif; ?>
            </p>
        </fieldset>

        <fieldset>
            <legend>Stock par taille</legend>

            <?php if (isset($erreurs['stock'])): ?>
                <p class="erreur"><?= e($erreurs['stock']) ?></p>
            <?php endif; ?>

            <p class="aide">Videz une taille pour la retirer du produit.
               Les tailles marquees d'un asterisque ont un historique de commandes
               et ne peuvent pas etre supprimees.</p>

            <div class="grille-tailles">
                <?php foreach ($tailles as $taille): ?>
                    <?php $tid = (int) $taille['id']; ?>
                    <p class="champ-taille">
                        <label for="stock_<?= $tid ?>">
                            <?= e($taille['code']) ?>
                            <?php if (isset($engagees[$tid])): ?>
                                <span class="badge" title="Presente dans une commande">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="stock_<?= $tid ?>"
                               name="stock[<?= $tid ?>]"
                               value="<?= e((string) ($valeurs['stocks'][$tid] ?? '')) ?>"
                               inputmode="numeric" size="4">
                    </p>
                <?php endforeach; ?>
            </div>

            <?php if ($engagees): ?>
                <p class="aide">* Taille commandee au moins une fois. Mettez 0 pour la rendre
                   indisponible sans casser l'historique.</p>
            <?php endif; ?>
        </fieldset>

        <p class="actions">
            <button type="submit">Enregistrer les modifications</button>
            <a href="<?= BASE_URL ?>/admin/produit-detail.php?id=<?= $id ?>">Annuler</a>
        </p>
    </form>

</main>

</body>
</html>