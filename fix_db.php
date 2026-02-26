<?php
// Simple PDO script to fix collations
$dsn = 'mysql:host=127.0.0.1;dbname=homebalance_db;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERR_MODE => PDO::ERR_MODE_EXCEPTION
    ]);

    echo "Connected to database.\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    echo "Disabled foreign key checks.\n";

    $tables = ['user', 'portal_notification', 'family', 'message', 'conversation', 'conversation_participant', 'audit_trail', 'account_notification'];

    foreach ($tables as $table) {
        echo "Converting $table... ";
        try {
            $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "OK\n";
        }
        catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Enabled foreign key checks.\n";
    echo "All done!\n";


}
catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
