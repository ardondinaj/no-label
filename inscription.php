<?php
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/config/db.php';

// Deja connecte : rien a faire ici
if (estConnecte()) {
    rediriger(BASE_URL . '/index.php');
}

$erreurs = [];
$valeurs = [
    'prenom'    => '',
    'nom'       => '',
    'email'     => '',
    'telephone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($valeurs as $champ => $defaut) {
        $valeurs[$champ] = trim($_POST[$champ] ?? '');
    }
    $motDePasse   = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // --- Prenom ---
    if ($valeurs['prenom'] === '') {
        $erreurs['prenom'] = 'Le prenom est obligatoire.';
    } elseif (mb_strlen($valeurs['prenom']) < 2) {
        $erreurs['prenom'] = 'Le prenom doit contenir au moins 2 caracteres.';
    } elseif (mb_strlen($valeurs['prenom']) > 80) {
        $erreurs['prenom'] = 'Le prenom est trop long.';
    }

    // --- Nom ---
    if ($valeurs['nom'] === '') {
        $erreurs['nom'] = 'Le nom est obligatoire.';
    } elseif (mb_strlen($valeurs['nom']) < 2) {
        $erreurs['nom'] = 'Le nom doit contenir au moins 2 caracteres.';
    } elseif (mb_strlen($valeurs['nom']) > 80) {
        $erreurs['nom'] = 'Le nom est trop long.';
    }

    // --- Email ---
    if ($valeurs['email'] === '') {
        $erreurs['email'] = 'L\'adresse email est obligatoire.';
    } elseif (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Cette adresse email n\'est pas valide.';
    } elseif (mb_strlen($valeurs['email']) > 180) {
        $erreurs['email'] = 'L\'adresse email est trop longue.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => $valeurs['email']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $erreurs['email'] = 'Un compte existe deja avec cette adresse.';
        }
    }

    // --- Telephone (facultatif) ---
    if ($valeurs['telephone'] !== ''
        && !preg_match('/^[\d\s\+\-\.\/\(\)]{8,30}$/', $valeurs['telephone'])) {
        $erreurs['telephone'] = 'Numero de telephone invalide.';
    }

    // --- Mot de passe ---
    if ($motDePasse === '') {
        $erreurs['mot_de_passe'] = 'Le mot de passe est obligatoire.';
    } elseif (mb_strlen($motDePasse) < 8) {
        $erreurs['mot_de_passe'] = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $motDePasse) || !preg_match('/\d/', $motDePasse)) {
        $erreurs['mot_de_passe'] = 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    } elseif ($motDePasse !== $confirmation) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
    }

    // --- Conditions ---
    if (!isset($_POST['conditions'])) {
        $erreurs['conditions'] = 'Vous devez accepter les conditions de vente.';
    }

    // --- Creation du compte ---
    if (!$erreurs) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO utilisateur (email, mot_de_passe, nom, prenom, telephone, role, actif)
                 VALUES (:email, :hash, :nom, :prenom, :tel, 'client', 1)"
            );
            $stmt->execute([
                ':email'  => $valeurs['email'],
                ':hash'   => password_hash($motDePasse, PASSWORD_DEFAULT),
                ':nom'    => $valeurs['nom'],
                ':prenom' => $valeurs['prenom'],
                ':tel'    => $valeurs['telephone'] !== '' ? $valeurs['telephone'] : null,
            ]);

            // Connexion immediate
            session_regenerate_id(true);
            $_SESSION['utilisateur_id']     = (int) $pdo->lastInsertId();
            $_SESSION['utilisateur_nom']    = $valeurs['nom'];
            $_SESSION['utilisateur_prenom'] = $valeurs['prenom'];
            $_SESSION['utilisateur_role']   = 'client';

            messageFlash('succes', 'Bienvenue ' . $valeurs['prenom'] . ', votre compte est cree.');

            $destination = $_SESSION['url_demandee'] ?? BASE_URL . '/index.php';
            unset($_SESSION['url_demandee']);
            rediriger($destination);

        } catch (PDOException $e) {
            $erreurs['general'] = DEBUG
                ? $e->getMessage()
                : 'La creation du compte a echoue. Veuillez reessayer.';
        }
    }
}

$titrePage = 'Creer un compte';
require __DIR__ . '/includes/header.php';
?>

<h1>Creer un compte</h1>

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

<form method="post" action="" class="form-inscription" novalidate>

    <fieldset>
        <legend>Identite</legend>

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
            <label for="telephone">Telephone</label>
            <input type="tel" id="telephone" name="telephone" value="<?= e($valeurs['telephone']) ?>">
            <?php if (isset($erreurs['telephone'])): ?>
                <span class="erreur"><?= e($erreurs['telephone']) ?></span>
            <?php endif; ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Connexion</legend>

        <p class="champ<?= isset($erreurs['email']) ? ' champ--erreur' : '' ?>">
            <label for="email">Adresse email *</label>
            <input type="email" id="email" name="email" value="<?= e($valeurs['email']) ?>">
            <?php if (isset($erreurs['email'])): ?>
                <span class="erreur"><?= e($erreurs['email']) ?></span>
            <?php endif; ?>
        </p>

        <p class="champ<?= isset($erreurs['mot_de_passe']) ? ' champ--erreur' : '' ?>">
            <label for="mot_de_passe">Mot de passe *</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe">
            <small class="aide">Au moins 8 caracteres, dont une lettre et un chiffre.</small>
            <?php if (isset($erreurs['mot_de_passe'])): ?>
                <span class="erreur"><?= e($erreurs['mot_de_passe']) ?></span>
            <?php endif; ?>
        </p>

        <p class="champ<?= isset($erreurs['confirmation']) ? ' champ--erreur' : '' ?>">
            <label for="confirmation">Confirmer le mot de passe *</label>
            <input type="password" id="confirmation" name="confirmation">
            <?php if (isset($erreurs['confirmation'])): ?>
                <span class="erreur"><?= e($erreurs['confirmation']) ?></span>
            <?php endif; ?>
        </p>
    </fieldset>

    <p class="champ<?= isset($erreurs['conditions']) ? ' champ--erreur' : '' ?>">
        <label>
            <input type="checkbox" name="conditions" value="1">
            J'accepte les conditions de vente.
        </label>
        <?php if (isset($erreurs['conditions'])): ?>
            <span class="erreur"><?= e($erreurs['conditions']) ?></span>
        <?php endif; ?>
    </p>

    <p class="actions">
        <button type="submit" class="bouton bouton--principal">Creer mon compte</button>
    </p>
</form>

<p>Deja inscrit ? <a href="<?= BASE_URL ?>/login.php">Se connecter</a></p>

<?php require __DIR__ . '/includes/footer.php'; ?>