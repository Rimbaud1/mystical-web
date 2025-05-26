<?php
/* Lance la session pour tout le site */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Paramètres de la BDD ---------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'bdd_mystical_dungeon');
define('DB_USER', 'root');          // à adapter
define('DB_PASS', 'root');             // à adapter

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    exit('Erreur de connexion : ' . $e->getMessage());
}
