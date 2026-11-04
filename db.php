<?php
/**
 * Database connection (PDO).
 * Edit these values to match your local MySQL setup
 */
$DB_HOST = 'localhost';
$DB_NAME = 'parking_db';
$DB_USER = 'root';
$DB_PASS = '';          // XAMPP default is empty; MAMP default is 'root'

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw on SQL errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]
    );
} catch (PDOException $e) {
    // Do not leak connection details to the browser
    http_response_code(500);
    die('Database connection failed. Check config/db.php.');
}
