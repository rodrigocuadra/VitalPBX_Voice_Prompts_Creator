<?php
/**
 * ============================================================================
 * File: config/openai.php
 * ============================================================================
 * Purpose:
 * --------
 * Central configuration and helper functions for accessing the OpenAI API.
 * This file defines:
 *   - The global API key variable ($OPENAI_API_KEY)
 *   - The OpenAI API base URL
 *   - A reusable function (openai_request) for making HTTP requests to OpenAI
 *
 * Key Features:
 * -------------
 * 1. Securely loads the API key from an environment variable (OPENAI_API_KEY).
 * 2. Provides a simple interface to call OpenAI endpoints using cURL.
 * 3. Supports both GET and POST requests.
 * 4. Handles JSON request/response encoding/decoding automatically.
 * 5. Logs errors if requests fail, without exposing sensitive details.
 *
 * Usage:
 * ------
 * Example:
 *   require_once __DIR__ . '/../config/openai.php';
 *
 *   $result = openai_request('models');
 *   if ($result) {
 *       print_r($result);
 *   } else {
 *       echo "Failed to contact OpenAI API.";
 *   }
 *
 * Security Notes:
 * ---------------
 * - The API key must be set as an environment variable:
 *       export OPENAI_API_KEY="your-secret-key"
 * - Do not hardcode keys in code or commit them to version control.
 * - Error messages are logged internally (PHP error log).
 *
 * Dependencies:
 * -------------
 * - PHP cURL extension
 *
 * @package VoiceApp\Config
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

// ============================================================================
// Load the OpenAI API key from an environment variable
// ============================================================================
// IMPORTANT: Make sure the hosting environment has OPENAI_API_KEY defined.
// You can set it in .htaccess, .env, or your hosting control panel.
$OPENAI_API_KEY = getenv('OPENAI_API_KEY');

// ============================================================================
// Base URL for all OpenAI API requests
// ============================================================================
define('OPENAI_API_BASE', 'https://api.openai.com/v1');

/**
 * Send a request to the OpenAI API.
 *
 * This function wraps the complexity of making HTTP requests to OpenAI.
 * It supports GET and POST methods and can send/receive JSON data.
 *
 * @param string $endpoint  The endpoint path after "/v1" (e.g., "models" or "chat/completions").
 * @param array  $payload   Optional data to send (ignored for GET).
 * @param string $method    HTTP method ("GET" or "POST").
 * @param bool   $asJson    If true, sends payload as JSON (default).
 *
 * @return array|null Returns a decoded JSON response as an associative array
 *                    on success, or null if the request fails.
 */
function openai_request(string $endpoint, array $payload = [], string $method = 'GET', bool $asJson = true): ?array
{
    global $OPENAI_API_KEY;

    // ------------------------------------------------------------------------
    // Validate API key
    // ------------------------------------------------------------------------
    if (!$OPENAI_API_KEY) {
        error_log('OpenAI API key is not set. Ensure OPENAI_API_KEY environment variable is defined.');
        return null;
    }

    // Build the full URL for the request
    $url = OPENAI_API_BASE . '/' . ltrim($endpoint, '/');

    // Prepare headers, including authorization
    $headers = [
        "Authorization: Bearer $OPENAI_API_KEY"
    ];

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Configure request for POST
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);

        if ($asJson) {
            // Send JSON payload
            $headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } else {
            // Send payload as form data
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    }

    // Attach headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the request
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ------------------------------------------------------------------------
    // Handle the response
    // ------------------------------------------------------------------------
    if ($status >= 200 && $status < 300) {
        // Decode JSON response
        return json_decode($response, true);
    } else {
        // Log the error for debugging purposes
        error_log("OpenAI API request failed ($status): $response");
        return null;
    }
}
