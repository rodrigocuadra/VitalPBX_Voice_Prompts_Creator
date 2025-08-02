<?php
/**
 * ============================================================================
 * File: modules/stts/tts.php
 * ============================================================================
 * Purpose:
 * --------
 * Provides the **Text-to-Speech main interface** with two main features:
 *
 * 1. **Direct Text Mode (single phrase):**
 *    - Allows the user to select a **Voice Profile**, edit its parameters
 *      (model, voice, volume, pitch, etc.) and generate audio immediately.
 *    - The generated audio is fetched from OpenAI API in real time and played
 *      in the browser.
 *
 * 2. **Batch CSV Mode (multiple phrases):**
 *    - Available **only if the user has permission index 10**.
 *    - Allows uploading a CSV file in the format:
 *         `filename,text`
 *      where `filename` may include subfolders (e.g. `digits/hours`) so that
 *      the generated audio will be saved into structured folders.
 *    - The uploaded CSV is **previewed in a table**, allowing the user to:
 *        - Preview each individual row by generating audio directly.
 *        - Queue all rows as a background batch job. This job is processed by
 *          a cron script (`crons/cron_process_csv_jobs.php`) and, when done,
 *          the user is notified by email with a ZIP file download link.
 *
 * Behavior:
 * ---------
 * - Profile parameters can be updated using a "Save changes" button, which
 *   updates the `voice_profiles` table through `save_voice_profile.php`.
 * - The **Batch (CSV)** tab is dynamically rendered only if the user has
 *   permission index 10 (`check_permission(10)`).
 *
 * Dependencies:
 * -------------
 * - layouts/layout.php: Provides layout and navigation.
 * - config/database.php: Provides database connection via getPDO().
 * - models/login_model.php: For `validarAccesoModulo()`.
 * - utils/helpers.php: For `debug_log()` and `check_permission()`.
 * - generate_tts.php: Generates real-time audio from OpenAI API.
 * - process_csv.php: Parses CSV and returns JSON rows.
 * - queue_csv_job.php: Queues CSV rows for background TTS generation.
 *
 * Security:
 * ---------
 * - Requires permission 2 to access this module.
 * - Batch (CSV) tab is hidden if permission 10 is not granted.
 *
 * Output:
 * -------
 * HTML page with Bootstrap UI and JavaScript for AJAX operations.
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

$page_title = "Text-to-Speech Generator";
$page_icon = "bi-soundwave";
$page_subtitle = "Generate audio prompts for PBX and IVR systems";

require_once __DIR__ . '/../../layouts/layout.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/login_model.php';
require_once __DIR__ . '/../../utils/helpers.php';

// ---------------------------------------------------------------------------
// Access validation for module 2 (TTS)
// ---------------------------------------------------------------------------
validarAccesoModulo(2);

// ---------------------------------------------------------------------------
// Load voice profiles from the database
// ---------------------------------------------------------------------------
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT id, name FROM voice_profiles ORDER BY name ASC");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    debug_log("Error loading voice profiles: " . $e->getMessage(), "tts_module");
    echo "<div class='alert alert-danger'>Error loading voice profiles</div>";
    exit;
}

// Predefined available voices
$availableVoices = ['alloy','ash','ballad','coral','echo','fable','nova','onyx','sage','shimmer'];
?>

<div class="container my-4">

  <!-- Tabs for Direct Text and Batch CSV -->
  <ul class="nav nav-tabs justify-content-center" id="ttsTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="direct-tab" data-bs-toggle="tab" data-bs-target="#direct" type="button" role="tab">
        <i class="bi bi-mic"></i> Direct Text
      </button>
    </li>
    <?php if (check_permission(10)): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv" type="button" role="tab">
        <i class="bi bi-filetype-csv"></i> Batch (CSV)
      </button>
    </li>
    <?php endif; ?>
  </ul>

  <div class="tab-content mt-4" id="ttsTabContent">

    <!-- ===================================================================== -->
    <!-- Direct TTS tab -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade show active" id="direct" role="tabpanel">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <form id="directForm">
            <!-- Voice Profile Selection -->
            <div class="mb-3">
              <label class="form-label fw-bold">Voice Profile</label>
              <select name="voice_profile_id" id="voice_profile_id" class="form-select" required>
                <option value="">-- Select a profile --</option>
                <?php foreach ($profiles as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Profile Parameters Panel (hidden until a profile is selected) -->
            <div id="profileParams" class="p-3 mb-3 bg-light border rounded" style="display:none;">
              <p class="mb-2 text-muted"><i class="bi bi-sliders"></i> Edit profile parameters:</p>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label">Model</label>
                  <select id="param_model" name="param_model" class="form-select">
                    <option value="gpt-4o-mini-tts">gpt-4o-mini-tts</option>
                    <option value="gpt-4o-tts">gpt-4o-tts</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Voice</label>
                  <select id="param_voice" name="param_voice" class="form-select">
                    <?php foreach ($availableVoices as $v): ?>
                      <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Audio Format</label>
                  <select id="param_audio_format" name="param_audio_format" class="form-select">
                    <option value="mp3">MP3</option>
                    <option value="wav">WAV</option>
                    <option value="pcm">PCM</option>
                  </select>
                </div>
                <div class="col-md-1">
                  <label class="form-label">Vol</label>
                  <input type="number" step="0.1" min="0.5" max="2.0" id="param_volume" name="param_volume" class="form-control">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Pitch</label>
                  <input type="number" step="0.1" min="0.5" max="2.0" id="param_pitch" name="param_pitch" class="form-control">
                </div>
              </div>
              <div class="mt-3">
                <label class="form-label">Style / Prompt</label>
                <textarea id="param_style_prompt" name="param_style_prompt" class="form-control" rows="2"></textarea>
              </div>
              <div class="mt-3 text-end">
                <button type="button" id="saveProfileBtn" class="btn btn-outline-success">
                  <i class="bi bi-save"></i> Save changes to profile
                </button>
              </div>
            </div>

            <!-- Text to convert -->
            <div class="mb-3">
              <label class="form-label fw-bold">Text to convert</label>
              <textarea name="text" id="text" class="form-control" rows="5" required placeholder="Enter the text you want to convert to speech..."></textarea>
            </div>

            <!-- Generate button -->
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-play-fill"></i> Generate & Play
              </button>
            </div>
          </form>

          <!-- Audio playback -->
          <div id="player" class="mt-4 text-center" style="display:none;">
            <h5 class="mb-3"><i class="bi bi-music-note-beamed"></i> Playback</h5>
            <audio id="audioPlayer" controls style="width:100%; max-width:400px;"></audio>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- Batch CSV tab (only if permission index 10) -->
    <!-- ===================================================================== -->
    <?php if (check_permission(10)): ?>
    <div class="tab-pane fade" id="csv" role="tabpanel">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <!-- CSV upload form -->
          <form id="csvForm" enctype="multipart/form-data" method="post">
            <div class="mb-3">
              <label class="form-label fw-bold">Voice Profile</label>
              <select name="voice_profile_id" id="csv_voice_profile_id" class="form-select" required>
                <option value="">-- Select a profile --</option>
                <?php foreach ($profiles as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Upload CSV file</label>
              <input type="file" name="csv_file" accept=".csv" class="form-control" required>
              <div class="form-text">
                Format: <code>filename,text</code><br>
                Once loaded, you can preview each row and finally process all.
              </div>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-eye"></i> Load and Preview
              </button>
            </div>
          </form>

          <!-- Preview table -->
          <div id="csvPreview" class="mt-4" style="display:none;">
            <div class="mb-3">
              <button id="processAllBtn" class="btn btn-primary w-100">
                <i class="bi bi-cloud-arrow-up"></i> Process All (Cron)
              </button>
            </div>
            <div class="mb-3">
              <button id="processRealtimeBtn" class="btn btn-warning w-100">
                <i class="bi bi-lightning-charge"></i> Process All (Real-Time)
              </button>
            </div>
            <h5><i class="bi bi-table"></i> Preview</h5>
            <table class="table table-bordered table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:20%">File name</th>
                  <th>Text</th>
                  <th style="width:120px">Actions</th>
                </tr>
              </thead>
              <tbody id="csvTableBody">
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ========================================================= -->
<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">Confirmation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="confirmMessage" class="fs-5"></p>
      </div>
      <div class="modal-footer">
        <button type="button" id="confirmCancelBtn" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmOkBtn" class="btn btn-warning">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-info">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Information</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="alertMessage" class="fs-5"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-info" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- Real-time Progress Modal -->
<div class="modal fade" id="realtimeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-primary">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Real-Time Processing</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="realtimeMessage">Preparing...</p>
        <div class="progress mt-3">
          <div id="realtimeProgress" class="progress-bar" style="width: 0%;">0%</div>
        </div>
        <button id="downloadZipBtn" class="btn btn-success w-100 mt-3 d-none">Download ZIP</button>
      </div>
    </div>
  </div>
</div>

<!--
===============================================================================
Embedded JavaScript Documentation - modules/stts/tts.php
===============================================================================
This JavaScript code handles **all client-side interactions** for the
Text-to-Speech module. It supports:

1. Dynamic loading of **voice profile parameters** when a profile is selected.
2. Real-time **TTS generation** for Direct Text mode.
3. Saving updated **profile settings**.
4. Managing the **Batch (CSV)** feature:
   - Uploading and previewing CSV files.
   - Playing a preview of an individual CSV row.
   - Queuing all rows for background processing.

Sections:
---------

A) Profile Parameters Loading
-----------------------------
- Event: `profileSelect.addEventListener('change', ...)`
- When the user selects a profile from the dropdown:
  - A GET request is sent to `modules/voice_profiles/get_voice_profile.php`.
  - The server responds with the model, voice, volume, pitch, etc.
  - These values are populated in the profile parameter inputs, and
    the parameters panel is shown.

B) Direct Text Mode (Single TTS)
--------------------------------
- Event: `document.getElementById('directForm').addEventListener('submit', ...)`
- Steps:
  1. Prevent form submission.
  2. Disable the submit button and show a loading spinner.
  3. Send the text and selected profile to `generate_tts.php`.
  4. Receive the generated audio as a blob.
  5. Create an object URL and assign it to the audio player, then play.

C) Save Profile Button
----------------------
- Event: `saveProfileBtn.addEventListener('click', ...)`
- Sends updated parameters (model, voice, etc.) to
  `modules/voice_profiles/save_voice_profile.php`.
- Displays an alert on success or failure.

D) CSV Batch Mode
-----------------
1. **Upload & Preview CSV**  
   - Event: `csvForm.addEventListener('submit', ...)`
   - Uploads the CSV to `process_csv.php`.
   - The server parses the file and returns JSON:
       [
         { "filename": "digits/hours", "text": "It is 9 o'clock" },
         ...
       ]
   - The rows are displayed in a table.
   - A global variable `window.csvData` stores the profile and rows.

2. **Preview a Row**  
   - Function: `previewRow(index)`  
   - Sends the selected row to `generate_tts.php` to generate
     audio for that single row and plays it directly in the browser.

3. **Queue All Rows**  
   - Event: `processAllBtn.addEventListener('click', ...)`
   - Sends a POST request with all rows and the selected profile to
     `queue_csv_job.php`.
   - The backend stores this job in `jobs/tts_queue.json` to be processed
     later by the cron script `cron_process_csv_jobs.php`.

Error Handling:
---------------
- All fetch calls use `.catch()` blocks to display errors in alerts.
- Buttons are re-enabled after each request completes.

Security:
---------
- This script relies on server-side permission validation.
- The Batch (CSV) form is only rendered if permission index 10 is active.

Dependencies:
-------------
- Bootstrap for UI
- Back-end endpoints:
  - `modules/voice_profiles/get_voice_profile.php`
  - `modules/voice_profiles/save_voice_profile.php`
  - `modules/stts/generate_tts.php`
  - `modules/stts/process_csv.php`
  - `modules/stts/queue_csv_job.php`

===============================================================================
-->
<script>
const profileSelect = document.getElementById('voice_profile_id');
const profileParamsDiv = document.getElementById('profileParams');

profileSelect.addEventListener('change', function () {
  const id = this.value;
  if (!id) {
    profileParamsDiv.style.display = 'none';
    return;
  }
  fetch('modules/voice_profiles/get_voice_profile.php?id=' + encodeURIComponent(id))
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const p = data.data;
        document.getElementById('param_model').value = p.model;
        document.getElementById('param_voice').value = p.voice;
        document.getElementById('param_volume').value = p.volume;
        document.getElementById('param_pitch').value = p.pitch;
        document.getElementById('param_style_prompt').value = p.style_prompt || '';
        document.getElementById('param_audio_format').value = p.audio_format || 'mp3';
        profileParamsDiv.style.display = 'block';
      } else {
        profileParamsDiv.style.display = 'none';
      }
    });
});

document.getElementById('directForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const btn = this.querySelector('button[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating...';

  fetch('modules/stts/generate_tts.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-play-fill"></i> Generate & Play';
    if (!response.ok) throw new Error("Error generating TTS");
    return response.blob();
  })
  .then(blob => {
    const audioURL = URL.createObjectURL(blob);
    const player = document.getElementById('audioPlayer');
    player.src = audioURL;
    document.getElementById('player').style.display = 'block';
    player.play();
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-play-fill"></i> Generate & Play';
    alert('Error: ' + err.message);
  });
});

// Save profile changes
document.getElementById('saveProfileBtn').addEventListener('click', function () {
  const id = profileSelect.value;
  if (!id) {
    alert('Please select a profile first.');
    return;
  }
  const params = {
    id: id,
    name: profileSelect.options[profileSelect.selectedIndex].text,
    model: document.getElementById('param_model').value,
    voice: document.getElementById('param_voice').value,
    volume: document.getElementById('param_volume').value,
    pitch: document.getElementById('param_pitch').value,
    audio_format: document.getElementById('param_audio_format').value,
    description: '',
    style_prompt: document.getElementById('param_style_prompt').value
  };
  fetch('modules/voice_profiles/save_voice_profile.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(params)
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Profile updated successfully.');
      } else {
        alert(data.message || 'Error updating profile.');
      }
    })
    .catch(err => alert('Error: ' + err.message));
});

// CSV form async (only attach if element exists)
const csvForm = document.getElementById('csvForm');
if (csvForm) {
  csvForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

    fetch('modules/stts/process_csv.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye"></i> Load and Preview';

        if (!data.success) {
          alert(data.message || "Failed to load CSV.");
          return;
        }

        const tbody = document.getElementById('csvTableBody');
        tbody.innerHTML = '';
        const rows = data.rows;
        rows.forEach((row, index) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${row.filename}</td>
            <td>${row.text}</td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="previewRow(${index})">
                <i class="bi bi-play-fill"></i> Preview
              </button>
            </td>
          `;
          tbody.appendChild(tr);
        });

        document.getElementById('csvPreview').style.display = 'block';
        window.csvData = { profile: document.getElementById('csv_voice_profile_id').value, rows };
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye"></i> Load and Preview';
        alert('Error: ' + err.message);
      });
  });
}

// Preview a single row using generate_tts.php
function previewRow(index) {
  const row = window.csvData.rows[index];
  const formData = new FormData();
  formData.append('voice_profile_id', window.csvData.profile);
  formData.append('text', row.text);

  fetch('modules/stts/generate_tts.php', { method: 'POST', body: formData })
    .then(response => {
      if (!response.ok) throw new Error("Error generating preview");
      return response.blob();
    })
    .then(blob => {
      const audioURL = URL.createObjectURL(blob);
      const audio = new Audio(audioURL);
      audio.play();
    })
    .catch(err => alert('Preview failed: ' + err.message));
}

// Process All rows in background
const processAllBtn = document.getElementById('processAllBtn');
if (processAllBtn) {
  processAllBtn.addEventListener('click', () => {
    if (!confirm("Confirm sending all rows for processing in background?")) return;

    const payload = {
      profile: window.csvData.profile,
      rows: window.csvData.rows
    };

    fetch('modules/stts/queue_csv_job.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('The job has been queued. You will receive an email when all files are ready.');
          document.getElementById('csvPreview').style.display = 'none';
        } else {
          alert(data.message || 'Error queuing job.');
        }
      })
      .catch(err => alert('Error: ' + err.message));
  });
}

// ============================================================================
// Helper functions for Bootstrap confirm and alert modals (Promise-based)
// ============================================================================
function showConfirm(message) {
  return new Promise((resolve) => {
    const modalEl = document.getElementById('confirmModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('confirmMessage').innerText = message;

    const okButton = document.getElementById('confirmOkBtn');
    const cancelButton = document.getElementById('confirmCancelBtn');

    const cleanUp = () => {
      okButton.onclick = null;
      cancelButton.onclick = null;
    };

    okButton.onclick = () => {
      cleanUp();
      modal.hide();
      resolve(true);
    };
    cancelButton.onclick = () => {
      cleanUp();
      modal.hide();
      resolve(false);
    };

    modal.show();
  });
}

function showAlert(message) {
  const modalEl = document.getElementById('alertModal');
  const modal = new bootstrap.Modal(modalEl);
  document.getElementById('alertMessage').innerText = message;
  modal.show();
}

// ============================================================================
// Real-Time Processing with Bootstrap modal
// ============================================================================
const processRealtimeBtn = document.getElementById('processRealtimeBtn');
if (processRealtimeBtn) {
  processRealtimeBtn.addEventListener('click', async () => {
    const confirmed = await showConfirm("Do you want to process all rows now (real-time)?");
    if (!confirmed) return;
    if (!window.csvData || !window.csvData.rows) {
      showAlert("You need to load a CSV first.");
      return;
    }

    const modalEl = document.getElementById('realtimeModal');
    const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    const progressBar = document.getElementById('realtimeProgress');
    const messageEl = document.getElementById('realtimeMessage');
    const downloadBtn = document.getElementById('downloadZipBtn');

    downloadBtn.classList.add('d-none');
    progressBar.style.width = "0%";
    progressBar.textContent = "0%";
    messageEl.innerHTML = "Starting real-time processing...";
    modal.show();

    const profileId = window.csvData.profile;
    const rows = window.csvData.rows;
    const tbody = document.getElementById('csvTableBody');
    const uploadedFiles = [];

    // Clear previous state
    [...tbody.querySelectorAll('tr')].forEach(tr => {
      tr.style.backgroundColor = '';
      tr.cells[2].innerHTML = '';
    });

    // Process rows sequentially
    for (let i = 0; i < rows.length; i++) {
      // Update progress UI before starting fetch
      const percent = Math.round(((i) / rows.length) * 100);
      progressBar.style.width = percent + "%";
      progressBar.textContent = percent + "%";
      messageEl.innerHTML = `Processing row ${i + 1} of ${rows.length}...`;
      await new Promise(r => setTimeout(r, 100)); // short pause for UI update

      const row = rows[i];
      const tr = tbody.querySelectorAll('tr')[i];
      try {
        const formData = new FormData();
        formData.append('voice_profile_id', profileId);
        formData.append('text', row.text);

        const response = await fetch('modules/stts/generate_tts.php', {
          method: 'POST',
          body: formData
        });

        if (!response.ok) throw new Error("Error generating audio");

        const blob = await response.blob();
        const fileExt = "mp3";
        const fileName = row.filename;

        // Upload generated audio to server
        const uploadData = new FormData();
        uploadData.append('filename', fileName);
        uploadData.append('audio', new File([blob], fileName + "." + fileExt));

        const uploadResp = await fetch('modules/stts/upload_audio.php', {
          method: 'POST',
          body: uploadData
        });
        const uploadResult = await uploadResp.json();
        if (uploadResult.success) {
          uploadedFiles.push(uploadResult.file);
        }

        tr.style.backgroundColor = '#d4edda';
        tr.cells[2].innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle"></i> Done</span>';

      } catch (err) {
        tr.style.backgroundColor = '#f8d7da';
        tr.cells[2].innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle"></i> Error</span>';
      }
    }

    // Final progress
    progressBar.style.width = "100%";
    progressBar.textContent = "100%";
    messageEl.innerHTML = "Generating ZIP file...";

    // Generate ZIP on server
    try {
      const zipResp = await fetch('modules/stts/generate_zip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ files: uploadedFiles })
      });
      const zipResult = await zipResp.json();
      if (zipResult.success) {
        messageEl.innerHTML = "All rows processed successfully! ZIP ready.";
        downloadBtn.classList.remove('d-none');
        downloadBtn.onclick = () => window.open(zipResult.zip, '_blank');
      } else {
        messageEl.innerHTML = "Processing finished but ZIP could not be generated.";
      }
    } catch (zipErr) {
      messageEl.innerHTML = "Processing finished, but ZIP generation failed.";
    }
  });
}

</script>