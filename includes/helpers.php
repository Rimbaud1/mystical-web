<?php
/* -----------------------------------------------------------------
 *  helpers.php  –  utilitaires + gestion des « flash »
 * ----------------------------------------------------------------- */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Empile un message flash.
 * $type : success | error | info | warning …
 */
function flash(string $type, string $msg): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type][] = $msg;
}

/**
 * Renvoie puis efface tous les flashs.
 * Toujours un array, jamais de TypeError.
 */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

/** Redirection courte */
function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

/** htmlspecialchars raccourci */
function h(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
