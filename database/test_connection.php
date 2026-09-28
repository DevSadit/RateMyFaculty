<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$currentDb = $pdo->query('SELECT DATABASE()')->fetchColumn();

echo "Connection successful. Connected to database: " . htmlspecialchars((string) $currentDb, ENT_QUOTES, 'UTF-8');