<?php
declare(strict_types=1);

$host = 'localhost';
$dbname = 'stalk_stable_db';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Stalk & Stable DB connection failed: ' . $e->getMessage());
    die('Database connection failed. Please check the database configuration.');
}
