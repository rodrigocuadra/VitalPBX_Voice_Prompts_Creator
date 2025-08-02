<?php
/**
 * ============================================================================
 * File: modules/users/save_user.php
 * ============================================================================
 * Purpose:
 * --------
 * This script is responsible for inserting a new user or updating an existing
 * user in the `users` table. It is invoked via an AJAX request from
 * `modules/users/users.php`.
 *
 * Request (POST):
 * ---------------
 * - id            (optional) User ID for updates (omit for new users)
 * - full_name     (string) Full name of the user
 * - username      (string) Unique username for login
 * - email         (string) Unique email address
 * - password      (string) Plain-text password. If updating and left empty,
 *                  the password will NOT be changed.
 * - permissions   (20-char string with 'S' or 'N' for each permission)
 * - message       (optional) Additional user note or status message
 *
 * Behavior:
 * ---------
 * - When creating a new user:
 *     - Password is required.
 * - When updating an existing user:
 *     - If `password` is empty, the existing password remains unchanged.
 *     - If `password` is provided, it is hashed using bcrypt before saving.
 *
 * Notes:
 * ------
 * - The 20th character (index 19) of the permissions string determines whether
 *   the user is disabled ("S") or enabled ("N").
 * - Passwords are stored securely with bcrypt.
 *
 * Response (JSON):
 * ----------------
 * On success:
 *   { "success": true }
 *
 * On failure:
 *   { "success": false, "message": "Error description" }
 *
 * Error Handling:
 * ---------------
 * - Handles duplicate username or email with specific error messages.
 * - Logs errors using debug_log().
 *
 * Security:
 * ---------
 * - Access is restricted to users with permission index 4 (Users module).
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

header('Content-Type: application/json');

// -------------------- Includes --------------------
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// -------------------- Access Validation --------------------
// Permission 4 = Users module
validarAccesoModulo(4);

// -------------------- Validate Required Fields --------------------
$data = $_POST;

// Required fields (note: password can be empty during update)
$requiredFields = ['full_name', 'username', 'email', 'permissions'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        echo json_encode([
            'success' => false,
            'message' => "The field '$field' is required."
        ]);
        debug_log("Missing required field '$field' in save_user.php", "save_user");
        exit;
    }
}

// -------------------- Data Extraction --------------------
$id          = $data['id'] ?? null;
$fullName    = trim($data['full_name']);
$username    = trim($data['username']);
$email       = trim($data['email']);
$password    = trim($data['password'] ?? '');
$permissions = trim($data['permissions']);
$message     = trim($data['message'] ?? '');
$ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// If a password is provided and is not already bcrypt-hashed, hash it
if ($password !== '' && !preg_match('/^\$2y\$/', $password)) {
    $password = password_hash($password, PASSWORD_BCRYPT);
}

try {
    $pdo = getPDO();

    if ($id && is_numeric($id)) {
        // =====================================================================
        // Update Existing User
        // =====================================================================
        if ($password !== '') {
            // Update all fields including the password
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?, username = ?, email = ?, password = ?, permissions = ?, message = ?, ip = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $fullName, $username, $email, $password, $permissions, $message, $ip, $id
            ]);
        } else {
            // Update without touching the password
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?, username = ?, email = ?, permissions = ?, message = ?, ip = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $fullName, $username, $email, $permissions, $message, $ip, $id
            ]);
        }

        debug_log("User updated successfully (ID: $id)", "save_user");

    } else {
        // =====================================================================
        // Insert New User
        // =====================================================================
        if ($password === '') {
            echo json_encode([
                'success' => false,
                'message' => 'Password is required for new users.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (
                full_name, username, email, password, permissions, message, created_at, ip
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $fullName, $username, $email, $password, $permissions, $message, $ip
        ]);
        $newID = $pdo->lastInsertId();
        debug_log("New user inserted successfully (ID: $newID)", "save_user");
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Handle duplicate key error (MySQL error code 23000)
    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate') !== false) {
        // Check for duplicate email or username
        $msg = 'Duplicate value. ';
        if (strpos($e->getMessage(), 'email') !== false) {
            $msg = 'Another user already uses this email address.';
        } elseif (strpos($e->getMessage(), 'username') !== false) {
            $msg = 'Another user already uses this username.';
        }
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    error_log("Error in save_user.php: " . $e->getMessage());
    debug_log("Error while saving user: " . $e->getMessage(), "save_user");
    echo json_encode(['success' => false, 'message' => 'Database error while saving user.']);
} catch (Throwable $e) {
    error_log("Error in save_user.php: " . $e->getMessage());
    debug_log("Error while saving user: " . $e->getMessage(), "save_user");
    echo json_encode(['success' => false, 'message' => 'Unexpected error while saving user.']);
}
