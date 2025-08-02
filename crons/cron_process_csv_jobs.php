<?php
/**
 * ============================================================================
 * File: crons/cron_process_csv_jobs.php
 * ============================================================================
 * Purpose:
 * --------
 * This cron script processes queued Text-to-Speech (TTS) batch jobs that were
 * uploaded via CSV files. For each queued job, it:
 *
 *  1. Loads the queued jobs from `jobs/tts_queue.json`.
 *  2. Retrieves the corresponding voice profile from the database.
 *  3. Iterates through each row in the job (filename + text).
 *  4. Calls the OpenAI TTS API to generate audio for the text.
 *  5. Saves each generated audio file to the output folder while preserving
 *     subdirectories from the CSV filename (e.g., "digits/hours").
 *  6. Compresses all generated files into a `.zip` file.
 *  7. Marks the job as "done" and updates the queue file.
 *  8. Optionally, logs or notifies the user (email notification can be added).
 *
 * Expected Cron Configuration:
 * ----------------------------
 * Example cron entry (runs every minute):
 *   * * * * * /usr/bin/php /path/to/your/app/crons/cron_process_csv_jobs.php >/dev/null 2>&1
 *
 * Input:
 * ------
 * - `jobs/tts_queue.json` (JSON file containing queued jobs)
 *   Each job structure:
 *   {
 *      "id": "unique_job_id",
 *      "profile": 3,
 *      "rows": [
 *          {"filename": "digits/hours", "text": "Hours"},
 *          {"filename": "digits/minutes", "text": "Minutes"}
 *      ],
 *      "status": "queued",
 *      "email": "notify@example.com"
 *   }
 *
 * Output:
 * -------
 * - Audio files generated in `jobs/output/{jobId}/`.
 * - A ZIP file containing all generated files: `jobs/output/{jobId}.zip`.
 * - Updated queue file: `jobs/tts_queue.json` (status changed to "done").
 *
 * Security Notes:
 * ---------------
 * - This script must be run only from CLI/cron, not via a public web endpoint.
 * - The script uses OpenAI API keys stored securely in `config/openai.php`.
 *
 * Dependencies:
 * -------------
 * - PHP extensions: PDO, cURL, ZipArchive
 * - Config files: config/database.php, config/openai.php
 *
 * @package VoiceApp\Cron
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

// Enable error display for cron debugging (optional)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Required application configuration and helper files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/openai.php';
require_once __DIR__ . '/../utils/helpers.php';

// ---------------------------------------------------------------------------
// Paths and queue setup
// ---------------------------------------------------------------------------
$jobsDir     = __DIR__ . '/../jobs';
$queueFile   = $jobsDir . '/tts_queue.json';
$outputBase  = __DIR__ . '/../jobs/output';

// If no queue file exists, nothing to process
if (!file_exists($queueFile)) {
    exit; // Nothing to process
}

// Load jobs queue
$queue = json_decode(file_get_contents($queueFile), true) ?: [];
$updatedQueue = [];

// ---------------------------------------------------------------------------
// Process each job in the queue
// ---------------------------------------------------------------------------
foreach ($queue as $job) {
    if ($job['status'] !== 'queued') {
        // Keep jobs that are already processed or in progress
        $updatedQueue[] = $job;
        continue;
    }

    $jobId     = $job['id'];
    $profileId = $job['profile'];
    $rows      = $job['rows'];

    debug_log("Processing TTS job $jobId", "cron_tts");

    try {
        // -------------------------------------------------------------------
        // Fetch voice profile from database
        // -------------------------------------------------------------------
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM voice_profiles WHERE id = ?");
        $stmt->execute([$profileId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            debug_log("Voice profile $profileId not found", "cron_tts");
            continue;
        }

        // -------------------------------------------------------------------
        // Prepare output directory for this job
        // -------------------------------------------------------------------
        $outputDir = $outputBase . '/' . $jobId;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // -------------------------------------------------------------------
        // Process each row (filename + text)
        // -------------------------------------------------------------------
        foreach ($rows as $row) {
            // Build relative path (can contain subdirectories like "digits/hours")
            $relativePath = $row['filename'];
            $fullPath = $outputDir . '/' . $relativePath . '.' . $profile['audio_format'];

            // Ensure the directory structure exists
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0777, true);
            }

            $text = $row['text'];

            // -------------------------------------------------------------------
            // Call OpenAI TTS API
            // -------------------------------------------------------------------
            $url = "https://api.openai.com/v1/audio/speech";
            $headers = [
                "Authorization: Bearer " . $GLOBALS['OPENAI_API_KEY'],
                "Content-Type: application/json"
            ];

            $payload = json_encode([
                "model"  => $profile['model'],
                "voice"  => $profile['voice'],
                "input"  => $text,
                "format" => $profile['audio_format']
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            $audioData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Save the generated audio file if successful
            if ($httpCode === 200 && substr($audioData, 0, 1) !== '{') {
                file_put_contents($fullPath, $audioData);
            } else {
                debug_log("Error generating audio for $relativePath: HTTP $httpCode", "cron_tts");
            }
        }

        // -------------------------------------------------------------------
        // Compress the generated files into a ZIP
        // -------------------------------------------------------------------
        $zipPath = $outputDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($outputDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $filePath  = (string)$file;
                $localName = substr($filePath, strlen($outputDir) + 1);
                $zip->addFile($filePath, $localName);
            }
            $zip->close();
        }

        // -------------------------------------------------------------------
        // Mark the job as done and store the zip path
        // -------------------------------------------------------------------
        $job['status'] = 'done';
        $job['zip']    = 'jobs/output/' . $jobId . '.zip';

        // -------------------------------------------------------------------
        // Optional: send notification email (not implemented)
        // -------------------------------------------------------------------
        if (!empty($job['email'])) {
            $downloadUrl = (isset($_SERVER['HTTP_HOST'])
                ? 'https://' . $_SERVER['HTTP_HOST'] . '/'
                : '') . $job['zip'];

            // Example: send_email($job['email'], "Your TTS job is ready", "Download: $downloadUrl");
            debug_log("Job $jobId completed. ZIP available at: $downloadUrl", "cron_tts");
        }

    } catch (Throwable $e) {
        debug_log("Error processing job $jobId: " . $e->getMessage(), "cron_tts");
    }

    // Add job back to the queue (processed)
    $updatedQueue[] = $job;
}

// ---------------------------------------------------------------------------
// Save updated queue back to the file
// ---------------------------------------------------------------------------
file_put_contents($queueFile, json_encode($updatedQueue, JSON_PRETTY_PRINT));
