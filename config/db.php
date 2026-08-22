<?php
// config/db.php

$host = 'localhost';
$db   = 'primehashtag_hashtag';
$user = 'primehashtag_hashtag';
$pass = 'primehashtag_hashtag';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // If connection fails, try without creating database if it doesn't exist yet,
     // but since db is pre-created by user, we fail gracefully or try connecting to host.
     try {
         $pdoTemp = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
         $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
         $pdo = new PDO($dsn, $user, $pass, $options);
     } catch (\PDOException $e2) {
         die("Database connection failed: " . $e2->getMessage());
     }
}
?>
