<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$connectionParams = [
    'url' => $_ENV['DATABASE_URL'],
];
$conn = DriverManager::getConnection($connectionParams);

function dumpTableCollations($conn, $tableName)
{
    echo "Collations for table: $tableName\n";
    $stmt = $conn->executeQuery("SHOW FULL COLUMNS FROM $tableName");
    while ($row = $stmt->fetchAssociative()) {
        echo sprintf("Column: %s, Collation: %s\n", $row['Field'], $row['Collation']);
    }
    echo "\n";
}

dumpTableCollations($conn, 'portal_notification');
dumpTableCollations($conn, 'user');
