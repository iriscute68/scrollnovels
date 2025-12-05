<?php
// Use the full-featured settings management code
require_once __DIR__ . '/../settings.php';
?>

<div class="container">
  <h1 class="page-title">Settings</h1>
  <div class="card">
    <form id="settingsForm">
      <div class="form-group">
        <label class="label">Platform Name</label>
        <input id="platformName" type="text" class="input-field" />
      </div>
      <div class="form-group">
        <label class="label">Commission %</label>
        <input id="commission" type="number" step="0.01" class="input-field" />
      </div>
      <button type="button" id="btnSaveSettings" class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
    </form>
  </div>
</div>
