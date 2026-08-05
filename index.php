<?php
// Page d'accueil temporaire - test de déploiement
$titre = "NO LABEL";
$date  = date('d.m.Y H:i');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titre ?></title>
</head>
<body>
    <h1><?= $titre ?></h1>
    <p>Installation fonctionnelle.</p>
    <p>Version de PHP : <?= phpversion() ?></p>
    <p>Page générée le <?= $date ?></p>
</body>
</html>