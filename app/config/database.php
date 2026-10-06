<?php
/**
 * database.php
 * Central place for the DB connection. Every model/service should
 * get its connection from here — never open a new mysqli/PDO
 * connection somewhere else, or a bug in one place won't show up
 * when you check another.
 */

require_once __DIR__ . '/env.php';

function getDbConnection(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(
            env('DB_HOST', '127.0.0.1'),
            env('DB_USER', 'root'),
            env('DB_PASS', ''),
            env('DB_NAME', 'helpdeskcrmc')
        );
        $conn->set_charset('utf8mb4');

        if ($conn->connect_error) {
            // Logged, not echoed — never leak DB errors to the browser.
            error_log('[DB CONNECTION ERROR] ' . $conn->connect_error);
            http_response_code(500);
            die('A server error occurred. Please try again later.');
        }
    }

    return $conn;
}
