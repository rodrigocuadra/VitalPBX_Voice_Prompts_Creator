<?php
/**
 * ============================================================================
 * File: modules/voice_profiles/voice_profiles.php
 * ============================================================================
 * Purpose:
 * --------
 * This module provides a web interface to **manage Voice Profiles** for
 * the Text-to-Speech (TTS) system. Voice Profiles define the parameters
 * used by OpenAI's TTS API when generating speech audio.
 *
 * Features:
 * ---------
 * - List all existing profiles.
 * - Create new profiles with custom parameters.
 * - Edit existing profiles and update their parameters.
 * - Delete profiles no longer needed.
 * - Dynamically load available voices from the database.
 *
 * Workflow:
 * ---------
 * 1. Validate user permissions (permission index = 3).
 * 2. Retrieve voice profiles from the database for display in a dropdown.
 * 3. Provide a form to:
 *      - Select an existing profile for editing.
 *      - Create a new profile (reset form).
 *      - Save changes using `save_voice_profile.php`.
 *      - Delete profiles using `delete_voice_profile.php`.
 * 4. Use JavaScript (Fetch API) to dynamically:
 *      - Load the selected profile details (via `get_voice_profile.php`).
 *      - Submit profile data to be saved.
 *      - Delete a selected profile.
 *      - Populate the list of available voices from `get_voices.php`.
 *
 * Permissions:
 * ------------
 * - Requires module permission index 3 (Voice Profiles).
 *
 * Database Tables:
 * ----------------
 * - `voice_profiles`: Stores all profile configuration details.
 * - `openai_voices`: Provides a list of supported voices.
 *
 * Dependencies:
 * -------------
 * - layouts/layout.php: Renders the global layout.
 * - config/database.php: Provides PDO database connection.
 * - models/login_model.php: Includes permission validation.
 * - utils/helpers.php: Logging and helper utilities.
 *
 * UI Notes:
 * ---------
 * - Uses Bootstrap for responsive layout and modal dialogs.
 * - The user can modify model, voice, volume, pitch, style, and format.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/error.log');
error_reporting(E_ALL);

$page_title = "Voice Profiles Management";
$page_icon = "mic-fill";
$page_subtitle = "Create and manage voice profiles for TTS generation";

require_once __DIR__ . '/../../layouts/layout.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// ---------------------------------------------------------------------------
// Permission Validation (Module 3)
// ---------------------------------------------------------------------------
validarAccesoModulo(3);

// ---------------------------------------------------------------------------
// Load all voice profiles from database
// ---------------------------------------------------------------------------
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM voice_profiles ORDER BY name ASC");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    debug_log("Error loading voice profiles: " . $e->getMessage(), "voice_profiles_module");
    echo "<div class='alert alert-danger'>Error loading profiles</div>";
    exit;
}
?>

<div class="container mt-4">

  <!-- Profile Selector -->
  <div class="row mb-3 align-items-end">
    <div class="col-md-6">
      <label class="form-label">Select Voice Profile</label>
      <div class="input-group">
        <select id="profileSelect" class="form-select">
          <option value="">-- Select a profile --</option>
          <?php foreach ($profiles as $p): ?>
            <option value="<?= $p['id'] ?>">
              <?= htmlspecialchars($p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary ms-2" onclick="newProfile()">New</button>
      </div>
    </div>
  </div>

  <!-- Profile Form -->
  <form id="formProfile">
    <input type="hidden" name="id" id="id">

    <div class="row mb-3">
      <div class="col-md-6">
        <label class="form-label">Profile Name</label>
        <input type="text" name="name" id="name" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Model</label>
        <select name="model" id="model" class="form-select" required>
          <option value="gpt-4o-mini-tts">gpt-4o-mini-tts</option>
          <option value="gpt-4o-tts">gpt-4o-tts</option>
        </select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Voice</label>
        <select name="voice" id="voice" class="form-select" required></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Audio Format</label>
        <select name="audio_format" id="audio_format" class="form-select" required>
          <option value="mp3">MP3 - default (compressed)</option>
          <option value="wav">WAV - uncompressed</option>
          <option value="pcm">PCM - raw 24kHz</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Volume (0.5-2.0)</label>
        <input type="number" step="0.1" min="0.5" max="2.0" name="volume" id="volume" class="form-control" value="1.0">
      </div>
      <div class="col-md-2">
        <label class="form-label">Pitch (0.5-2.0)</label>
        <input type="number" step="0.1" min="0.5" max="2.0" name="pitch" id="pitch" class="form-control" value="1.0">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" id="description" class="form-control"></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Style / Prompt instructions</label>
      <textarea name="style_prompt" id="style_prompt" class="form-control"></textarea>
    </div>

    <div class="d-grid mb-2">
      <button type="submit" class="btn btn-success">Save Profile</button>
    </div>
    <div class="d-grid">
      <button type="button" class="btn btn-danger" onclick="deleteProfile()">Delete Profile</button>
    </div>
  </form>
</div>

<!-- Success Modal -->
<div class="modal fade" id="modalSuccess" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p class="fs-5">Voice profile saved successfully.</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button class="btn btn-outline-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
// ---------------------------------------------------------------------------
// JS: Event handlers for profile management
// ---------------------------------------------------------------------------

// Load profile when selected
document.getElementById('profileSelect').addEventListener('change', function () {
  loadProfile(this.value);
});

// Load a profile by ID
function loadProfile(id) {
  fetch('modules/voice_profiles/get_voice_profile.php?id=' + encodeURIComponent(id))
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const p = data.data;
        document.getElementById('id').value = p.id;
        document.getElementById('name').value = p.name;
        document.getElementById('model').value = p.model;
        document.getElementById('voice').value = p.voice;
        document.getElementById('audio_format').value = p.audio_format;
        document.getElementById('volume').value = p.volume;
        document.getElementById('pitch').value = p.pitch;
        document.getElementById('description').value = p.description || '';
        document.getElementById('style_prompt').value = p.style_prompt || '';
      } else {
        alert(data.message || 'Error loading profile');
      }
    });
}

// Reset form for new profile creation
function newProfile() {
  document.getElementById('formProfile').reset();
  document.getElementById('id').value = '';
  document.getElementById('model').value = 'gpt-4o-mini-tts';
  document.getElementById('audio_format').value = 'mp3';
  document.getElementById('volume').value = 1.0;
  document.getElementById('pitch').value = 1.0;
}

// Save profile (Insert or Update)
document.getElementById('formProfile').addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);

  fetch('modules/voice_profiles/save_voice_profile.php', {
    method: 'POST',
    body: new URLSearchParams(formData)
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const modal = new bootstrap.Modal(document.getElementById('modalSuccess'));
        modal.show();
      } else {
        alert(data.message || 'Error saving profile');
      }
    })
    .catch(() => alert('Network error while saving profile'));
});

// Delete profile
function deleteProfile() {
  const id = document.getElementById('id').value;
  if (!id) { alert('Select a profile to delete'); return; }
  if (!confirm('Are you sure you want to delete this profile?')) return;

  fetch('modules/voice_profiles/delete_voice_profile.php?id=' + encodeURIComponent(id))
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Profile deleted successfully.');
        location.reload();
      } else {
        alert(data.message || 'Error deleting profile');
      }
    });
}

// Load available voices dynamically from DB
function loadVoices() {
  fetch('modules/voice_profiles/get_voices.php')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const voiceSelect = document.getElementById('voice');
        voiceSelect.innerHTML = '<option value="">-- Select --</option>';
        data.data.forEach(v => {
          const opt = document.createElement('option');
          opt.value = v;
          opt.textContent = v.charAt(0).toUpperCase() + v.slice(1);
          voiceSelect.appendChild(opt);
        });
      } else {
        alert('Error loading voices');
      }
    });
}

// Initialize voices dropdown on page load
loadVoices();
</script>
