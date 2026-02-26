<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$connectionParams = [
    'url' => $_ENV['DATABASE_URL'],
];

try {
    $conn = DriverManager::getConnection($connectionParams);

    echo "Disabling foreign key checks...\n";
    $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

    $tables = ['user', 'portal_notification', 'family', 'message', 'conversation', 'conversation_participant'];

    foreach ($tables as $table) {
        echo "Converting table $table to utf8mb4_unicode_ci...\n";
        try {
            $conn->executeStatement("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "Success.\n";
        }
        catch (\Exception $e) {
            echo "Error converting table $table: " . $e->getMessage() . "\n";
        }
    }

    echo "Enabling foreign key checks...\n";
    $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

    echo "Done.\n";
}
catch (\Exception $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
}
