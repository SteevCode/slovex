<?php
/**
 * Solvex Report — Database Connection (PDO)
 * 
 * Returns a singleton PDO instance with:
 * - utf8mb4 charset
 * - InnoDB engine (enforced by schema)
 * - Exception error mode
 * - Emulated prepares OFF
 * - Asia/Kolkata timezone on every connection
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $ex) {
            // Never expose DB credentials in error messages
            error_log('Database connection failed: ' . $ex->getMessage());
            die('Database connection failed. Please check configuration.');
        }
    }

    return $pdo;
}
