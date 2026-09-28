<?php
declare(strict_types=1);

/**
 * Single reusable PDO connection for the whole app.
 * Any page that needs the database does:
 *     require_once __DIR__ . '/../config/app.php';
 *     require_once __DIR__ . '/../config/database.php';
 * and then has a ready-to-use $pdo variable.
 */

// --- Edit these if your XAMPP MySQL setup differs from the default ---
$dbHost = 'localhost';
$dbName = 'ratemyfaculty';
$dbUser = 'root';
$dbPass = ''; // default XAMPP root password is blank
$dbCharset = 'utf8mb4';
// -----------------------------------------------------------------------

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // rows as assoc arrays
    PDO::ATTR_EMULATE_PREPARES => false,                   // use real prepared statements
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    // Never expose $e->getMessage() to the browser — it can contain
    // the DSN, username, or fragments of the query.
    error_log('[RateMyFaculty] Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('A server errorrrr occurred. Please try again later.');
}