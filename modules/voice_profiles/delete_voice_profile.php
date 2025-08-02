<?php
/**
 * ============================================================================
 * File: modules/voice_profiles/delete_voice_profile.php
 * ============================================================================
 * Purpose:
 * --------
 * This script deletes a voice profile record from the `voice_profiles` table.
 * It is typically called via an AJAX request when an administrator chooses to
 * remove a voice profile from the system.
 *
 * Workflow:
 * ---------
 * 1. Validate that the user has permission to access the Voice Profiles module
 *    (permission index = 3).
 * 2. Validate the `id` parameter received via GET (must be numeric).
 * 3. Check if the voice profile with the given ID exists in the database.
 * 4. If it exists, delete the profile from the table.
 * 5. Return a JSON response indicating success or failure.
 *
 * Request:
 * --------
 * Method: GET
 * Parameters:
 *   - id (numeric): The ID of the voice profile to be deleted.
 *
 * Response:
 * ---------
 * JSON structure:
 *   On success:
 *      { "success": true }
 *
 *   On failure:
 *      { "success": false, "message": "Description of the error" }
 *
 * Permissions:
 * ------------
 * - Requires module permission index 3 ("Voice Profiles").
 *
 * Security Notes:
 * ---------------
 * - Access to this script is restricted by `validarAccesoModulo(3)`.
 * - Only valid numeric IDs are processed.
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
// Access Control: Permission 3 = Voice Profiles module
// ---------------------------------------------------------------------------
validarAccesoModulo(3);

// ---------------------------------------------------------------------------
// Input Validation
// ---------------------------------------------------------------------------
$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing profile ID.'
    ]);
    debug_log("Invalid ID received in delete_voice_profile.php: $id", "delete_voice_profile");
    exit;
}

try {
    $pdo = getPDO();

    // -----------------------------------------------------------------------
    // Step 1: Check if the profile exists
    // -----------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT id FROM voice_profiles WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Voice profile not found.'
        ]);
        exit;
    }

    // -----------------------------------------------------------------------
    // Step 2: Delete the profile
    // -----------------------------------------------------------------------
    $stmt = $pdo->prepare("DELETE FROM voice_profiles WHERE id = ?");
    $stmt->execute([$id]);

    debug_log("Voice profile deleted successfully (ID: $id)", "delete_voice_profile");
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    // -----------------------------------------------------------------------
    // Error Handling
    // -----------------------------------------------------------------------
    error_log("Error in delete_voice_profile.php: " . $e->getMessage());
    debug_log("Error deleting voice profile ID $id: " . $e->getMessage(), "delete_voice_profile");
    echo json_encode([
        'success' => false,
        'message' => 'Error while deleting voice profile.'
    ]);
}
