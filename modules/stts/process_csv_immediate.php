<?php
// ========================================================================
// File: modules/stts/process_csv_immediate.php
// Description:
//   Initializes a real-time CSV audio generation job and prepares
//   a directory structure to store each generated file.
//
// Process:
//   1. Receives a JSON payload with `profile` and `rows`.
//   2. Creates a unique job folder under jobs/realtime/.
//   3. Returns a job_id to the client for real-time upload of files
//      (handled by upload_audio.php) and later ZIP creation.
//
// Request (POST JSON):
//   {
//     "profile": "123",
//     "rows": [
//       { "filename": "digits/1", "text": "One" },
//       { "filename": "digits/2", "text": "Two" }
//     ]
//   }
//
// Response (JSON):
//   { "success": true, "job_id": "realtime_1725288888888" }
//
// Author: VitalPBX Team
// ========================================================================

header('Content-Type: application/json');

// Read input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || empty($data['profile']) || empty($data['rows'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Generate a unique job ID
$jobId = 'realtime_' . time() . rand(1000, 9999);
$jobDir = __DIR__ . '/../../jobs/realtime/' . $jobId;

if (!is_dir($jobDir)) {
    mkdir($jobDir, 0777, true);
}

// Store job metadata (optional for debugging)
file_put_contents($jobDir . '/metadata.json', json_encode($data, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'job_id' => $jobId
]);
