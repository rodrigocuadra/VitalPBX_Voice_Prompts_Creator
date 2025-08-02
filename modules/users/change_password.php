<?php
/**
 * ============================================================================
 * File: modules/users/change_password.php
 * ============================================================================
 * Purpose:
 * --------
 * Provides a **secure password change form** for the currently logged-in user.
 * The form requires the current password for verification and ensures that
 * the new password is confirmed before being submitted to the backend.
 *
 * Requirements:
 * -------------
 * - The user must be logged in and have valid session data.
 * - Permission index 5 ("Change Password") must be enabled for the user.
 *
 * Workflow:
 * ---------
 * 1. Display a form pre-filled with the username (read-only).
 * 2. Fields required:
 *      - Current password
 *      - New password
 *      - Confirm new password
 * 3. Client-side validation:
 *      - Ensure new password and confirmation match before sending.
 * 4. On submit:
 *      - Send an AJAX request (POST) to `modules/users/update_password.php`.
 *      - Display the server's response as a Bootstrap alert.
 *      - Reset the form on success.
 *
 * Security:
 * ---------
 * - The username field is read-only and comes from the session.
 * - Password changes are processed securely on the backend; this page
 *   does not directly update the database.
 *
 * Output:
 * -------
 * HTML form with JavaScript that manages submission and validation.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

$page_title = "Change Password";
$page_icon = "bi-key-fill";
$page_subtitle = "Update your account password securely";

// Includes
require_once __DIR__ . '/../../layouts/layout.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';

// Ensure the current user has permission to access Change Password module
validarAccesoModulo(5);

// Retrieve current logged-in username from session
$currentUsername = $_SESSION['username'] ?? '';
?>

<div class="container mt-4">

  <!-- Change Password Form -->
  <form id="formChangePassword">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" class="form-control" name="username" 
             value="<?= htmlspecialchars($currentUsername) ?>" readonly>
    </div>

    <div class="mb-3">
      <label class="form-label">Current Password</label>
      <input type="password" class="form-control" name="current_password" required>
    </div>

    <div class="mb-3">
      <label class="form-label">New Password</label>
      <input type="password" class="form-control" name="new_password" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Confirm New Password</label>
      <input type="password" class="form-control" name="confirm_password" required>
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-success">Change Password</button>
    </div>
  </form>

  <!-- Container for displaying server responses -->
  <div id="result" class="mt-3"></div>
</div>

<script>
/**
 * ============================================================================
 * JavaScript Section - Password Change Workflow
 * ============================================================================
 * This script:
 *  - Prevents default form submission.
 *  - Ensures the new password matches its confirmation.
 *  - Sends an asynchronous request to update the password.
 *  - Displays the response from the backend as a Bootstrap alert.
 */
document.getElementById('formChangePassword').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = new FormData(this);

  // Validate new password confirmation
  if (form.get('new_password') !== form.get('confirm_password')) {
    document.getElementById('result').innerHTML = 
      '<div class="alert alert-warning">New password does not match confirmation.</div>';
    return;
  }

  // Send POST request to update_password.php
  fetch('modules/users/update_password.php', {
    method: 'POST',
    body: new URLSearchParams(form)
  })
  .then(res => res.json())
  .then(data => {
    const type = data.success ? 'success' : 'danger';
    document.getElementById('result').innerHTML = 
      `<div class="alert alert-${type}">${data.message}</div>`;
    if (data.success) document.getElementById('formChangePassword').reset();
  })
  .catch(() => {
    document.getElementById('result').innerHTML = 
      '<div class="alert alert-danger">An error occurred while processing your request.</div>';
  });
});
</script>
