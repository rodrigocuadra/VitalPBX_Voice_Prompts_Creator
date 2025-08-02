<?php
/**
 * ============================================================================
 * File: index.php
 * ============================================================================
 * Purpose:
 * --------
 * Main entry point for the **Text-to-Speech Management System**.
 *
 * Responsibilities:
 * -----------------
 * 1. Validates if the current user is authenticated.
 * 2. Loads the global layout (layouts/layout.php), which:
 *      - Displays the sidebar menu
 *      - Handles page header, CSS and JavaScript
 * 3. Renders a simple dashboard with:
 *      - Welcome message
 *      - Quick access links to main modules:
 *          * Voice Profiles
 *          * Text-to-Speech
 *          * Users
 *
 * Workflow:
 * ---------
 * - If the user is NOT authenticated (`$_SESSION['validado'] !== true`):
 *      Redirects to `/login/login.php`.
 * - If authenticated:
 *      Displays the dashboard.
 *
 * Security:
 * ---------
 * - Relies on PHP sessions to validate login state.
 *
 * Dependencies:
 * -------------
 * - layouts/layout.php
 *
 * Session Variables:
 * ------------------
 * - `$_SESSION['validado']`: boolean, must be true for access.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

// -------------------- Start session --------------------
session_start();

// -------------------- Validate authentication --------------------
if (!isset($_SESSION['validado']) || !$_SESSION['validado']) {
    // Redirect to login if not authenticated
    header('Location: /login/login.php');
    exit;
}

// Page metadata for layout.php
$page_title = "Dashboard";
$page_icon = "bi-speedometer2";
$page_subtitle = "Overview and quick access to system modules";

// -------------------- Load global layout --------------------
// layout.php includes header, menu, CSS/JS and opens main container
require_once __DIR__ . '/layouts/layout.php';
?>

<!-- ===================== MAIN DASHBOARD CONTENT ===================== -->
<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="p-4">

        <!-- Welcome header -->
        <h1 class="mb-4">Welcome to the Text-to-Speech Dashboard</h1>

        <!-- Sub description -->
        <p class="lead">
          Use this platform to manage <strong>Users</strong>, <strong>Voice Profiles</strong> and convert <strong>Text to Audio</strong>.
        </p>

        <hr>

        <!-- Info alert -->
        <div class="alert alert-info">
          <i class="bi bi-info-circle-fill me-2"></i>
          Please select an option from the menu on the left to begin.
        </div>

        <!-- Quick links (optional) -->
        <div class="row mt-4">
          <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">Voice Profiles</h5>
                <p class="card-text">Create and manage voice profiles for the OpenAI TTS models.</p>
                <a href="main.php?mod=voiceprofiles" class="btn btn-primary">Go</a>
              </div>
            </div>
          </div>
          <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">Text-to-Speech</h5>
                <p class="card-text">Generate audio files from text using predefined voice profiles.</p>
                <a href="main.php?mod=text2speech" class="btn btn-primary">Go</a>
              </div>
            </div>
          </div>
          <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">Users</h5>
                <p class="card-text">Manage application users and their permissions.</p>
                <a href="main.php?mod=users" class="btn btn-primary">Go</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Closing container opened in layout.php -->
</div> <!-- .main-content -->
</body>
</html>
