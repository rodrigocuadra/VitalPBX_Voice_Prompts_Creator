<?php
/**
 * ============================================================================
 * File: modules/email/email_settings.php
 * ============================================================================
 * Purpose:
 * --------
 * This module allows system administrators to configure SMTP settings for the
 * Text-to-Speech platform. It provides:
 *   1. A form to save SMTP credentials (server, port, username, password,
 *      from address, and sender name) into a JSON configuration file.
 *   2. A button to send a test email using the configured SMTP credentials
 *      to confirm that email sending is working correctly.
 *
 * This module will be used for future features like:
 * - Forgot Password email delivery.
 * - Notifications and system alerts via email.
 *
 * Permissions:
 * ------------
 * This module requires permission index 6 (Email Configuration).
 *
 * Workflow:
 * ---------
 * 1. On GET:
 *    - Loads current configuration from `email_config.json`.
 *    - Displays a form populated with existing configuration values.
 *
 * 2. On POST:
 *    a. If the "Save Configuration" button is pressed:
 *       - Stores submitted SMTP data in JSON format into `email_config.json`.
 *       - Displays a success message.
 *
 *    b. If the "Send Test Email" button is pressed:
 *       - Calls `send_test_email()` (defined in `utils/email.php`) to send a test
 *         email to the specified recipient using the provided SMTP credentials.
 *       - Displays success or failure messages based on the result.
 *
 * Dependencies:
 * -------------
 * - layouts/layout.php    : Loads the global application layout and sidebar.
 * - config/database.php   : (Indirectly used if database logging is enabled).
 * - utils/helpers.php     : Provides debug_log() and session validation.
 * - models/login_model.php: Provides validarAccesoModulo() for permissions.
 * - utils/email.php       : Must define the function:
 *                           send_test_email(array $config, string $to): bool
 *
 * Files:
 * ------
 * - Configuration: modules/email/email_config.json
 * - Log errors:    logs/email_errors.log
 *
 * Security Notes:
 * ---------------
 * - The SMTP password is stored in plain JSON on the server; ensure proper
 *   filesystem permissions are enforced to protect this file.
 * - The test email functionality should be restricted to administrators.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "Email Settings";
$page_icon = "bi-envelope-fill";
$page_subtitle = "Configure SMTP server and send test emails";

require_once __DIR__ . '/../../layouts/layout.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../models/login_model.php';
// Email helper functions (must implement send_test_email)
require_once __DIR__ . '/../../utils/email.php';

// Check user permissions (index 6 = Email Configuration)
validarAccesoModulo(6);

// Ensure session started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Configuration and log file paths
// ---------------------------------------------------------------------------
$configFile = __DIR__ . '/email_config.json';
$logFile    = __DIR__ . '/../../logs/email_errors.log';

// Default page messages
$message    = '';
$testResult = '';

// Load configuration if file exists
$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

debug_log("Access to SMTP configuration module", "email_settings");

// ---------------------------------------------------------------------------
// Save SMTP Configuration
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    // Collect configuration from form
    $config = [
        'host'       => $_POST['host'] ?? '',
        'port'       => $_POST['port'] ?? 587,
        'username'   => $_POST['username'] ?? '',
        'password'   => $_POST['password'] ?? '',
        'from'       => $_POST['from'] ?? '',
        'from_name'  => $_POST['from_name'] ?? '',
        'test_to'    => $_POST['test_to'] ?? ''
    ];

    // Save configuration to JSON file
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    $message = '✅ SMTP configuration saved successfully.';
    debug_log("SMTP configuration updated", "email_settings");
}

// ---------------------------------------------------------------------------
// Send Test Email
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    // Call the email sender function (must be implemented in utils/email.php)
    $sent = send_test_email($config, $config['test_to']);

    if ($sent) {
        $testResult = "<div class='alert alert-success'>✅ Test email sent successfully to <strong>{$config['test_to']}</strong>.</div>";
        debug_log("Test email sent to {$config['test_to']}", "email_settings");
    } else {
        $testResult = "<div class='alert alert-danger'>❌ Failed to send test email. Check <code>logs/email_errors.log</code>.</div>";
        debug_log("Error sending test email to {$config['test_to']}", "email_settings");
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4"><?= htmlspecialchars($page_title) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?= $testResult ?>

    <!-- SMTP Configuration Form -->
    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">SMTP Server:</label>
            <input type="text" name="host" class="form-control" value="<?= htmlspecialchars($config['host'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Port:</label>
            <input type="number" name="port" class="form-control" value="<?= htmlspecialchars($config['port'] ?? 587) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">SMTP Username:</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($config['username'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">SMTP Password:</label>
            <input type="password" name="password" class="form-control" value="<?= htmlspecialchars($config['password'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">From Email:</label>
            <input type="email" name="from" class="form-control" value="<?= htmlspecialchars($config['from'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">From Name:</label>
            <input type="text" name="from_name" class="form-control" value="<?= htmlspecialchars($config['from_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-12">
            <label class="form-label">Test recipient:</label>
            <input type="email" name="test_to" class="form-control" value="<?= htmlspecialchars($config['test_to'] ?? '') ?>" required>
        </div>

        <div class="col-12 text-end">
            <button type="submit" name="save_config" class="btn btn-primary">💾 Save Configuration</button>
            <button type="submit" name="test_email" class="btn btn-success">📧 Send Test Email</button>
        </div>
    </form>
</div>
