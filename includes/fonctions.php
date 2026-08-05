<?php

/**
 * Echappe une chaine avant affichage HTML (protection XSS).
 */
function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige vers une URL et stoppe l'execution.
 */
function rediriger(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Enregistre un message a afficher sur la page suivante.
 */
function messageFlash(string $type, string $texte): void
{
    $_SESSION['flash'][] = ['type' => $type, 'texte' => $texte];
}

/**
 * Recupere et vide les messages en attente.
 */
function lireFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}