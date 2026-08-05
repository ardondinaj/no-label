<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/fonctions.php';

/**
 * L'utilisateur est-il connecte ?
 */
function estConnecte(): bool
{
    return isset($_SESSION['utilisateur_id']);
}

/**
 * L'utilisateur connecte est-il administrateur ?
 */
function estAdmin(): bool
{
    return estConnecte() && ($_SESSION['utilisateur_role'] ?? '') === 'admin';
}

/**
 * Bloque l'acces aux visiteurs non connectes.
 */
function exigerConnexion(): void
{
    if (!estConnecte()) {
        $_SESSION['url_demandee'] = $_SERVER['REQUEST_URI'];
        messageFlash('erreur', 'Veuillez vous connecter pour acceder a cette page.');
        rediriger(BASE_URL . '/login.php');
    }
}

/**
 * Bloque l'acces aux non-administrateurs.
 */
function exigerAdmin(): void
{
    exigerConnexion();

    if (!estAdmin()) {
        http_response_code(403);
        die('Acces refuse.');
    }
}