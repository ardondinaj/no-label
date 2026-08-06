<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/fonctions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rediriger(BASE_URL . '/index.php');
}

$produitId = filter_input(INPUT_POST, 'produit_id', FILTER_VALIDATE_INT);
$tailleId  = filter_input(INPUT_POST, 'taille_id',  FILTER_VALIDATE_INT);
$quantite  = filter_input(INPUT_POST, 'quantite',   FILTER_VALIDATE_INT);

// --- Validation ---
if (!$produitId || !$tailleId) {
    messageFlash('erreur', 'Veuillez choisir une taille.');
    rediriger(BASE_URL . '/produit.php?id=' . (int) $produitId);
}

if (!$quantite || $quantite < 1) {
    $quantite = 1;
} elseif ($quantite > 10) {
    $quantite = 10;
}

// --- Verification en base : le couple produit/taille existe-t-il et est-il dispo ? ---
$stmt = $pdo->prepare(
    'SELECT p.nom, p.prix, t.code AS taille, s.quantite AS stock
     FROM stock s
     JOIN produit p ON p.id = s.produit_id
     JOIN taille  t ON t.id = s.taille_id
     WHERE s.produit_id = :pid AND s.taille_id = :tid AND p.actif = 1'
);
$stmt->execute([':pid' => $produitId, ':tid' => $tailleId]);
$article = $stmt->fetch();

if (!$article) {
    messageFlash('erreur', 'Cet article n\'est pas disponible dans cette taille.');
    rediriger(BASE_URL . '/produit.php?id=' . $produitId);
}

$cle = $produitId . '-' . $tailleId;

// Quantite deja au panier pour cette combinaison
$dejaAuPanier = (int) ($_SESSION['panier'][$cle]['quantite'] ?? 0);
$total        = $dejaAuPanier + $quantite;

if ($total > (int) $article['stock']) {
    $restant = (int) $article['stock'] - $dejaAuPanier;

    if ($restant <= 0) {
        messageFlash('erreur',
            'Vous avez deja tout le stock disponible de cet article en taille '
            . $article['taille'] . ' dans votre panier.');
    } else {
        messageFlash('erreur',
            'Stock insuffisant : il ne reste que ' . $restant
            . ' piece(s) disponible(s) en taille ' . $article['taille'] . '.');
    }
    rediriger(BASE_URL . '/produit.php?id=' . $produitId);
}

// --- Ajout au panier ---
$_SESSION['panier'][$cle] = [
    'produit_id' => $produitId,
    'taille_id'  => $tailleId,
    'quantite'   => $total,
];

messageFlash('succes',
    $article['nom'] . ' (taille ' . $article['taille'] . ') ajoute au panier.');

rediriger(BASE_URL . '/panier.php');