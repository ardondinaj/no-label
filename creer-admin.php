<?php
require_once __DIR__ . '/config/db.php';

$email      = 'admin@nolabel.ch';
$motDePasse = 'ardon1217';

$stmt = $pdo->prepare(
    "INSERT INTO utilisateur (email, mot_de_passe, nom, prenom, role, actif)
     VALUES (:email, :hash, 'Dinaj', 'Ardon', 'admin', 1)"
);
$stmt->execute([
    ':email' => $email,
    ':hash'  => password_hash($motDePasse, PASSWORD_DEFAULT),
]);

echo "Compte admin cree : " . htmlspecialchars($email);