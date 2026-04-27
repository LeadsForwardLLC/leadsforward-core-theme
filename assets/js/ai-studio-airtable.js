(function () {
  var cfg = window.LFAirtableManifester;
  if (!cfg) return;

  var container = document.getElementById('lf-airtable-picker');

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
  var imagesForm = document.getElementById('lf-manifester-images-form');
  var imagesInput = document.getElementById('lf-manifester-images');
  var imagesPreview = document.getElementById('lf-manifester-images-preview');
  var imagesStatusEl = document.getElementById('lf-manifester-images-status');
  var imagesUploading = false;

  if (tokenToggle && tokenInput) {
    tokenToggle.addEventListener('change', function () {
      tokenInput.type = tokenToggle.checked ? 'text' : 'password';
    });
  }

  var hasAirtableUI = !!(searchInput && resultsEl && previewEl && statusEl);

  if (hasAirtableUI && cfg.strings && cfg.strings.searchPlaceholder) {
    searchInput.placeholder = cfg.strings.searchPlaceholder;
  }

  var selectedRecord = null;
  var debounceTimer = null;

  function setStatus(message, type) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.className = 'lf-airtable-status' + (type ? ' is-' + type : '');
  }

  function setPrimaryStatus(message, type) {
    if (!primaryStatusEl) return;
    primaryStatusEl.textContent = message || '';
    primaryStatusEl.className = 'lf-manifester-status' + (type ? ' is-' + type : '');
  }

  function clearResults() {
    if (!resultsEl) return;
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
        fetchScopePreviewForRecord(record);
        var buttons = resultsEl.querySelectorAll('.lf-airtable-result');
        buttons.forEach(function (btn) {
          btn.classList.toggle('is-active', btn === button);
        });
      });
      if (stored && stored.id && stored.id === record.id) {
        selectedRecord = record;
        updatePreview(record);
        fetchScopePreviewForRecord(record);
        button.classList.add('is-active');
      }
      resultsEl.appendChild(button);
    });
  }

  function populateMultiSelect(selectEl, rows, labelKey) {
    if (!selectEl) return;
    var prev = {};
    Array.prototype.slice.call(selectEl.options || []).forEach(function (opt) {
      if (opt && opt.selected) prev[String(opt.value || '')] = true;
    });
    selectEl.innerHTML = '';
    (rows || []).forEach(function (row) {
      if (!row) return;
      var value = String(row.slug || '');
      var label = String(row[labelKey] || row.title || row.label || value);
      if (!value) return;
      var opt = document.createElement('option');
      opt.value = value;
      opt.textContent = label;
      if (prev[value]) opt.selected = true;
      selectEl.appendChild(opt);
    });
    selectEl.disabled = (selectEl.options.length === 0);
  }

  function fetchScopePreviewForRecord(record) {
    if (!record || !record.id || !cfg || !cfg.ajaxUrl || !cfg.nonce) return;
    var svcSelect = document.getElementById('lf-ai-scope-service-slugs');
    var areaSelect = document.getElementById('lf-ai-scope-area-slugs');
    if (!svcSelect && !areaSelect) return;
    var body = new URLSearchParams({
      action: 'lf_ai_airtable_preview_manifest',
      nonce: cfg.nonce,
      record_id: record.id
    });
    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) return;
        populateMultiSelect(svcSelect, payload.data.services || [], 'title');
        populateMultiSelect(areaSelect, payload.data.service_areas || [], 'label');
      })
      .catch(function () {});
  }

  function hasManifestFile() {
    return !!(manifestInput && manifestInput.files && manifestInput.files.length);
  }

  function hasAirtableSelection() {
    return !!(selectedRecord && selectedRecord.id);
  }

  function confirmHomepageOnlyIfNeeded() {
    var sc = cfg.scope || {};
    if (!sc.isHomepageOnly || !sc.servicePostsPublished) {
      return true;
    }
    var msg = (cfg.strings && cfg.strings.confirmHomepageOnly) ? cfg.strings.confirmHomepageOnly : '';
    return window.confirm(msg);
  }

  function updatePrimaryState() {
    if (!primaryGenerateBtn) return;
    if (cfg.scope && cfg.scope.hasTargets === false) {
      primaryGenerateBtn.disabled = true;
      var needScope = (cfg.strings && cfg.strings.scopeNoTargets) ? cfg.strings.scopeNoTargets : 'Enable at least one generation target under step 2 and save.';
      setPrimaryStatus(needScope, 'error');
      return;
    }
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
    if (!hasAirtableUI) return;
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
    if (!confirmHomepageOnlyIfNeeded()) {
      updatePrimaryState();
      return;
    }
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

  if (hasAirtableUI && searchInput) {
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
        if (!confirmHomepageOnlyIfNeeded()) {
          setPrimaryStatus('', '');
          return;
        }
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

  (function initScopeFormShortcuts() {
    function byId(id) {
      return document.getElementById(id);
    }
    var allBtn = byId('lf-ai-scope-select-all');
    var hsBtn = byId('lf-ai-scope-homepage-services');
    var ids = ['lf_ai_gen_homepage', 'lf_ai_gen_services', 'lf_ai_gen_service_areas', 'lf_ai_gen_core_pages', 'lf_ai_gen_blog_posts', 'lf_ai_gen_projects'];
    function setAll(on) {
      ids.forEach(function (id) {
        var el = byId(id);
        if (el) {
          el.checked = on;
        }
      });
    }
    if (allBtn) {
      allBtn.addEventListener('click', function () {
        setAll(true);
      });
    }
    if (hsBtn) {
      hsBtn.addEventListener('click', function () {
        setAll(false);
        var h = byId('lf_ai_gen_homepage');
        var s = byId('lf_ai_gen_services');
        if (h) {
          h.checked = true;
        }
        if (s) {
          s.checked = true;
        }
      });
    }
  })();

  if (hasAirtableUI) {
    if (!cfg.enabled) {
      setStatus(cfg.strings && cfg.strings.notConfigured ? cfg.strings.notConfigured : 'Airtable is not configured.', 'error');
      searchInput.disabled = true;
    } else {
      fetchResults('');
    }
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
    if (!jobId || jobId === '0' || !cfg.jobStatusNonce || polling) return;
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

  function setImagesStatus(message, type) {
    if (!imagesStatusEl) return;
    imagesStatusEl.textContent = message || '';
    imagesStatusEl.className = 'lf-manifester-status' + (type ? ' is-' + type : '');
  }

  function clearImagePreviews() {
    if (!imagesPreview) return;
    while (imagesPreview.firstChild) {
      imagesPreview.removeChild(imagesPreview.firstChild);
    }
  }

  function renderImagePreviews(files) {
    if (!imagesPreview) return;
    clearImagePreviews();
    if (!files || !files.length) return;
    Array.prototype.forEach.call(files, function (file, index) {
      var card = document.createElement('div');
      card.className = 'lf-manifester-image-card';
      card.setAttribute('data-index', String(index));

      var img = document.createElement('img');
      img.alt = file && file.name ? file.name : '';
      var objectUrl = URL.createObjectURL(file);
      img.src = objectUrl;
      img.onload = function () {
        URL.revokeObjectURL(objectUrl);
      };

      var meta = document.createElement('div');
      meta.className = 'lf-manifester-image-meta';
      meta.textContent = file && file.name ? file.name : 'Image';

      card.appendChild(img);
      card.appendChild(meta);
      imagesPreview.appendChild(card);
    });
  }

  function uploadImages(files) {
    if (!files || !files.length || imagesUploading) return;
    if (!cfg.imagesUploadNonce) return;
    var strings = cfg.imagesStrings || {};
    setImagesStatus(strings.uploading || 'Uploading images…', 'info');
    imagesUploading = true;

    // Upload in chunks to avoid server limits (request size / max file uploads).
    // This removes the practical "12 images" ceiling users hit on some hosts.
    var fileArr = Array.prototype.slice.call(files);
    var batchSize = 20;
    var total = fileArr.length;
    var uploadedTotal = 0;
    var failedTotal = 0;

    function uploadBatch(startIndex) {
      var endIndex = Math.min(total, startIndex + batchSize);
      var formData = new FormData();
      formData.append('action', 'lf_ai_studio_images_upload');
      formData.append('nonce', cfg.imagesUploadNonce);
      for (var i = startIndex; i < endIndex; i++) {
        formData.append('lf_manifest_images[]', fileArr[i]);
      }

      setImagesStatus(
        (strings.uploading || 'Uploading images…') + ' (' + uploadedTotal + '/' + total + ')',
        'info'
      );

      return fetch(cfg.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (payload) {
          if (!payload || !payload.success) {
            failedTotal += (endIndex - startIndex);
            return;
          }
          var data = payload.data || {};
          var uploaded = Array.isArray(data.uploaded) ? data.uploaded : [];
          var cards = imagesPreview ? imagesPreview.querySelectorAll('.lf-manifester-image-card') : [];
          uploaded.forEach(function (item, index) {
            var card = cards[startIndex + index];
            if (!card) return;
            var img = card.querySelector('img');
            if (img && item && item.url) {
              img.src = item.url;
            }
            card.classList.add('is-uploaded');
          });
          uploadedTotal += uploaded.length;
          failedTotal += (data.error_count || 0);
        })
        .catch(function () {
          failedTotal += (endIndex - startIndex);
        })
        .then(function () {
          if (endIndex >= total) return;
          return uploadBatch(endIndex);
        });
    }

    uploadBatch(0).then(function () {
      imagesUploading = false;
      var successMsg = strings.success || 'Images uploaded to Media Library.';
      successMsg += ' ' + uploadedTotal + '/' + total + ' uploaded.';
      if (failedTotal) {
        successMsg += ' ' + failedTotal + ' failed.';
      }
      setImagesStatus(successMsg, failedTotal ? 'error' : 'success');
    });
  }

  if (imagesInput) {
    imagesInput.addEventListener('change', function () {
      var files = imagesInput.files;
      if (!files || !files.length) {
        setImagesStatus((cfg.imagesStrings && cfg.imagesStrings.empty) || 'Please choose one or more images before uploading.', 'error');
        clearImagePreviews();
        return;
      }
      renderImagePreviews(files);
      uploadImages(files);
      imagesInput.value = '';
    });
  }

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
