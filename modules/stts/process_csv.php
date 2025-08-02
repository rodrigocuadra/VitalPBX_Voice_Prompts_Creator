<?php
/**
 * ============================================================================
 * File: modules/stts/process_csv.php
 * ============================================================================
 * Purpose:
 * --------
 * This endpoint processes a CSV file uploaded by the user from the
 * **Batch (CSV)** tab of the Text-to-Speech module (`tts.php`).
 *
 * It parses the CSV content and returns the rows as JSON for preview in the UI
 * before they are queued for background TTS processing.
 *
 * Request:
 * --------
 * Method: POST (multipart/form-data)
 *
 * Required:
 *   - csv_file : CSV file uploaded by the user
 *
 * Expected CSV Format:
 * --------------------
 * Each row must contain:
 *   column[0] = filename (can include subdirectories, e.g., `digits/hours`)
 *   column[1] = text     (text to convert into speech)
 *
 * Response (JSON):
 * ----------------
 * On success:
 * {
 *   "success": true,
 *   "rows": [
 *      { "filename": "digits/hours", "text": "The current hour is ..." },
 *      { "filename": "welcome",      "text": "Welcome to our service." }
 *   ]
 * }
 *
 * On error:
 * {
 *   "success": false,
 *   "message": "Error description"
 * }
 *
 * Behavior:
 * ---------
 * 1. Validates that the user has permission for module 2 (TTS).
 * 2. Validates that a file was uploaded and no errors occurred.
 * 3. Opens the uploaded CSV file and reads it line by line.
 * 4. For each line with at least two columns:
 *      - Column 0 = filename (trimmed)
 *      - Column 1 = text (trimmed)
 * 5. Returns an array of rows in JSON format.
 *
 * Important Notes:
 * ----------------
 * - This script does NOT generate audio. It only parses and returns the CSV
 *   content for preview on the frontend. The actual generation is triggered
 *   later when the user clicks "Process All".
 * - If `filename` contains subdirectories (e.g., `digits/hours`), those paths
 *   will be preserved when creating the output ZIP during final processing.
 *
 * Dependencies:
 * -------------
 * - models/login_model.php : for validarAccesoModulo() to ensure user permissions.
 * - utils/helpers.php      : for debug_log().
 *
 * Security:
 * ---------
 * - Requires permission 2 (TTS).
 * - Accepts only CSV files uploaded via POST.
 *
 * Author:
 * --------
 * VitalPBX Team
 * ============================================================================
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// ---------------------------------------------------------------------------
// Check access permissions for module 2 (TTS)
// ---------------------------------------------------------------------------
validarAccesoModulo(2);

// ---------------------------------------------------------------------------
// Validate file upload
// ---------------------------------------------------------------------------
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

// ---------------------------------------------------------------------------
// Parse CSV file
// ---------------------------------------------------------------------------
$rows = [];
if (($handle = fopen($_FILES['csv_file']['tmp_name'], 'r')) !== false) {
    while (($data = fgetcsv($handle)) !== false) {
        // Each valid row must have at least 2 columns
        if (count($data) >= 2) {
            $rows[] = [
                'filename' => trim($data[0]),  // May include subdirectories
                'text'     => trim($data[1])
            ];
        }
    }
    fclose($handle);
}

// ---------------------------------------------------------------------------
// Return parsed data as JSON
// ---------------------------------------------------------------------------
echo json_encode(['success' => true, 'rows' => $rows]);
