<?php
// ============================================================
//  Configuration de la base de données — MODÈLE
//  Copie ce fichier vers database.php et renseigne tes accès :
//      cp config/database.example.php config/database.php
//  Ne versionne JAMAIS database.php (déjà dans .gitignore).
// ============================================================

// --- MySQL / MariaDB (par défaut) ---
define('DB_DRIVER', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'correctqcm');
define('DB_USER', 'correctqcm');
define('DB_PASS', 'change-moi');

// --- PostgreSQL (décommenter et adapter si tu utilises pgsql) ---
// define('DB_DRIVER', 'pgsql');
// define('DB_HOST', '127.0.0.1');
// define('DB_PORT', '5432');
// define('DB_NAME', 'correctqcm');
// define('DB_USER', 'postgres');
// define('DB_PASS', 'change-moi');

/**
 * Retourne une connexion PDO unique (réutilisée par tous les endpoints).
 */
function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = DB_DRIVER;
    $dsn = match ($driver) {
        'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME),
        default => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
    };

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Connexion à la base de données impossible : ' . $e->getMessage(),
        ]);
        exit;
    }

    return $pdo;
}