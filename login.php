<?php
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/config/db.php';

// Deja connecte : inutile de revenir ici
if (estConnecte()) {
    rediriger(estAdmin() ? '/nolabel/admin/index.php' : '/nolabel/index.php');
}

$erreurs = [];
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if ($email === '') {
        $erreurs[] = "L'adresse email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse email n'est pas valide.";
    }

    if ($motDePasse === '') {
        $erreurs[] = 'Le mot de passe est obligatoire.';
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare(
            'SELECT id, email, mot_de_passe, nom, prenom, role, actif
             FROM utilisateur
             WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            // Message volontairement vague : ne pas reveler si l'email existe
            $erreurs[] = 'Identifiants incorrects.';
        } elseif ((int) $utilisateur['actif'] !== 1) {
            $erreurs[] = 'Ce compte a ete desactive.';
        } else {
            // Protection contre la fixation de session
            session_regenerate_id(true);

            $_SESSION['utilisateur_id']     = $utilisateur['id'];
            $_SESSION['utilisateur_nom']    = $utilisateur['nom'];
            $_SESSION['utilisateur_prenom'] = $utilisateur['prenom'];
            $_SESSION['utilisateur_role']   = $utilisateur['role'];

            messageFlash('succes', 'Bienvenue ' . $utilisateur['prenom'] . '.');

            $destination = $_SESSION['url_demandee']
                ?? ($utilisateur['role'] === 'admin' ? '/nolabel/admin/index.php' : '/nolabel/index.php');
            unset($_SESSION['url_demandee']);

            rediriger($destination);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — NO LABEL</title>
</head>
<body>
    <h1>Connexion</h1>

    <?php if ($erreurs): ?>
        <ul>
            <?php foreach ($erreurs as $erreur): ?>
                <li><?= e($erreur) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="">
        <p>
            <label for="email">Email</label><br>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
        </p>
        <p>
            <label for="mot_de_passe">Mot de passe</label><br>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
        </p>
        <p><button type="submit">Se connecter</button></p>
    </form>
</body>
</html>