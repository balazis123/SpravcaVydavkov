<?php
define('BASE', '/ROO_Apka_SB');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function aktualny_pouzivatel(): ?array { return $_SESSION['pouzivatel'] ?? null; }

function vyzadovat_prihlasenie(): void {
    if (!aktualny_pouzivatel()) { header('Location: ' . BASE . '/login.php'); exit; }
}

function vyzadovat_admina(): void {
    $p = aktualny_pouzivatel();
    if (!$p || $p['rola'] !== 'admin') { header('Location: ' . BASE . '/index.php'); exit; }
}

function presmerovat_prihlaseneho(): void {
    if (aktualny_pouzivatel()) { header('Location: ' . BASE . '/index.php'); exit; }
}

function nastav_spravu(string $typ, string $text): void {
    $_SESSION['sprava'] = compact('typ', 'text');
}

function zober_spravu(): ?array {
    $sprava = $_SESSION['sprava'] ?? null;
    unset($_SESSION['sprava']);
    return $sprava;
}

function escapuj(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
