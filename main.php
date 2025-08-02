<?php
/**
 * ============================================================================
 * File: main.php
 * ============================================================================
 * Purpose:
 * --------
 * This script acts as a **simple module router/dispatcher** for the
 * Text-to-Speech Management System.  
 * Based on the `mod` query parameter, it loads the corresponding PHP file.
 *
 * Behavior:
 * ---------
 * 1. Reads the `mod` parameter from the query string (e.g., `?mod=users`).
 * 2. Looks up the file path in a predefined `$map`.
 * 3. If the module exists and the file is found:
 *      - Logs the action using `debug_log()`.
 *      - Includes the corresponding PHP file.
 * 4. If the module does not exist:
 *      - Logs the invalid access attempt.
 *      - Displays an error message.
 *
 * Modules available:
 * ------------------
 * - text2speech     => modules/stts/tts.php
 * - users           => modules/users/users.php
 * - changepassword  => modules/users/change_password.php
 * - voiceprofiles   => modules/voice_profiles/voice_profiles.php
 * - email-settings  => modules/email/email_settings.php
 *
 * Security:
 * ---------
 * - Prevents direct access to undefined modules.
 * - Ensures files are included only from a controlled whitelist.
 *
 * Dependencies:
 * -------------
 * - utils/helpers.php (for debug_log)
 * - All modules listed in `$map`
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

declare(strict_types=1);

// Ensure an active session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include common helper functions
require_once __DIR__ . '/utils/helpers.php';

// Get the `mod` parameter from the URL (example: ?mod=users)
$mod = $_GET['mod'] ?? '';

// Map of valid modules and their corresponding PHP files
$map = [
    // Text-to-Speech module
    'text2speech'    => 'modules/stts/tts.php',

    // Users management
    'users'          => 'modules/users/users.php',
    'changepassword' => 'modules/users/change_password.php',

    // Voice profiles
    'voiceprofiles'  => 'modules/voice_profiles/voice_profiles.php',

    // Email configuration
    'email-settings' => 'modules/email/email_settings.php',
];

// Validate module existence and include the corresponding file
if (isset($map[$mod]) && file_exists($map[$mod])) {

    debug_log("Loading module: $mod -> {$map[$mod]}", 'main');

    require $map[$mod];

} else {
    // Invalid or missing module
    debug_log("Invalid module access attempt: '$mod'", 'main');

    echo "<div class='container mt-5'>
            <div class='alert alert-danger shadow-sm'>
                <h5 class='mb-2'>⚠️ Invalid or missing module</h5>
                <p>The requested module <strong>'" . htmlspecialchars($mod) . "'</strong> does not exist or has been removed.</p>
              </div>
          </div>";
}
