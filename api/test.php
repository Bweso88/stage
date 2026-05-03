<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$result = [
    'php_version'   => PHP_VERSION,
    'php_ok'        => version_compare(PHP_VERSION, '8.0.0', '>='),
    'env_loaded'    => !empty($_ENV['DB_HOST']),
    'db_host'       => $_ENV['DB_HOST'] ?? 'non défini',
    'db_name'       => $_ENV['DB_NAME'] ?? 'non défini',
    'anthropic_key' => !empty($_ENV['ANTHROPIC_API_KEY']) && $_ENV['ANTHROPIC_API_KEY'] !== 'sk-ant-api03-YOUR_KEY_HERE',
    'smtp_host'     => $_ENV['SMTP_HOST'] ?? 'non défini',
    'extensions'    => [
        'pdo'       => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl'      => extension_loaded('curl'),
        'mbstring'  => extension_loaded('mbstring'),
        'json'      => extension_loaded('json'),
    ],
];

try {
    $pdo = getPDO();
    $pdo->query('SELECT 1');
    $result['db_connection'] = true;
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $result['tables'] = $tables;
} catch (Throwable $e) {
    $result['db_connection'] = false;
    $result['db_error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
