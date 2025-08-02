<?php
/**
 * ============================================================================
 * File: modules/users/get_user.php
 * ============================================================================
 * Purpose:
 * --------
 * This endpoint retrieves the details of a specific user from the database
 * based on the provided user ID and returns the information as JSON.
 * 
 * It is primarily used by the Users module (in the admin panel) to populate
 * the edit form when an administrator selects a user.
 *
 * Request (GET):
 * --------------
 * - id (numeric): The ID of the user to retrieve.
 *
 * Response (JSON):
 * ----------------
 * {
 *   "success": true,
 *   "data": {
 *      "id": 1,
 *      "full_name": "John Doe",
 *      "username": "jdoe",
 *      "email": "john@example.com",
 *      "permissions": "SSNNNN...",
 *      ...
 *   }
 * }
 * OR
 * {
 *   "success": false,
 *   "message": "Error message"
 * }
 *
 * Security Notes:
 * ---------------
 * - The `password` field is NEVER returned in the JSON response to prevent
 *   leaking hashed passwords to the frontend.
 * - Access to this script requires permission index 4 (Users module).
 *
 * Other Notes:
 * ------------
 * - The permissions string contains 20 characters. The 20th character
 *   (index 19) indicates whether the user is disabled ('S') or enabled ('N').
 * - This script uses prepared statements to prevent SQL injection.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

// Force JSON output
header('Content-Type: application/json');

// -------------------- Includes --------------------
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// -------------------- Access Validation --------------------
// Permission 4 = Users module
validarAccesoModulo(4);

// -------------------- Input Validation --------------------
$id = $_GET['id'] ?? '';

if (!$id || !is_numeric($id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing user ID.'
    ]);
    debug_log("Invalid ID received in get_user.php: $id", "get_user");
    exit;
}

try {
    $pdo = getPDO();

    // Query the user record by ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // SECURITY: Never return the password hash to the frontend
        if (isset($user['password'])) {
            $user['password'] = '';
        }

        debug_log("User data for ID $id retrieved successfully", "get_user");

        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
    } else {
        debug_log("User ID $id not found", "get_user");
        echo json_encode([
            'success' => false,
            'message' => 'User not found.'
        ]);
    }

} catch (Throwable $e) {
    error_log("Error in get_user.php: " . $e->getMessage());
    debug_log("Error retrieving user data for ID $id: " . $e->getMessage(), "get_user");

    echo json_encode([
        'success' => false,
        'message' => 'Database error while retrieving user.'
    ]);
}
