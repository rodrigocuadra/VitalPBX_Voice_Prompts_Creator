<?php
/**
 * ============================================================================
 * File: modules/voice_profiles/get_voices.php
 * ============================================================================
 * Purpose:
 * --------
 * This script retrieves a list of available OpenAI voice names stored in the
 * database table `openai_voices` and returns them in JSON format.
 *
 * This list is typically used to populate dropdown menus when creating or
 * editing a voice profile in the **Voice Profiles** module.
 *
 * Workflow:
 * ---------
 * 1. Validate that the current user has permission to access the Voice Profiles
 *    module (permission index = 3).
 * 2. Query the `openai_voices` table for all available voice names.
 * 3. Return the list as a JSON array.
 *
 * Request:
 * --------
 * Method: GET
 * Parameters: None
 *
 * Response:
 * ---------
 * JSON structure:
 *   On success:
 *      {
 *        "success": true,
 *        "data": ["alloy", "ash", "coral", "nova", ...]
 *      }
 *
 *   On failure:
 *      { "success": false, "message": "Error retrieving voices list." }
 *
 * Permissions:
 * ------------
 * - Requires module permission index 3 ("Voice Profiles").
 *
 * Security Notes:
 * ---------------
 * - No sensitive information is returned.
 * - Only the `name` column of the `openai_voices` table is exposed.
 *
 * Dependencies:
 * -------------
 * - config/database.php
 * - models/login_model.php (for validarAccesoModulo)
 * - utils/helpers.php (for debug_log)
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
// Access Control: Permission 3 = Voice Profiles
// ---------------------------------------------------------------------------
validarAccesoModulo(3);

try {
    // -----------------------------------------------------------------------
    // Query the available OpenAI voices
    // -----------------------------------------------------------------------
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT name FROM openai_voices ORDER BY name ASC");
    $voices = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // -----------------------------------------------------------------------
    // Return list of voices
    // -----------------------------------------------------------------------
    echo json_encode([
        'success' => true,
        'data' => $voices
    ]);

} catch (Throwable $e) {
    // -----------------------------------------------------------------------
    // Error Handling
    // -----------------------------------------------------------------------
    error_log("Error in get_voices.php: " . $e->getMessage());
    debug_log("Error loading voices: " . $e->getMessage(), "get_voices");

    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving voices list.'
    ]);
}
