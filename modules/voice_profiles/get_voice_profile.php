<?php
/**
 * ============================================================================
 * File: modules/voice_profiles/get_voice_profile.php
 * ============================================================================
 * Purpose:
 * --------
 * This script retrieves a single voice profile record from the `voice_profiles`
 * table by its ID and returns the data in JSON format.
 *
 * It is typically called via AJAX when loading or editing a voice profile
 * in the UI (e.g., on the Text-to-Speech page).
 *
 * Workflow:
 * ---------
 * 1. Validate that the user has permission to access the Voice Profiles module
 *    (permission index = 3).
 * 2. Validate the `id` parameter received via GET (must be numeric).
 * 3. Fetch the profile details from the database.
 * 4. Validate the `model` field to ensure it is one of the allowed models.
 *    - If invalid, normalize the model to "gpt-4o-mini-tts".
 * 5. Return the profile data as a JSON response.
 *
 * Request:
 * --------
 * Method: GET
 * Parameters:
 *   - id (numeric): The ID of the voice profile to be retrieved.
 *
 * Response:
 * ---------
 * JSON structure:
 *   On success:
 *      {
 *        "success": true,
 *        "data": {
 *           "id": 1,
 *           "name": "Profile Name",
 *           "model": "gpt-4o-mini-tts",
 *           "voice": "alloy",
 *           "volume": 1.0,
 *           "pitch": 1.0,
 *           "style_prompt": "",
 *           "audio_format": "mp3"
 *        }
 *      }
 *
 *   On failure:
 *      { "success": false, "message": "Error description" }
 *
 * Permissions:
 * ------------
 * - Requires module permission index 3 ("Voice Profiles").
 *
 * Security Notes:
 * ---------------
 * - The `id` parameter must be validated and sanitized before querying.
 * - Returns only profile data, never sensitive user/session data.
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
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    debug_log("Invalid id for get_voice_profile: $id", "voice_profiles");
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM voice_profiles WHERE id = ?");
    $stmt->execute([$id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
        // -------------------------------------------------------------------
        // Validate the model field
        // -------------------------------------------------------------------
        $allowedModels = ['gpt-4o-mini-tts', 'gpt-4o-tts'];

        // Normalize model if it is not in the allowed list
        if (!in_array($profile['model'], $allowedModels, true)) {
            debug_log("Normalizing invalid model '{$profile['model']}' for profile ID $id", "voice_profiles");
            $profile['model'] = 'gpt-4o-mini-tts';
        }

        // -------------------------------------------------------------------
        // Return profile data
        // -------------------------------------------------------------------
        echo json_encode(['success' => true, 'data' => $profile]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }

} catch (Throwable $e) {
    // -----------------------------------------------------------------------
    // Error Handling
    // -----------------------------------------------------------------------
    error_log("Error get_voice_profile.php: " . $e->getMessage());
    debug_log("Error getting voice profile $id: " . $e->getMessage(), "voice_profiles");
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
