<?php
/**
 * ============================================================================
 * File: modules/stts/queue_csv_job.php
 * ============================================================================
 * Purpose:
 * --------
 * This endpoint receives a JSON payload from the **Batch (CSV)** tab in
 * `tts.php`, which contains:
 *   - The selected voice profile ID.
 *   - A list of parsed rows from the uploaded CSV file.
 *
 * It appends a new job to the **TTS queue file** (`jobs/tts_queue.json`)
 * to be processed asynchronously by the cron script `crons/cron_process_csv_jobs.php`.
 *
 * This script does NOT generate audio immediately. Instead, it:
 *   1. Validates the request.
 *   2. Adds a job entry into the queue.
 *   3. Returns a JSON response with the generated `job_id`.
 *
 * Request (POST):
 * ---------------
 * Content-Type: application/json
 *
 * JSON Body:
 * {
 *   "profile": "3",               // Voice profile ID
 *   "rows": [
 *      { "filename": "digits/hours", "text": "The current hour is ..." },
 *      { "filename": "welcome",      "text": "Welcome to our service." }
 *   ]
 * }
 *
 * Response (JSON):
 * ----------------
 * On success:
 * {
 *   "success": true,
 *   "message": "Job queued",
 *   "job_id": "job_64f0c5a5c8b12.12345678"
 * }
 *
 * On failure:
 * {
 *   "success": false,
 *   "message": "Error description"
 * }
 *
 * Queue File (jobs/tts_queue.json):
 * ---------------------------------
 * The job is stored in a JSON file as an array of objects:
 * [
 *   {
 *     "id": "job_64f0c5a5c8b12.12345678",
 *     "profile": "3",
 *     "rows": [ ... ],
 *     "email": "admin@example.com",
 *     "status": "queued",
 *     "created_at": "2025-07-31 15:45:00"
 *   }
 * ]
 *
 * Background Processing:
 * ----------------------
 * The cron script (`crons/cron_process_csv_jobs.php`) periodically reads this
 * queue file, processes queued jobs, generates TTS audio files, packages them
 * into a ZIP file, and updates the job status to `done`.
 *
 * Email Notification:
 * -------------------
 * If the logged-in user has an email in `$_SESSION['userdata']['Email']`, that
 * email is saved in the job. After processing, the cron script can optionally
 * notify the user with a download link.
 *
 * Dependencies:
 * -------------
 * - config/database.php: Initializes database (not used here but kept for consistency).
 * - models/login_model.php: Provides validarAccesoModulo() for permission validation.
 * - utils/helpers.php: Provides debug_log().
 *
 * Security:
 * ---------
 * - Requires permission 2 (TTS).
 * - Input is strictly validated.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// ---------------------------------------------------------------------------
// Check access permissions for module 2 (TTS)
// ---------------------------------------------------------------------------
validarAccesoModulo(2);

// ---------------------------------------------------------------------------
// Decode JSON input
// ---------------------------------------------------------------------------
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['profile']) || empty($data['rows'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// ---------------------------------------------------------------------------
// Ensure jobs directory exists
// ---------------------------------------------------------------------------
$jobsDir = __DIR__ . '/../../jobs';
if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0777, true);
}

$queueFile = $jobsDir . '/tts_queue.json';

// ---------------------------------------------------------------------------
// Load current queue (if file exists)
// ---------------------------------------------------------------------------
$queue = [];
if (file_exists($queueFile)) {
    $queue = json_decode(file_get_contents($queueFile), true) ?: [];
}

// ---------------------------------------------------------------------------
// Create a unique job ID and append job to the queue
// ---------------------------------------------------------------------------
$jobId = uniqid('job_', true);
$queue[] = [
    'id'         => $jobId,
    'profile'    => $data['profile'],
    'rows'       => $data['rows'],
    'email'      => $_SESSION['userdata']['Email'] ?? '', // user email for notifications
    'status'     => 'queued',
    'created_at' => date('Y-m-d H:i:s')
];

// Save the updated queue to file
file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));

// ---------------------------------------------------------------------------
// Send success response
// ---------------------------------------------------------------------------
echo json_encode([
    'success' => true,
    'message' => 'Job queued',
    'job_id'  => $jobId
]);
