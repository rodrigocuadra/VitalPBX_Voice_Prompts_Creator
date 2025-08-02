<?php
/**
 * ============================================================================
 * File: modules/users/users.php
 * ============================================================================
 * Purpose:
 * --------
 * This module provides a web-based interface to manage users for the
 * Text-to-Speech Management System.
 *
 * Features:
 * ---------
 * 1. Lists all users in the system.
 * 2. Allows creating new users and editing existing users.
 * 3. Controls user permissions and access rights (20-character string).
 * 4. Supports enabling/disabling user accounts.
 * 5. Provides fields for email and custom messages.
 *
 * Permissions:
 * ------------
 * - Requires permission index 4 to access (User Management module).
 *
 * Database Table:
 * ---------------
 * The `users` table must include:
 *   - id, full_name, username, email, password
 *   - message, permissions, created_at, ip
 *
 * Frontend Workflow:
 * ------------------
 * - The page loads a dropdown of all users.
 * - Selecting a user triggers `get_user.php` via AJAX to fetch details.
 * - Editing form fields and submitting the form triggers `save_user.php`
 *   to update the database.
 * - New users can be created using the "New" button.
 *
 * Security:
 * ---------
 * - Password hashes are never shown in the form (field is blank for edits).
 * - Permissions are applied via checkboxes and stored as a 20-character string.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

// ---------------------------------------------------------------------------
// Error Handling
// ---------------------------------------------------------------------------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/error.log');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Page Metadata
// ---------------------------------------------------------------------------
$page_title = "User Management";
$page_icon = "bi-people-fill";
$page_subtitle = "Manage system users, permissions and access control";

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../../layouts/layout.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// ---------------------------------------------------------------------------
// Access Control: ensure the current user has permission (4)
// ---------------------------------------------------------------------------
validarAccesoModulo(4);

// ---------------------------------------------------------------------------
// Load Data: Fetch all users
// ---------------------------------------------------------------------------
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM users ORDER BY full_name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    debug_log("Error loading users: " . $e->getMessage(), "users_module");
    echo "<div class='alert alert-danger'>Error while loading user data.</div>";
    exit;
}

// ---------------------------------------------------------------------------
// Permission Options (position -> description)
// ---------------------------------------------------------------------------
$permissionsOptions = [
    '1'  => 'Dashboard',
    '2'  => 'Text-to-Speech',
    '3'  => 'Voice Profiles',
    '4'  => 'Users',
    '5'  => 'Change Password',
    '6'  => 'Email Settings',
    '10' => 'Import CSV'
];
?>

<div class="container mt-4">

  <!-- ==============================================================
       Dropdown: Select an existing user to edit
       ============================================================== -->
  <div class="row mb-3 align-items-end">
    <div class="col-md-6">
      <label class="form-label">Select User</label>
      <div class="input-group">
        <select id="userSelect" class="form-select">
          <option value="">-- Select a user --</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>">
              <?= htmlspecialchars($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary ms-2" onclick="newUser()">New</button>
      </div>
    </div>
  </div>

  <!-- ==============================================================
       User Form
       ============================================================== -->
  <form id="userForm">
    <input type="hidden" name="id" id="id">

    <!-- Basic Information -->
    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" id="full_name" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Username</label>
        <input type="text" name="username" id="username" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control" required>
      </div>
    </div>

    <!-- Password and Custom Message -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label class="form-label">Password</label>
        <input type="text" name="password" id="password" class="form-control" 
               placeholder="Leave blank to keep current password">
      </div>
      <div class="col-md-6">
        <label class="form-label">Message</label>
        <input type="text" name="message" id="message" class="form-control">
      </div>
    </div>

    <!-- Account Disabled / Suspended -->
    <div class="row mb-3">
      <div class="col-md-6 offset-md-6 d-flex justify-content-end align-items-center">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="S" id="suspendedCheckbox">
          <label class="form-check-label text-danger fw-bold" for="suspendedCheckbox">
            User Disabled
          </label>
        </div>
      </div>
    </div>

    <!-- Permissions Matrix -->
    <div class="row mb-4">
      <div class="col-md-12">
        <label class="form-label">Permissions</label>
        <div class="row">
          <?php foreach ($permissionsOptions as $pos => $label): ?>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="S" id="perm<?= $pos ?>">
                <label class="form-check-label" for="perm<?= $pos ?>">
                  <?= $pos ?> - <?= $label ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-success">Save User</button>
    </div>
  </form>
</div>

<!-- ==============================================================
     Success Modal
     ============================================================== -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p class="fs-5">User saved successfully.</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button class="btn btn-outline-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
/**
 * ======================================================================
 * JavaScript Section
 * Handles loading user data, creating a new user, and saving user data.
 * ======================================================================
 */

// Event: Load selected user details
document.getElementById('userSelect').addEventListener('change', function () {
  loadUser(this.value);
});

/**
 * Load user details via AJAX and populate form
 */
function loadUser(id) {
  fetch('modules/users/get_user.php?id=' + encodeURIComponent(id))
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const u = data.data;
        document.getElementById('id').value = u.id;
        document.getElementById('full_name').value = u.full_name;
        document.getElementById('username').value = u.username;
        document.getElementById('email').value = u.email || '';
        document.getElementById('password').value = ''; // never display hashed passwords
        document.getElementById('message').value = u.message || '';

        // Load permissions into checkboxes
        let permissions = u.permissions || ''.padEnd(20, 'N');
        for (let i = 1; i <= 20; i++) {
          const chk = document.getElementById('perm' + i);
          if (chk) chk.checked = (permissions.charAt(i - 1) === 'S');
        }
        document.getElementById('suspendedCheckbox').checked = (permissions.charAt(19) === 'S');
      } else {
        alert(data.message || 'Error while loading user');
      }
    });
}

/**
 * Reset form for a new user
 */
function newUser() {
  document.getElementById('userForm').reset();
  document.getElementById('id').value = '';
  for (let i = 1; i <= 20; i++) {
    const chk = document.getElementById('perm' + i);
    if (chk) chk.checked = false;
  }
  document.getElementById('suspendedCheckbox').checked = false;
}

/**
 * Save user via AJAX
 */
document.getElementById('userForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const formData = new FormData(this);

  // Construct the permissions string (20 characters, default N)
  let permissions = ''.padEnd(20, 'N');
  for (let i = 1; i <= 20; i++) {
    const chk = document.getElementById('perm' + i);
    if (chk?.checked) permissions = permissions.substring(0, i - 1) + 'S' + permissions.substring(i);
  }

  if (document.getElementById('suspendedCheckbox').checked) {
    permissions = permissions.substring(0, 19) + 'S';
  }

  formData.append('permissions', permissions);

  fetch('modules/users/save_user.php', {
    method: 'POST',
    body: new URLSearchParams(formData)
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();
      } else {
        alert(data.message || 'Error while saving user');
      }
    })
    .catch(() => alert('Network error while saving user'));
});
</script>
