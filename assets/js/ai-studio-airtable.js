(function () {
  var cfg = window.LFAirtableManifester;
  if (!cfg) return;

  var container = document.getElementById('lf-airtable-picker');
  if (!container) return;

  var searchInput = document.getElementById('lf-airtable-search');
  var resultsEl = document.getElementById('lf-airtable-results');
  var previewEl = document.getElementById('lf-airtable-preview');
  var generateBtn = document.getElementById('lf-airtable-generate');
  var manifestForm = document.getElementById('lf-ai-manifest-form');
  var manifestInput = document.getElementById('lf_site_manifest');
  var primaryGenerateBtn = document.getElementById('lf-manifester-generate');
  var primaryStatusEl = document.getElementById('lf-manifester-status');
  var statusEl = document.getElementById('lf-airtable-status');
  var tokenToggle = document.getElementById('lf-airtable-token-toggle');
  var tokenInput = document.getElementById('lf_ai_airtable_pat');

  if (tokenToggle && tokenInput) {
    tokenToggle.addEventListener('change', function () {
      tokenInput.type = tokenToggle.checked ? 'text' : 'password';
    });
  }

  if (!searchInput || !resultsEl || !previewEl || !generateBtn || !statusEl) return;

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
    records.forEach(function (record) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'lf-airtable-result';
      var metaParts = [];
      if (record.city || record.state) {
        metaParts.push([record.city, record.state].filter(Boolean).join(', '));
      }
      if (record.niche) {
        metaParts.push(record.niche);
      }
      button.textContent = record.name + (metaParts.length ? ' — ' + metaParts.join(' • ') : '');
      button.addEventListener('click', function () {
        selectedRecord = record;
        generateBtn.disabled = false;
        updatePrimaryState();
        var previewParts = [record.name];
        if (record.city || record.state) {
          previewParts.push([record.city, record.state].filter(Boolean).join(', '));
        }
        if (record.niche) {
          previewParts.push(record.niche);
        }
        previewEl.textContent = previewParts.join(' • ');
        var buttons = resultsEl.querySelectorAll('.lf-airtable-result');
        buttons.forEach(function (btn) {
          btn.classList.toggle('is-active', btn === button);
        });
      });
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
    generateBtn.disabled = true;
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
          generateBtn.disabled = false;
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
        generateBtn.disabled = false;
        updatePrimaryState();
      });
  }

  function submitManifestForm() {
    if (!manifestForm) return;
    setPrimaryStatus('Uploading manifest…', 'info');
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

  generateBtn.addEventListener('click', generateFromRecord);

  if (manifestInput) {
    manifestInput.addEventListener('change', updatePrimaryState);
  }

  if (primaryGenerateBtn) {
    primaryGenerateBtn.addEventListener('click', function () {
      if (hasManifestFile()) {
        submitManifestForm();
        return;
      }
      if (hasAirtableSelection()) {
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

  updatePrimaryState();
})();
