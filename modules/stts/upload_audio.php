<?php
/**
 * ============================================================================
 * File: modules/stts/upload_audio.php
 * ============================================================================
 * Purpose:
 * --------
 * Handles the upload of generated TTS audio files from the client during
 * real-time batch processing.
 *
 * Behavior:
 * ---------
 * - Receives an audio file (MP3/WAV/PCM) via POST (multipart/form-data).
 * - Receives a `filename` parameter which may include subfolders, e.g.:
 *       digits/12
 *   This will be saved as:
 *       jobs/tts_realtime/digits/12.mp3
 * - Creates any required subfolders.
 * - Returns a JSON response with the final path to the file, which will later
 *   be used to generate a ZIP containing all files.
 *
 * Request (POST):
 * ---------------
 * - filename  : string (e.g. "digits/12")
 * - audio     : file blob (uploaded file)
 *
 * Response (JSON):
 * ----------------
 * {
 *   "success": true,
 *   "file": "jobs/tts_realtime/digits/12.mp3"
 * }
 *
 * Error cases return:
 * {
 *   "success": false,
 *   "message": "Error message"
 * }
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

header('Content-Type: application/json');

// Validate inputs
if (empty($_POST['filename']) || !isset($_FILES['audio'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing filename or audio file.'
    ]);
    exit;
}

$filename = trim($_POST['filename']);
$audioFile = $_FILES['audio'];

// Base directory where files will be stored
$baseDir = __DIR__ . '/../../jobs/tts_realtime';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

// Create subfolders if `filename` contains slashes
$targetPath = $baseDir . '/' . $filename;
$targetDir = dirname($targetPath);
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// Get the extension from the uploaded file
$ext = pathinfo($audioFile['name'], PATHINFO_EXTENSION);
$fullPath = $targetPath . '.' . $ext;

// Move the uploaded file to its destination
if (!move_uploaded_file($audioFile['tmp_name'], $fullPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save file on server.'
    ]);
    exit;
}

// Return success response
$relativePath = 'jobs/tts_realtime/' . $filename . '.' . $ext;

echo json_encode([
    'success' => true,
    'file' => $relativePath
]);

