<?php
/**
 * ============================================================================
 * File: modules/stts/generate_tts.php
 * ============================================================================
 * Purpose:
 * --------
 * This endpoint generates speech audio from text using the OpenAI TTS API.
 * It is typically called via an AJAX POST request from the **Text-to-Speech**
 * module (`tts.php`) for:
 *   - Real-time preview of generated audio.
 *   - Batch generation of audio (CSV preview and final processing).
 *
 * Input Parameters (POST):
 * ------------------------
 * Required:
 *   - voice_profile_id : (int) ID of the voice profile to use.
 *   - text             : (string) Text to convert to speech.
 *
 * Optional (overrides, provided by form `tts.php`):
 *   - param_model        : Override voice model (e.g., gpt-4o-mini-tts)
 *   - param_voice        : Override voice name (e.g., shimmer)
 *   - param_volume       : Override playback volume (currently unused by API)
 *   - param_pitch        : Override pitch (currently unused by API)
 *   - param_style_prompt : Style/prompt for voice guidance
 *   - param_audio_format : Output format (mp3, wav, pcm)
 *
 * Process:
 * --------
 * 1. Validate required POST parameters.
 * 2. Fetch the specified voice profile from the `voice_profiles` table.
 * 3. Apply overrides from POST (if provided).
 * 4. Make an HTTP POST request to the OpenAI `/v1/audio/speech` API endpoint.
 * 5. Return the generated audio binary stream to the client.
 *
 * Output:
 * -------
 * - On success: Returns raw audio stream with MIME type:
 *      * `audio/mpeg` for mp3
 *      * `audio/wav` for wav
 *      * `audio/L16` for pcm
 * - On error: Returns JSON or plain text error message with an appropriate
 *   HTTP status code.
 *
 * Dependencies:
 * -------------
 * - config/database.php  : Provides getPDO() for DB access.
 * - config/openai.php    : Provides global $OPENAI_API_KEY.
 * - utils/helpers.php    : Provides debug_log().
 *
 * OpenAI API:
 * -----------
 * Endpoint: POST https://api.openai.com/v1/audio/speech
 * Payload:
 *   {
 *     "model": "gpt-4o-mini-tts",
 *     "voice": "shimmer",
 *     "input": "text to synthesize",
 *     "format": "mp3"
 *   }
 *
 * Error Handling:
 * ---------------
 * - Missing required parameters (HTTP 400).
 * - Invalid profile ID (HTTP 400).
 * - Missing API key (HTTP 500).
 * - API error response (returns JSON error body from OpenAI).
 *
 * Author:
 * --------
 * VitalPBX Team
 * ============================================================================
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/openai.php';
require_once __DIR__ . '/../../utils/helpers.php';

global $OPENAI_API_KEY;

/**
 * Deletes files older than a specified number of hours from a directory.
 *
 * @param string $dir        The directory path to clean.
 * @param int    $ttlHours   The time-to-live in hours.
 */
function cleanOldFiles(string $dir, int $ttlHours = 24): void {
    if (!is_dir($dir)) return;

    $ttlSeconds = $ttlHours * 3600;
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $ttlSeconds) {
            @unlink($file);
        }
    }
}

// ---------------------------------------------------------------------------
// Optional: Clean up old files before generating new audio
// ---------------------------------------------------------------------------
$generatedDir = __DIR__ . '/../../jobs'; 
if (is_dir($generatedDir)) {
    cleanOldFiles($generatedDir, 24); 
}

// ---------------------------------------------------------------------------
// Validate OpenAI API Key
// ---------------------------------------------------------------------------
if (empty($OPENAI_API_KEY)) {
    http_response_code(500);
    die("API key is missing. Check config/openai.php");
}

// ---------------------------------------------------------------------------
// Extract POST parameters
// ---------------------------------------------------------------------------
$voiceProfileId = $_POST['voice_profile_id'] ?? '';
$text = trim($_POST['text'] ?? '');

if (!$voiceProfileId || !$text) {
    http_response_code(400);
    echo "Missing required parameters.";
    exit;
}

try {
    // -----------------------------------------------------------------------
    // Fetch voice profile from DB
    // -----------------------------------------------------------------------
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM voice_profiles WHERE id = ?");
    $stmt->execute([$voiceProfileId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        http_response_code(400);
        echo "Invalid voice profile.";
        exit;
    }

    // -----------------------------------------------------------------------
    // Apply parameter overrides (temporary, do not persist in DB)
    // -----------------------------------------------------------------------
    $model        = $_POST['param_model']        ?? $profile['model'];
    $voice        = $_POST['param_voice']        ?? $profile['voice'];
    $volume       = $_POST['param_volume']       ?? $profile['volume'];
    $pitch        = $_POST['param_pitch']        ?? $profile['pitch'];
    $style        = $_POST['param_style_prompt'] ?? $profile['style_prompt'];
    $audioFormat  = $_POST['param_audio_format'] ?? $profile['audio_format'] ?? 'mp3';

    debug_log("TTS generation using model=$model, voice=$voice, format=$audioFormat", "tts_module");

    // -----------------------------------------------------------------------
    // Prepare request to OpenAI TTS API
    // -----------------------------------------------------------------------
    $url = "https://api.openai.com/v1/audio/speech";
    $headers = [
        "Authorization: Bearer " . $OPENAI_API_KEY,
        "Content-Type: application/json"
    ];

    $payload = json_encode([
        "model"  => $model,
        "voice"  => $voice,
        "input"  => $text,
        "format" => $audioFormat
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // -----------------------------------------------------------------------
    // Handle API error responses
    // -----------------------------------------------------------------------
    if ($httpCode !== 200) {
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    // If the response is JSON (starts with '{'), treat it as an error
    if (substr($response, 0, 1) === '{') {
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    // -----------------------------------------------------------------------
    // Return audio to the browser
    // -----------------------------------------------------------------------
    $mime = ($audioFormat === 'wav') ? 'audio/wav' :
            (($audioFormat === 'pcm') ? 'audio/L16' : 'audio/mpeg');

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="tts.' . $audioFormat . '"');
    echo $response;

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Error generating TTS: " . $e->getMessage());
    debug_log("Error generating TTS: " . $e->getMessage(), "tts_module");
    echo "Error generating audio.";
}
