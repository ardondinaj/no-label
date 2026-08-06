<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/fonctions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rediriger(BASE_URL . '/panier.php');
}

$action = $_POST['action'] ?? '';
$cle    = $_POST['cle'] ?? '';

if ($action === 'vider') {
    unset($_SESSION['panier']);
    messageFlash('succes', 'Votre panier a ete vide.');
    rediriger(BASE_URL . '/panier.php');
}

if (!isset($_SESSION['panier'][$cle])) {
    messageFlash('erreur', 'Article introuvable dans le panier.');
    rediriger(BASE_URL . '/panier.php');
}

$ligne = $_SESSION['panier'][$cle];

if ($action === 'retirer') {
    unset($_SESSION['panier'][$cle]);
    messageFlash('succes', 'Article retire du panier.');
    rediriger(BASE_URL . '/panier.php');
}

if ($action === 'quantite') {
    $quantite = filter_input(INPUT_POST, 'quantite', FILTER_VALIDATE_INT);

    if (!$quantite || $quantite < 1) {
        messageFlash('erreur', 'La quantite doit etre d\'au moins 1.');
        rediriger(BASE_URL . '/panier.php');
    }

    // Verification du stock
    $stmt = $pdo->prepare(
        'SELECT s.quantite AS stock, t.code AS taille, p.nom
         FROM stock s
         JOIN produit p ON p.id = s.produit_id
         JOIN taille  t ON t.id = s.taille_id
         WHERE s.produit_id = :pid AND s.taille_id = :tid'
    );
    $stmt->execute([':pid' => $ligne['produit_id'], ':tid' => $ligne['taille_id']]);
    $article = $stmt->fetch();

    if (!$article || $quantite > (int) $article['stock']) {
        messageFlash('erreur',
            'Stock insuffisant : ' . (int) ($article['stock'] ?? 0)
            . ' piece(s) disponible(s) en taille ' . ($article['taille'] ?? '?') . '.');
        rediriger(BASE_URL . '/panier.php');
    }

    $_SESSION['panier'][$cle]['quantite'] = $quantite;
    messageFlash('succes', 'Quantite mise a jour.');
}

rediriger(BASE_URL . '/panier.php');