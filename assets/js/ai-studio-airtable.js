(function () {
  var cfg = window.LFAirtableManifester;
  if (!cfg) return;

  var container = document.getElementById('lf-airtable-picker');
  if (!container) return;

  var searchInput = document.getElementById('lf-airtable-search');
  var resultsEl = document.getElementById('lf-airtable-results');
  var previewEl = document.getElementById('lf-airtable-preview');
  var manifestForm = document.getElementById('lf-ai-manifest-form');
  var manifestInput = document.getElementById('lf_site_manifest');
  var primaryGenerateBtn = document.getElementById('lf-manifester-generate');
  var primaryStatusEl = document.getElementById('lf-manifester-status');
  var statusEl = document.getElementById('lf-airtable-status');
  var tokenToggle = document.getElementById('lf-airtable-token-toggle');
  var tokenInput = document.getElementById('lf_ai_airtable_pat');
  var storageKey = 'lfAirtableSelectedProject';
  var progressWrap = document.querySelector('.lf-manifester-progress');
  var progressBar = progressWrap ? progressWrap.querySelector('.lf-manifester-progress__bar span') : null;
  var progressLabel = progressWrap ? progressWrap.querySelector('.lf-manifester-progress__label') : null;
  var jobId = progressWrap ? progressWrap.getAttribute('data-job-id') : '';
  var polling = false;

  if (tokenToggle && tokenInput) {
    tokenToggle.addEventListener('change', function () {
      tokenInput.type = tokenToggle.checked ? 'text' : 'password';
    });
  }

  if (!searchInput || !resultsEl || !previewEl || !statusEl) return;

  if (cfg.strings && cfg.strings.searchPlaceholder) {
    searchInput.placeholder = cfg.strings.searchPlaceholder;
  }

  var selectedRecord = null;
  var debounceTimer = null;

  function setStatus(message, type) {
    statusEl.textContent = message || '';
    statusEl.className = 'lf-airtable-status' + (type ? ' is-' + type : '');
  }

  function setPrimaryStatus(message, type) {
    if (!primaryStatusEl) return;
    primaryStatusEl.textContent = message || '';
    primaryStatusEl.className = 'lf-manifester-status' + (type ? ' is-' + type : '');
  }

  function clearResults() {
    while (resultsEl.firstChild) {
      resultsEl.removeChild(resultsEl.firstChild);
    }
  }

  function formatRecordLabel(record) {
    if (!record) return '';
    var metaParts = [];
    if (record.city || record.state) {
      metaParts.push([record.city, record.state].filter(Boolean).join(', '));
    }
    if (record.niche) {
      metaParts.push(record.niche);
    }
    return record.name + (metaParts.length ? ' — ' + metaParts.join(' • ') : '');
  }

  function updatePreview(record) {
    if (!previewEl) return;
    if (!record) {
      previewEl.textContent = (cfg.strings && cfg.strings.selectPrompt) ? cfg.strings.selectPrompt : 'Select a project to preview before generating.';
      previewEl.classList.remove('is-selected');
      return;
    }
    previewEl.textContent = formatRecordLabel(record);
    previewEl.classList.add('is-selected');
  }

  function loadStoredSelection() {
    try {
      var raw = window.localStorage.getItem(storageKey);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (parsed && parsed.id) return parsed;
    } catch (e) {
      return null;
    }
    return null;
  }

  function storeSelection(record) {
    if (!record || !record.id) return;
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(record));
    } catch (e) {
      // ignore storage failures
    }
  }

  function renderEmpty() {
    clearResults();
    var empty = document.createElement('div');
    empty.className = 'lf-airtable-empty';
    empty.textContent = (cfg.strings && cfg.strings.noResults) ? cfg.strings.noResults : 'No projects found.';
    resultsEl.appendChild(empty);
  }

  function renderResults(records) {
    clearResults();
    if (!records || !records.length) {
      renderEmpty();
      return;
    }
    var stored = loadStoredSelection();
    records.forEach(function (record) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'lf-airtable-result';
      button.textContent = formatRecordLabel(record);
      button.addEventListener('click', function () {
        selectedRecord = record;
        storeSelection(record);
        updatePrimaryState();
        updatePreview(record);
        var buttons = resultsEl.querySelectorAll('.lf-airtable-result');
        buttons.forEach(function (btn) {
          btn.classList.toggle('is-active', btn === button);
        });
      });
      if (stored && stored.id && stored.id === record.id) {
        selectedRecord = record;
        updatePreview(record);
        button.classList.add('is-active');
      }
      resultsEl.appendChild(button);
    });
  }

  function hasManifestFile() {
    return !!(manifestInput && manifestInput.files && manifestInput.files.length);
  }

  function hasAirtableSelection() {
    return !!(selectedRecord && selectedRecord.id);
  }

  function updatePrimaryState() {
    if (!primaryGenerateBtn) return;
    var canGenerate = hasManifestFile() || hasAirtableSelection();
    primaryGenerateBtn.disabled = !canGenerate;
    if (!canGenerate) {
      setPrimaryStatus('Select a manifest file or Airtable project to continue.', 'info');
      return;
    }
    if (hasManifestFile()) {
      setPrimaryStatus('Ready to generate from manifest.', 'success');
      return;
    }
    setPrimaryStatus('Ready to generate from Airtable.', 'success');
  }

  function fetchResults(query) {
    if (!cfg.enabled) {
      setStatus(cfg.strings && cfg.strings.notConfigured ? cfg.strings.notConfigured : 'Airtable is not configured.', 'error');
      return;
    }
    setStatus('');
    var params = new URLSearchParams({
      action: 'lf_ai_airtable_search',
      nonce: cfg.nonce,
      query: query || ''
    });
    fetch(cfg.ajaxUrl + '?' + params.toString(), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) {
          var msg = payload && payload.data && payload.data.message ? payload.data.message : 'Search failed.';
          setStatus(msg, 'error');
          return;
        }
        renderResults(payload.data.records || []);
        if (payload.data.notice) {
          setStatus(payload.data.notice, 'info');
        }
      })
      .catch(function () {
        setStatus('Search failed.', 'error');
      });
  }

  function generateFromRecord() {
    if (!selectedRecord || !selectedRecord.id) return;
    if (primaryGenerateBtn) {
      primaryGenerateBtn.disabled = true;
    }
    setStatus(cfg.strings && cfg.strings.generating ? cfg.strings.generating : 'Generating from Airtable…', 'info');
    setPrimaryStatus('Generating from Airtable…', 'info');
    var body = new URLSearchParams({
      action: 'lf_ai_airtable_generate',
      nonce: cfg.nonce,
      record_id: selectedRecord.id
    });
    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) {
          var msg = payload && payload.data && payload.data.message ? payload.data.message : 'Generation failed.';
          if (payload && payload.data && payload.data.errors && payload.data.errors.length) {
            msg += '\n' + payload.data.errors.join('\n');
          }
          setStatus(msg, 'error');
          setPrimaryStatus(msg, 'error');
          updatePrimaryState();
          return;
        }
        if (payload.data && payload.data.redirect) {
          window.location.href = payload.data.redirect;
          return;
        }
        setStatus('Generation queued.', 'success');
        setPrimaryStatus('Generation queued.', 'success');
      })
      .catch(function () {
        setStatus('Generation failed.', 'error');
        setPrimaryStatus('Generation failed.', 'error');
        updatePrimaryState();
      });
  }

  function submitManifestForm() {
    if (!manifestForm) return;
    setPrimaryStatus('Uploading manifest…', 'info');
    setProgress(10, 'Uploading manifest…');
    if (manifestForm.requestSubmit) {
      manifestForm.requestSubmit();
      return;
    }
    var evt = new Event('submit', { cancelable: true });
    if (manifestForm.dispatchEvent(evt)) {
      manifestForm.submit();
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var value = searchInput.value.trim();
      if (debounceTimer) {
        window.clearTimeout(debounceTimer);
      }
      debounceTimer = window.setTimeout(function () {
        fetchResults(value);
      }, 300);
    });
  }

  // No secondary generate button; primary button handles generation.

  if (manifestInput) {
    manifestInput.addEventListener('change', updatePrimaryState);
  }

  if (primaryGenerateBtn) {
    primaryGenerateBtn.addEventListener('click', function () {
      if (hasManifestFile()) {
        setProgress(5, 'Queued…');
        submitManifestForm();
        return;
      }
      if (hasAirtableSelection()) {
        setProgress(5, 'Queued…');
        generateFromRecord();
        return;
      }
      updatePrimaryState();
      setPrimaryStatus('Select a manifest file or Airtable project to continue.', 'error');
    });
  }

  if (!cfg.enabled) {
    setStatus(cfg.strings && cfg.strings.notConfigured ? cfg.strings.notConfigured : 'Airtable is not configured.', 'error');
    searchInput.disabled = true;
  } else {
    fetchResults('');
  }

  var storedSelection = loadStoredSelection();
  if (storedSelection) {
    selectedRecord = storedSelection;
    updatePreview(storedSelection);
  }
  updatePrimaryState();

  function setProgress(percent, label) {
    if (progressBar) {
      progressBar.style.width = Math.max(0, Math.min(100, percent || 0)) + '%';
    }
    if (progressLabel && label) {
      progressLabel.textContent = label;
    }
  }

  function fetchJobStatus() {
    if (!jobId || !cfg.jobStatusNonce || polling) return;
    polling = true;
    var params = new URLSearchParams({
      action: 'lf_ai_studio_job_status',
      nonce: cfg.jobStatusNonce,
      job_id: jobId
    });
    fetch(cfg.ajaxUrl + '?' + params.toString(), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) return;
        var data = payload.data;
        var progress = data.progress || {};
        var percent = progress.percent || 0;
        var label = progress.step || progress.message || '';
        if (data.status === 'done') {
          percent = 100;
          label = label || 'Complete.';
        } else if (data.status === 'failed') {
          label = label || 'Failed.';
        } else if (!label) {
          label = 'In progress…';
        }
        setProgress(percent, label);
        if (data.status === 'done' || data.status === 'failed') {
          return;
        }
        window.setTimeout(function () {
          polling = false;
          fetchJobStatus();
        }, 4000);
      })
      .catch(function () {
        window.setTimeout(function () {
          polling = false;
          fetchJobStatus();
        }, 5000);
      });
  }

  fetchJobStatus();

  var researchInput = document.getElementById('lf_site_research');
  var researchStatusEl = document.getElementById('lf-research-status');

  function setResearchStatus(message, type) {
    if (!researchStatusEl) return;
    researchStatusEl.textContent = message || '';
    researchStatusEl.className = 'lf-manifester-status' + (type ? ' is-' + type : '');
  }

  function uploadResearch(file) {
    if (!file || !cfg.researchNonce) return;
    var strings = cfg.researchStrings || {};
    setResearchStatus(strings.uploading || 'Uploading research…', 'info');
    var formData = new FormData();
    formData.append('action', 'lf_ai_studio_research_upload');
    formData.append('nonce', cfg.researchNonce);
    formData.append('lf_site_research', file);
    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) {
          var message = strings.error || 'Research upload failed.';
          if (payload && payload.data && payload.data.errors && payload.data.errors.length) {
            message += '\n' + payload.data.errors.join('\n');
          } else if (payload && payload.data && payload.data.message) {
            message = payload.data.message;
          }
          setResearchStatus(message, 'error');
          return;
        }
        setResearchStatus(strings.success || 'Research uploaded. Ready for generation.', 'success');
      })
      .catch(function () {
        setResearchStatus(strings.error || 'Research upload failed.', 'error');
      });
  }

  if (researchInput) {
    researchInput.addEventListener('change', function () {
      var file = researchInput.files && researchInput.files.length ? researchInput.files[0] : null;
      if (!file) return;
      uploadResearch(file);
    });
  }
})();
