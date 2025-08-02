<?php
/**
 * ============================================================================
 * File: config/database.php
 * ============================================================================
 * This file defines a single function: getPDO().
 *
 * Purpose:
 * --------
 * Provides a standardized, secure connection to a MySQL database
 * using PDO (PHP Data Objects). All modules of the application
 * must use this function to interact with the database.
 *
 * Key Features:
 * -------------
 * 1. Uses PDO with real prepared statements (no emulation).
 * 2. Sets UTF-8 (utf8mb4) as the character encoding to fully support Unicode.
 * 3. Enables exception-based error handling for simpler debugging.
 * 4. Logs successful connections or failures using debug_log().
 * 5. Avoids exposing sensitive error details to the user.
 *
 * Example Usage:
 * --------------
 * require_once __DIR__ . '/../config/database.php';
 *
 * try {
 *     $pdo = getPDO();
 *     $stmt = $pdo->query("SELECT * FROM users");
 *     $users = $stmt->fetchAll();
 * } catch (PDOException $e) {
 *     // Handle or log error
 *     echo "Database connection error.";
 * }
 *
 * Security Notes:
 * ---------------
 * - Credentials are currently hardcoded for simplicity.
 *   In production, move them to environment variables (.env)
 *   or a separate secured configuration file.
 * - Always catch exceptions when calling getPDO() to prevent
 *   leaking database errors to end users.
 *
 * Dependencies:
 * -------------
 * - utils/helpers.php (for debug_log function)
 *
 * @return PDO
 * @throws PDOException Throws a generic PDOException if the connection fails.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

// Strict types
declare(strict_types=1);

// Include helper functions for logging
require_once __DIR__ . '/../utils/helpers.php';

/**
 * Establish and return a PDO database connection.
 *
 * @return PDO  Returns a PDO instance configured for MySQL.
 * @throws PDOException If the database connection fails.
 */
function getPDO(): PDO
{
    // ------------------------------------------------------------------------
    // Connection parameters
    // ------------------------------------------------------------------------
    // NOTE: In production, these values should come from a secure source,
    //       such as environment variables or a configuration file outside
    //       of the repository.
    // ------------------------------------------------------------------------
    $host     = 'localhost';
    $dbname   = '';
    $user     = '';
    $password = '';

    try {
        // Build DSN string
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        // PDO options:
        // - ERRMODE_EXCEPTION: Throw exceptions on errors
        // - DEFAULT_FETCH_MODE: Fetch associative arrays
        // - EMULATE_PREPARES: Use native prepared statements (more secure)
        $pdo = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Log success (only visible if debug logging is enabled)
        debug_log("Database connection established successfully", 'database');

        return $pdo;

    } catch (PDOException $e) {
        // Log detailed error internally for debugging
        debug_log("Failed to connect to the database: " . $e->getMessage(), 'database');

        // Throw a generic exception to avoid exposing sensitive details
        throw new PDOException("Database connection error.");
    }
}
