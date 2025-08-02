<?php
/**
 * ============================================================================
 * File: modules/voice_profiles/save_voice_profile.php
 * ============================================================================
 * Purpose:
 * --------
 * This script handles the creation and update of **Voice Profiles** for the
 * Text-to-Speech system. A voice profile defines parameters such as:
 *
 * - Profile name
 * - OpenAI TTS model (e.g., gpt-4o-mini-tts)
 * - Selected voice (e.g., alloy, nova)
 * - Audio output format (mp3/wav/pcm)
 * - Style prompts, pitch and volume adjustments
 *
 * These profiles are used later when generating speech from text.
 *
 * Workflow:
 * ---------
 * 1. Validate that the current user has permission to access module 3
 *    (Voice Profiles).
 * 2. Validate that all required POST fields are provided:
 *      - name
 *      - model
 *      - voice
 * 3. Validate that the selected model and audio format are supported.
 * 4. If an `id` is provided, update the existing record.
 * 5. Otherwise, insert a new voice profile.
 * 6. Return a JSON response indicating success or failure.
 *
 * Request (POST):
 * ---------------
 * - id            (optional) Profile ID for update. If omitted, a new profile is created.
 * - name          (string) Profile name.
 * - model         (string) OpenAI TTS model. Must be one of:
 *                   - gpt-4o-mini-tts
 *                   - gpt-4o-tts
 * - voice         (string) Voice name.
 * - volume        (float, optional) Voice volume multiplier (default 1.0).
 * - pitch         (float, optional) Voice pitch multiplier (default 1.0).
 * - description   (string, optional) Free text description.
 * - style_prompt  (string, optional) Style instructions for TTS generation.
 * - audio_format  (string, optional) Output format: mp3, wav, or pcm.
 *
 * Response (JSON):
 * ----------------
 * - Success:
 *      { "success": true }
 * - Failure:
 *      { "success": false, "message": "Error message" }
 *
 * Permissions:
 * ------------
 * - Requires permission index 3 (Voice Profiles).
 *
 * Security Notes:
 * ---------------
 * - Direct database access is performed using prepared statements.
 * - Validations ensure only supported models and audio formats are stored.
 *
 * Dependencies:
 * -------------
 * - config/database.php
 * - models/login_model.php
 * - utils/helpers.php
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
// Access control: Permission 3 = Voice Profiles
// ---------------------------------------------------------------------------
validarAccesoModulo(3);

// ---------------------------------------------------------------------------
// Extract and validate POST data
// ---------------------------------------------------------------------------
$data = $_POST;

// Required fields
$requiredFields = ['name', 'model', 'voice'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        echo json_encode([
            'success' => false,
            'message' => "Field '$field' is required."
        ]);
        debug_log("Missing field $field", "voice_profiles");
        exit;
    }
}

// Normalize input
$id           = $data['id'] ?? null;
$name         = trim($data['name']);
$model        = trim($data['model']);
$voice        = trim($data['voice']);
$volume       = isset($data['volume']) ? floatval($data['volume']) : 1.0;
$pitch        = isset($data['pitch']) ? floatval($data['pitch']) : 1.0;
$description  = trim($data['description'] ?? '');
$style_prompt = trim($data['style_prompt'] ?? '');
$audio_format = trim($data['audio_format'] ?? 'mp3');

// ---------------------------------------------------------------------------
// Validate model and audio format
// ---------------------------------------------------------------------------

// Allowed models for OpenAI TTS
$allowedModels = ['gpt-4o-mini-tts', 'gpt-4o-tts'];
if (!in_array($model, $allowedModels, true)) {
    echo json_encode(['success' => false, 'message' => "Invalid model selected."]);
    debug_log("Invalid model '$model'", "voice_profiles");
    exit;
}

// Validate audio format
if (!in_array($audio_format, ['mp3', 'wav', 'pcm'])) {
    $audio_format = 'mp3';
}

try {
    $pdo = getPDO();

    // -----------------------------------------------------------------------
    // Update or Insert profile
    // -----------------------------------------------------------------------
    if ($id && is_numeric($id)) {
        // ========================= UPDATE =========================
        $stmt = $pdo->prepare("
            UPDATE voice_profiles
            SET name = ?, model = ?, voice = ?, volume = ?, pitch = ?, description = ?, style_prompt = ?, audio_format = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $model, $voice, $volume, $pitch, $description, $style_prompt, $audio_format, $id
        ]);
        debug_log("Voice profile updated (ID $id)", "voice_profiles");
    } else {
        // ========================= INSERT =========================
        $stmt = $pdo->prepare("
            INSERT INTO voice_profiles
            (name, model, voice, volume, pitch, description, style_prompt, audio_format)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $model, $voice, $volume, $pitch, $description, $style_prompt, $audio_format
        ]);
        $newId = $pdo->lastInsertId();
        debug_log("New voice profile inserted (ID $newId)", "voice_profiles");
    }

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    error_log("Error in save_voice_profile.php: " . $e->getMessage());
    debug_log("Error saving profile: " . $e->getMessage(), "voice_profiles");
    echo json_encode(['success' => false, 'message' => 'Error saving voice profile.']);
}
