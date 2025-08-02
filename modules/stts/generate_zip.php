<?php
/**
 * ============================================================================
 * File: modules/stts/generate_zip.php
 * ============================================================================
 * Purpose:
 * --------
 * Creates a ZIP file containing all audio files generated during the
 * real-time batch TTS process.
 *
 * Behavior:
 * ---------
 * - Receives an array of file paths (relative to the project root).
 * - Validates each file exists on disk.
 * - Creates a single ZIP file in `jobs/tts_realtime/exports/`.
 * - Returns the URL to download the ZIP file.
 *
 * Request (POST JSON):
 * --------------------
 * {
 *   "files": [
 *     "jobs/tts_realtime/digits/12.mp3",
 *     "jobs/tts_realtime/welcome.mp3"
 *   ]
 * }
 *
 * Response (JSON):
 * ----------------
 * {
 *   "success": true,
 *   "zip": "jobs/tts_realtime/exports/tts_batch_20250802_153000.zip"
 * }
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['files']) || !is_array($input['files'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request: missing files list.'
    ]);
    exit;
}

$files = $input['files'];

// Destination directory for ZIP files
$exportDir = __DIR__ . '/../../jobs/tts_realtime/exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

// Generate a unique ZIP file name
$zipFilename = 'tts_batch_' . date('Ymd_His') . '.zip';
$zipPath = $exportDir . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to create ZIP file.'
    ]);
    exit;
}

// Add each file to the ZIP
foreach ($files as $relativeFile) {
    $absoluteFile = __DIR__ . '/../../' . $relativeFile;
    if (file_exists($absoluteFile)) {
        // Preserve the relative path inside the zip (remove jobs/tts_realtime/)
        $localName = str_replace('jobs/tts_realtime/', '', $relativeFile);
        $zip->addFile($absoluteFile, $localName);
    }
}

$zip->close();

// Return the relative path to the ZIP
$relativeZip = 'jobs/tts_realtime/exports/' . $zipFilename;

echo json_encode([
    'success' => true,
    'zip' => $relativeZip
]);
