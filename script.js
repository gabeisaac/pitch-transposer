(function () {
  'use strict';

  var fileInput = document.getElementById('fileInput');
  var dropZone = document.getElementById('dropZone');
  var fileChip = document.getElementById('fileChip');
  var fileName = document.getElementById('fileName');
  var clearFile = document.getElementById('clearFile');
  var previewWrap = document.getElementById('previewWrap');
  var previewPlayer = document.getElementById('previewPlayer');
  var slider = document.getElementById('semitoneSlider');
  var valueEl = document.getElementById('semitoneValue');
  var downBtn = document.getElementById('downBtn');
  var upBtn = document.getElementById('upBtn');
  var formatSelect = document.getElementById('formatSelect');
  var preserveTempo = document.getElementById('preserveTempo');
  var shiftSummary = document.getElementById('shiftSummary');
  var processBtn = document.getElementById('processBtn');
  var progressBar = document.getElementById('progressBar');
  var statusEl = document.getElementById('status');
  var result = document.getElementById('result');
  var downloadLink = document.getElementById('downloadLink');
  var resultPlayer = document.getElementById('resultPlayer');

  var selectedFile = null;
  var previewUrl = null;
  var resultUrl = null;

  function getSemitones() {
    return parseInt(slider.value, 10) || 0;
  }

  function updateSemitoneUI() {
    var n = getSemitones();
    valueEl.textContent = (n > 0 ? '+' : '') + n;
    var dir;
    if (n === 0) dir = 'no shift';
    else if (n > 0) dir = 'up ' + n + ' semitone' + (n === 1 ? '' : 's');
    else dir = 'down ' + Math.abs(n) + ' semitone' + (n === -1 ? '' : 's');
    var tempo = preserveTempo.checked ? 'tempo preserved' : 'speed changes with pitch';
    shiftSummary.innerHTML = 'Shift <strong>' + dir + '</strong> · ' + tempo;
    processBtn.disabled = !selectedFile || n === 0;
  }

  function setSemitones(n) {
    n = Math.max(-12, Math.min(12, n));
    // Skip 0 when stepping past it? Keep 0 allowed but button disabled.
    slider.value = String(n);
    updateSemitoneUI();
  }

  function showStatus(msg, kind) {
    statusEl.textContent = msg || '';
    statusEl.className = 'status show ' + (kind || 'info');
  }

  function clearStatus() {
    statusEl.textContent = '';
    statusEl.className = 'status';
  }

  function revokePreview() {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
      previewUrl = null;
    }
  }

  function revokeResult() {
    if (resultUrl) {
      URL.revokeObjectURL(resultUrl);
      resultUrl = null;
    }
  }

  function setFile(file) {
    if (!file) return;
    if (!/^audio\//.test(file.type) && !/\.(mp3|wav|m4a|flac|ogg|aac|aiff|aif|wma|mp4|webm)$/i.test(file.name)) {
      showStatus('Please choose an audio file.', 'error');
      return;
    }
    selectedFile = file;
    fileName.textContent = file.name + ' · ' + formatSize(file.size);
    fileChip.classList.add('show');
    revokePreview();
    previewUrl = URL.createObjectURL(file);
    previewPlayer.src = previewUrl;
    previewWrap.classList.add('show');
    result.classList.remove('show');
    clearStatus();
    updateSemitoneUI();
  }

  function clearSelected() {
    selectedFile = null;
    fileInput.value = '';
    fileChip.classList.remove('show');
    previewWrap.classList.remove('show');
    previewPlayer.removeAttribute('src');
    revokePreview();
    result.classList.remove('show');
    updateSemitoneUI();
  }

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  fileInput.addEventListener('change', function () {
    if (fileInput.files && fileInput.files[0]) setFile(fileInput.files[0]);
  });

  clearFile.addEventListener('click', clearSelected);

  ;['dragenter', 'dragover'].forEach(function (evt) {
    dropZone.addEventListener(evt, function (e) {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.add('drag-over');
    });
  });
  ;['dragleave', 'drop'].forEach(function (evt) {
    dropZone.addEventListener(evt, function (e) {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.remove('drag-over');
    });
  });
  dropZone.addEventListener('drop', function (e) {
    var files = e.dataTransfer && e.dataTransfer.files;
    if (files && files[0]) setFile(files[0]);
  });

  slider.addEventListener('input', updateSemitoneUI);
  downBtn.addEventListener('click', function () { setSemitones(getSemitones() - 1); });
  upBtn.addEventListener('click', function () { setSemitones(getSemitones() + 1); });
  preserveTempo.addEventListener('change', updateSemitoneUI);

  processBtn.addEventListener('click', async function () {
    if (!selectedFile || getSemitones() === 0) return;
    clearStatus();
    result.classList.remove('show');
    processBtn.disabled = true;
    progressBar.classList.add('show');
    showStatus('Transposing on the server…', 'info');

    var fd = new FormData();
    fd.append('audioFile', selectedFile);
    fd.append('semitones', String(getSemitones()));
    fd.append('preserveTempo', preserveTempo.checked ? '1' : '0');
    fd.append('targetFormat', formatSelect.value);

    try {
      var res = await fetch('process.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (!data.success) throw new Error(data.message || 'Transpose failed.');

      downloadLink.href = data.downloadUrl;
      downloadLink.setAttribute('download', data.filename || 'transposed');
      downloadLink.textContent = 'Download ' + (data.filename || 'file');
      resultPlayer.src = data.downloadUrl;
      result.classList.add('show');
      showStatus('Done — preview below or download.', 'success');
    } catch (err) {
      showStatus(err.message || 'Something went wrong.', 'error');
    } finally {
      progressBar.classList.remove('show');
      updateSemitoneUI();
    }
  });

  updateSemitoneUI();
})();
