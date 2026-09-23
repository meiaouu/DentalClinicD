<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/app/Core/Database.php';

use App\Core\Database;

try {
    $pdo = Database::getConnection();

    $result = $pdo
        ->query('SELECT DATABASE() AS database_name')
        ->fetch();

    echo '<h1>DATABASE CONNECTION SUCCESS</h1>';
    echo '<p>Connected database: ' .
        htmlspecialchars((string)($result['database_name'] ?? 'unknown')) .
        '</p>';

} catch (Throwable $e) {
    http_response_code(500);

    echo '<h1>DATABASE CONNECTION FAILED</h1>';
    echo '<pre>' .
        htmlspecialchars($e->getMessage()) .
        '</pre>';
}