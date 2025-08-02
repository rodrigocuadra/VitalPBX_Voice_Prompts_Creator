<?php
/**
 * ============================================================================
 * File: layouts/layout.php
 * ============================================================================
 * Purpose:
 * --------
 * This file defines the **global layout** of the Text-to-Speech web application.
 * It structures the page into:
 *   1. A **sidebar menu** (on the left) containing navigation links based on
 *      the permissions of the logged-in user.
 *   2. A **top header** (title bar) showing the current page title and optional subtitle.
 *   3. A **main content area** where each module injects its content.
 *
 * Main Features:
 * --------------
 * - **Dynamic Sidebar:** Menu options are built dynamically by reading:
 *       layouts/menu_map.php
 *   and applying permissions from `$_SESSION['userdata']['Permisos']`.
 *
 * - **Permissions:** Each menu option corresponds to a permission index.
 *   If a user has 'S' (enabled) at that index, the menu option is shown.
 *
 * - **Top Header:** Displays a page icon, the title (`$page_title`) and
 *   optional subtitle (`$page_subtitle`).
 *
 * - **Debug Mode Indicator:** If debug mode is enabled (permission 16),
 *   a yellow "DEBUG MODE" banner is shown in the top-right corner.
 *
 * - **Bootstrap UI:** The layout uses Bootstrap 5 for styling and
 *   `bootstrap-icons` for icons.
 *
 * Variables expected to be defined BEFORE including this layout:
 * --------------------------------------------------------------
 *   $page_title    : string (required) Title of the page
 *   $page_icon     : string (optional) Bootstrap icon class (e.g. "bi-mic-fill")
 *   $page_subtitle : string (optional) Small subtitle shown below the title
 *
 * Session Variables:
 * ------------------
 *   $_SESSION['userdata']['Permisos'] : 20-char permission string
 *   $_SESSION['username']             : Current user's display name
 *
 * Security:
 * ---------
 * - Access to this layout implies the user is already authenticated.
 *
 * Dependencies:
 * -------------
 * - utils/helpers.php       : Provides debug_log() and check_permission()
 * - layouts/menu_map.php    : Defines menu items
 * - CSS/JS: /css/bootstrap.min.css, /css/bootstrap-icons.css
 *
 * Author:
 * -------
 * VitalPBX Team
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load helper functions (e.g., check_permission, debug_log)
require_once __DIR__ . '/../utils/helpers.php';

// Load menu configuration (menu_map.php) and user data
$menuItems = require __DIR__ . '/menu_map.php';
$permisos  = str_split($_SESSION['userdata']['Permisos'] ?? '');
$username  = htmlspecialchars($_SESSION['username'] ?? '');

// Define page title as fallback
$page_title = $page_title ?? 'Main Dashboard';

// Log navigation for debug purposes (if debug mode is enabled)
debug_log("Rendering layout for page: $page_title", 'layout');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?></title>

  <!-- Bootstrap and Bootstrap Icons CSS -->
  <link href="/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/bootstrap-icons.css" rel="stylesheet">

  <style>
    /* Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html, body {
      height: 100%;
      font-family: Arial, sans-serif;
    }

    body {
      display: flex;
      overflow: hidden;
    }

    /* Sidebar Styles */
    .sidebar {
      height: 100vh;
      width: 250px;
      background-color: #343a40;
      color: white;
      padding-top: 1rem;
      flex-shrink: 0;
      overflow-y: auto;
    }

    .sidebar .logo {
      text-align: center;
      margin-bottom: 1rem;
    }

    .sidebar .logo img {
      max-width: 150px;
    }

    .sidebar a {
      color: white;
      display: block;
      padding: 10px 20px;
      text-decoration: none;
    }

    .sidebar a:hover {
      background-color: #495057;
    }

    /* Main Content Area */
    .main-content {
      flex-grow: 1;
      height: 100vh;
      overflow-y: auto;
      padding: 0;
      margin: 0;
      background-color: #f8f9fa;
    }

    /* Top Header */
    .top-header {
      background-color: #343a40;
      color: white;
      padding: 1rem 2rem;
      font-size: 1.25rem;
      font-weight: bold;
      border-bottom: 1px solid #212529;
    }

    .main-inner {
      padding: 1.5rem 2rem;
    }
  </style>
</head>
<body>

  <!-- ==========================================================
       DEBUG MODE BANNER (shown if permission 16 is enabled)
       ========================================================== -->
  <?php if (current_user_debug()): ?>
    <div style="position:fixed;top:0;right:0;z-index:9999;
                background:#ffcc00;color:#000;
                padding:4px 12px;border-bottom-left-radius:6px;
                font-size:12px;font-weight:bold;
                box-shadow:0 2px 5px rgba(0,0,0,0.3);">
      DEBUG MODE
    </div>
  <?php endif; ?>

  <!-- ===================== SIDEBAR ===================== -->
  <div class="sidebar">
    <div class="logo">
      <img src="/img/logo.png" alt="Logo">
    </div>

    <h5 class="text-center mb-3">Welcome, <?= $username ?></h5>

    <ul class="nav flex-column">
      <!-- Dynamic menu items based on permissions -->
      <?php foreach ($menuItems as $permNumber => $item): ?>
          <?php
            // Subtract 1 to convert from permission (1-based) to string index (0-based)
            $permIndex = $permNumber - 1;
          ?>
          <?php if (($permisos[$permIndex] ?? 'N') === 'S'): ?>
            <li class="nav-item">
              <a href="<?= $item['url'] ?>" class="nav-link">
                <i class="bi bi-<?= $item['icon'] ?>"></i>
                <?= htmlspecialchars($item['label']) ?>
              </a>
            </li>
          <?php endif; ?>
      <?php endforeach; ?>
      <!-- Logout option -->
      <li class="nav-item">
        <a href="/login/logout.php" class="nav-link text-danger">
          <i class="bi bi-box-arrow-right"></i>
          Logout
        </a>
      </li>
    </ul>
  </div>

  <!-- ===================== MAIN CONTENT ===================== -->
  <div class="main-content">
    <!-- Top bar: page title and optional subtitle -->
    <div class="top-header d-flex flex-column">
        <div class="d-flex align-items-center text-white">
            <?php if (!empty($page_icon)): ?>
                <i class="bi <?= htmlspecialchars($page_icon) ?> me-2" style="font-size: 1.25rem;"></i>
            <?php endif; ?>
            <span class="fw-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($page_title) ?></span>
        </div>
        <?php if (!empty($page_subtitle)): ?>
            <small class="text-light mt-1" style="font-size: 0.85rem; opacity: 0.85;">
                <?= htmlspecialchars($page_subtitle) ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- Inner page content starts here -->
    <div class="main-inner">

    <!-- Bootstrap JS bundle -->
    <script src="/js/bootstrap.bundle.min.js"></script>
