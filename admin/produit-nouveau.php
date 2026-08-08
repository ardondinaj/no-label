<?php
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../config/db.php';

exigerAdmin();

// Donnees necessaires au formulaire
$categories = $pdo->query('SELECT id, nom FROM categorie ORDER BY nom')->fetchAll();
$tailles    = $pdo->query('SELECT id, code FROM taille ORDER BY ordre')->fetchAll();

$erreurs = [];
$valeurs = [
    'nom'          => '',
    'categorie_id' => '',
    'description'  => '',
    'prix'         => '',
    'image'        => '',
    'stocks'       => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Recuperation ---
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

    // --- Validation : categorie (doit exister en base) ---
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

    // --- Validation : nom de fichier image ---
    if ($valeurs['image'] !== '' && !preg_match('/^[\w\-]+\.(jpg|jpeg|png|webp)$/i', $valeurs['image'])) {
        $erreurs['image'] = 'Nom de fichier invalide (attendu : nom-image.jpg).';
    }

    // --- Validation : stocks ---
    $stocksValides = [];
    $totalStock    = 0;

    foreach ($tailles as $taille) {
        $saisie = trim($valeurs['stocks'][$taille['id']] ?? '');

        if ($saisie === '') {
            continue;   // taille non proposee pour ce produit
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

        $stocksValides[$taille['id']] = $quantite;
        $totalStock += $quantite;
    }

    if (!isset($erreurs['stock']) && !$stocksValides) {
        $erreurs['stock'] = 'Renseignez au moins une taille avec sa quantite.';
    }

    // --- Enregistrement ---
    if (!$erreurs) {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO produit (categorie_id, nom, description, prix, image, actif)
                 VALUES (:cat, :nom, :desc, :prix, :image, 1)'
            );
            $stmt->execute([
                ':cat'   => $valeurs['categorie_id'],
                ':nom'   => $valeurs['nom'],
                ':desc'  => $valeurs['description'] !== '' ? $valeurs['description'] : null,
                ':prix'  => $valeurs['prix'],
                ':image' => $valeurs['image'] !== '' ? $valeurs['image'] : null,
            ]);

            $produitId = (int) $pdo->lastInsertId();

            $stmtStock = $pdo->prepare(
                'INSERT INTO stock (produit_id, taille_id, quantite)
                 VALUES (:pid, :tid, :qte)'
            );

            foreach ($stocksValides as $tailleId => $quantite) {
                $stmtStock->execute([
                    ':pid' => $produitId,
                    ':tid' => $tailleId,
                    ':qte' => $quantite,
                ]);
            }

            $pdo->commit();

            messageFlash('succes',
                'Produit « ' . $valeurs['nom'] . ' » cree avec '
                . count($stocksValides) . ' taille(s), ' . $totalStock . ' pieces au total.');

            rediriger(BASE_URL . '/admin/produits.php');

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erreurs['general'] = DEBUG
                ? 'Erreur : ' . $e->getMessage()
                : 'Une erreur est survenue, le produit n\'a pas ete cree.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau produit — NO LABEL</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

</head>
<body>

<header class="admin-header">
    <h1>Nouveau produit</h1>
    <nav>
        <a href="<?= BASE_URL ?>/admin/index.php">Tableau de bord</a>
        <a href="<?= BASE_URL ?>/admin/produits.php">Produits</a>
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

    <form method="post" action="" class="form-produit" novalidate>

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

            <p class="aide">Laissez vide une taille qui n'est pas proposee pour ce produit.</p>

            <div class="grille-tailles">
                <?php foreach ($tailles as $taille): ?>
                    <p class="champ-taille">
                        <label for="stock_<?= (int) $taille['id'] ?>"><?= e($taille['code']) ?></label>
                        <input type="text" id="stock_<?= (int) $taille['id'] ?>"
                               name="stock[<?= (int) $taille['id'] ?>]"
                               value="<?= e($valeurs['stocks'][$taille['id']] ?? '') ?>"
                               inputmode="numeric" size="4">
                    </p>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <p class="actions">
            <button type="submit">Creer le produit</button>
            <a href="<?= BASE_URL ?>/admin/produits.php">Annuler</a>
        </p>
    </form>

</main>

</body>
</html>