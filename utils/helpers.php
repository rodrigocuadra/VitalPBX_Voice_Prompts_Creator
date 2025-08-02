<?php
/**
 * ============================================================================
 * File: utils/helpers.php
 * ============================================================================
 * Purpose:
 * --------
 * This file provides **utility helper functions** that are used throughout
 * the Text-to-Speech Management System. These include:
 *
 * 1. Permission checking based on the user's session.
 * 2. Detection of debug mode for the currently logged-in user.
 * 3. Centralized debug logging with daily log rotation.
 *
 * These functions require that a valid PHP session is active and that
 * the session contains user information (`$_SESSION['userdata']`).
 *
 * Functions:
 * ----------
 * - check_permission(int $code): bool
 *     Checks whether a specific permission flag is enabled for the
 *     currently logged-in user.
 *
 * - current_user_debug(): bool
 *     Returns true if the current user has debug mode enabled.
 *
 * - debug_log(string $message, string $context = 'general'): void
 *     Writes debug log messages to a daily log file when debug mode is enabled.
 *
 * Session Data Dependency:
 * ------------------------
 * These functions expect:
 *   $_SESSION['userdata']['Permisos'] = string of 20 characters
 *     Each character represents a module/permission:
 *       - 'S' = enabled
 *       - 'N' = disabled
 *
 * Security and Logging:
 * ---------------------
 * - Logging only happens if debug mode is enabled.
 * - Logs are stored in the `logs/` directory with filenames:
 *       logs/debug_YYYY-MM-DD.log
 *
 * Example Usage:
 * --------------
 *   if (check_permission(2)) {
 *       // User has permission to access Text-to-Speech module
 *   }
 *
 *   debug_log("Profile updated", "voice_profiles");
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

/**
 * Checks if the current user has a specific permission enabled.
 *
 * @param int $code Permission index (1-based: 1 = Dashboard, 2 = TTS, etc.)
 * @return bool True if permission is enabled ('S'), false otherwise.
 */
function check_permission(int $code): bool
{
    // Convert 1-based permission index to 0-based
    $index = $code - 1;

    // Ensure the session contains the permissions string
    if (!isset($_SESSION['userdata']['Permisos'])) {
        return false;
    }

    $perms = $_SESSION['userdata']['Permisos'];
    return strlen($perms) > $index && $perms[$index] === 'S';
}

/**
 * Checks if the current user has debug mode enabled.
 *
 * Debug mode is controlled by the permission bit at position 16
 * (index 15 in zero-based indexing).
 *
 * @return bool True if debug mode is enabled, false otherwise.
 */
function current_user_debug(): bool
{
    return check_permission(16);
}

/**
 * Writes a debug message to a log file if debug mode is enabled.
 *
 * Each log entry is timestamped with hours, minutes, and seconds,
 * and includes a context tag to identify the module or component
 * that generated the log.
 *
 * Log files are stored in:
 *    /logs/debug_YYYY-MM-DD.log
 *
 * @param string $message Log message
 * @param string $context Context or component name (default: "general")
 * @return void
 */
function debug_log(string $message, string $context = 'general'): void
{
    if (!current_user_debug()) {
        return; // Debug mode not enabled; do not log
    }

    $dir = __DIR__ . '/../logs/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $file = $dir . 'debug_' . date('Y-m-d') . '.log';
    $logLine = '[' . date('H:i:s') . "][$context] $message" . PHP_EOL;
    file_put_contents($file, $logLine, FILE_APPEND);
}
