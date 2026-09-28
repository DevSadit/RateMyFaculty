<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// If we reach this line, $pdo connected successfully.
$currentDb = $pdo->query('SELECT DATABASE()')->fetchColumn();

echo "Connection successful. Connected to database: " . htmlspecialchars((string) $currentDb, ENT_QUOTES, 'UTF-8');