<?php
/**
 * ============================================================================
 * File: modules/users/update_password.php
 * ============================================================================
 * Purpose:
 * --------
 * This script allows a logged-in user to change their password securely.
 * It verifies the current password and updates it to a new password
 * using bcrypt hashing.
 *
 * Access:
 * -------
 * - Only users with permission index 5 (Change Password module) can use this.
 * - The username is retrieved from the active session (cannot be changed).
 *
 * Request (POST):
 * ---------------
 * - username         (auto-filled from session, read-only in the form)
 * - current_password (current password for verification)
 * - new_password     (the new password to set)
 *
 * Response (JSON):
 * ----------------
 * Success:
 *   {
 *     "success": true,
 *     "message": "Password updated successfully."
 *   }
 *
 * Failure:
 *   {
 *     "success": false,
 *     "message": "Error description."
 *   }
 *
 * Workflow:
 * ---------
 * 1. Validate request and session data.
 * 2. Retrieve the current password hash from the database.
 * 3. Verify that the provided current password matches.
 * 4. Hash the new password using bcrypt and update it in the database.
 * 5. Return a success message.
 *
 * Security:
 * ---------
 * - Passwords are never stored or compared in plain text.
 * - Bcrypt hashing is used for secure password storage.
 * - The script prevents changing passwords without verifying the current password.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

header('Content-Type: application/json');

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// ---------------------------------------------------------------------------
// Access Validation
// Permission 5 = Change Password module
// ---------------------------------------------------------------------------
validarAccesoModulo(5);

// ---------------------------------------------------------------------------
// Session & Input
// ---------------------------------------------------------------------------
session_start();
$username    = $_SESSION['username'] ?? '';
$currentPass = $_POST['current_password'] ?? '';
$newPass     = $_POST['new_password'] ?? '';

// ---------------------------------------------------------------------------
// Input Validation
// ---------------------------------------------------------------------------
if (!$username || !$currentPass || !$newPass) {
    debug_log("Attempt to change password with incomplete data", "update_password");
    echo json_encode([
        'success' => false,
        'message' => 'Incomplete data.'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    // -----------------------------------------------------------------------
    // Step 1: Verify current password
    // -----------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        debug_log("User '$username' not found during password change", "update_password");
        echo json_encode([
            'success' => false,
            'message' => 'User not found.'
        ]);
        exit;
    }

    $storedPassword = $record['password'];

    // Verify the current password using password_verify
    if (!password_verify($currentPass, $storedPassword)) {
        debug_log("Current password verification failed for user '$username'", "update_password");
        echo json_encode([
            'success' => false,
            'message' => 'The current password is incorrect.'
        ]);
        exit;
    }

    // -----------------------------------------------------------------------
    // Step 2: Update password
    // -----------------------------------------------------------------------
    $hashedNewPassword = password_hash($newPass, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?, created_at = NOW()
        WHERE username = ?
    ");
    $stmt->execute([$hashedNewPassword, $username]);

    debug_log("Password updated successfully for user '$username'", "update_password");
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully.'
    ]);

} catch (Throwable $e) {
    error_log("Error in update_password.php: " . $e->getMessage());
    debug_log("Exception while updating password: " . $e->getMessage(), "update_password");
    echo json_encode([
        'success' => false,
        'message' => 'Error while updating password.'
    ]);
}
