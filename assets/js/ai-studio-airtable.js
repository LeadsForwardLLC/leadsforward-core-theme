(function () {
  var cfg = window.LFAirtableManifester;
  if (!cfg) return;

  var container = document.getElementById('lf-airtable-picker');

  var searchInput = document.getElementById('lf-airtable-search');
  var resultsEl = document.getElementById('lf-airtable-results');
  var previewEl = document.getElementById('lf-airtable-preview');
  var primaryBuildBtn = document.getElementById('lf-manifester-build-site');
  var secondaryGenerateBtn = document.getElementById('lf-manifester-generate');
  var primaryStatusEl = document.getElementById('lf-manifester-status');
  var statusEl = document.getElementById('lf-airtable-status');
  var statusInlineEl = document.getElementById('lf-airtable-status-inline');
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
  var scopePreviewRecordId = '';

  function setStatus(message, type) {
    var els = [statusEl, statusInlineEl].filter(Boolean);
    if (!els.length) return;
    els.forEach(function (el) {
      el.textContent = message || '';
      el.className = 'lf-airtable-status' + (type ? ' is-' + type : '');
    });
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
      previewEl.textContent = (cfg.strings && cfg.strings.selectPrompt) ? cfg.strings.selectPrompt : 'Select a project to preview before building.';
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
        scopePreviewRecordId = '';
        fetchScopePreviewForRecord(record, true);
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

  function updateScopeFilterCount(filterEl) {
    if (!filterEl) return;
    var countEl = filterEl.querySelector('[data-lf-scope-count]');
    var listEl = filterEl.querySelector('.lf-scope-filter__list');
    if (!countEl || !listEl) return;
    var boxes = listEl.querySelectorAll('.lf-scope-filter__checkbox');
    var total = boxes.length;
    var selected = 0;
    Array.prototype.forEach.call(boxes, function (cb) {
      if (cb.checked) selected++;
    });
    var strings = (cfg && cfg.strings) ? cfg.strings : {};
    countEl.classList.remove('is-warn');
    if (total === 0) {
      countEl.textContent = strings.scopeFilterEmpty || 'No list loaded';
      return;
    }
    var tpl = strings.scopeFilterCount || '{selected}/{total} included';
    countEl.textContent = tpl.replace('{selected}', String(selected)).replace('{total}', String(total));
    if (selected === 0) {
      countEl.classList.add('is-warn');
    }
  }

  function setScopeFilterChecks(filterEl, on) {
    if (!filterEl) return;
    var listEl = filterEl.querySelector('.lf-scope-filter__list');
    if (!listEl) return;
    listEl.querySelectorAll('.lf-scope-filter__checkbox').forEach(function (cb) {
      cb.checked = !!on;
      cb.disabled = false;
    });
    updateScopeFilterCount(filterEl);
  }

  function filterScopeListItems(filterEl, query) {
    if (!filterEl) return;
    var q = String(query || '').trim().toLowerCase();
    filterEl.querySelectorAll('[data-lf-scope-item]').forEach(function (item) {
      var label = item.querySelector('.lf-scope-filter__label');
      var text = label ? String(label.textContent || '').toLowerCase() : '';
      item.classList.toggle('is-hidden', q !== '' && text.indexOf(q) === -1);
    });
  }

  function bindScopeFilter(filterEl) {
    if (!filterEl) return;
    var listEl = filterEl.querySelector('.lf-scope-filter__list');
    var searchEl = filterEl.querySelector('[data-lf-scope-search]');
    if (listEl && !listEl.getAttribute('data-lf-scope-list-bound')) {
      listEl.setAttribute('data-lf-scope-list-bound', '1');
      listEl.addEventListener('change', function () {
        updateScopeFilterCount(filterEl);
      });
    }
    if (searchEl && !searchEl.getAttribute('data-lf-scope-search-bound')) {
      searchEl.setAttribute('data-lf-scope-search-bound', '1');
      searchEl.addEventListener('input', function () {
        filterScopeListItems(filterEl, searchEl.value);
      });
    }
    updateScopeFilterCount(filterEl);
  }

  function initScopeFilters() {
    document.querySelectorAll('[data-lf-scope-filter]').forEach(function (filterEl) {
      bindScopeFilter(filterEl);
    });
  }

  function deriveScopePickMode(listEl) {
    if (!listEl) return 'all';
    var boxes = listEl.querySelectorAll('.lf-scope-filter__checkbox');
    var total = boxes.length;
    if (total === 0) return 'all';
    var checked = 0;
    boxes.forEach(function (cb) {
      if (cb.checked) checked++;
    });
    if (checked === 0) return 'none';
    if (checked === total) return 'all';
    return 'pick';
  }

  function syncScopeModeOnSubmit(filterEl) {
    if (!filterEl) return;
    var listEl = filterEl.querySelector('.lf-scope-filter__list');
    var modeInput = filterEl.querySelector('[data-lf-scope-mode]');
    if (!listEl || !modeInput) return;
    var mode = deriveScopePickMode(listEl);
    modeInput.value = mode;
    listEl.setAttribute('data-scope-mode', mode);
    listEl.querySelectorAll('.lf-scope-filter__checkbox').forEach(function (cb) {
      cb.disabled = false;
    });
  }

  function syncScopeSectionVisibility() {
    document.querySelectorAll('[data-lf-scope-toggle]').forEach(function (wrap) {
      var toggleId = wrap.getAttribute('data-lf-scope-toggle');
      var toggle = toggleId ? document.getElementById(toggleId) : null;
      var show = !!(toggle && toggle.checked);
      wrap.classList.toggle('is-hidden', !show);
    });
  }

  function publishScheduleStrings() {
    return (cfg && cfg.strings) ? cfg.strings : {};
  }

  function publishScheduleMinDate() {
    var d = new Date();
    var m = String(d.getMonth() + 1);
    var day = String(d.getDate());
    if (m.length < 2) m = '0' + m;
    if (day.length < 2) day = '0' + day;
    return d.getFullYear() + '-' + m + '-' + day;
  }

  function buildPublishScheduleNode(scheduleKey, stored) {
    stored = stored || {};
    var strings = publishScheduleStrings();
    var timing = String(stored.timing || 'now');
    var date = String(stored.date || '');
    var wrap = document.createElement('div');
    wrap.className = 'lf-publish-schedule lf-publish-schedule--compact';
    wrap.setAttribute('data-lf-publish-schedule', '1');
    wrap.setAttribute('data-schedule-key', scheduleKey);

    var select = document.createElement('select');
    select.className = 'lf-publish-schedule__timing';
    select.name = 'lf_ai_publish_schedule[' + scheduleKey + '][timing]';
    select.setAttribute('data-lf-publish-timing', '1');
    [
      ['now', strings.publishNow || 'Publish now'],
      ['schedule', strings.publishSchedule || 'Schedule'],
      ['draft', strings.publishDraft || 'Keep draft']
    ].forEach(function (pair) {
      var opt = document.createElement('option');
      opt.value = pair[0];
      opt.textContent = pair[1];
      if (timing === pair[0]) opt.selected = true;
      select.appendChild(opt);
    });

    var dateInput = document.createElement('input');
    dateInput.type = 'date';
    dateInput.className = 'lf-publish-schedule__date';
    dateInput.name = 'lf_ai_publish_schedule[' + scheduleKey + '][date]';
    dateInput.value = date.length >= 10 ? date.slice(0, 10) : date;
    dateInput.min = publishScheduleMinDate();
    dateInput.setAttribute('data-lf-publish-date', '1');
    dateInput.autocomplete = 'off';
    dateInput.setAttribute('aria-label', strings.publishDatePlaceholder || 'Publish date');
    if (timing !== 'schedule') {
      dateInput.classList.add('lf-publish-schedule__date--hidden');
    }

    select.addEventListener('click', function (e) {
      e.stopPropagation();
    });
    select.addEventListener('change', function (e) {
      e.stopPropagation();
    });
    dateInput.addEventListener('click', function (e) {
      e.stopPropagation();
    });
    dateInput.addEventListener('change', function (e) {
      e.stopPropagation();
    });

    wrap.appendChild(select);
    wrap.appendChild(dateInput);
    bindPublishTimingSelect(wrap);
    return wrap;
  }

  function bindPublishTimingSelect(wrap) {
    if (!wrap || wrap.getAttribute('data-lf-publish-bound')) return;
    wrap.setAttribute('data-lf-publish-bound', '1');
    var select = wrap.querySelector('[data-lf-publish-timing]');
    var dateEl = wrap.querySelector('[data-lf-publish-date]');
    if (!select || !dateEl) return;
    var sync = function () {
      var on = select.value === 'schedule';
      dateEl.classList.toggle('lf-publish-schedule__date--hidden', !on);
      if (!on) {
        dateEl.value = '';
      }
    };
    select.addEventListener('change', sync);
    sync();
  }

  function initPublishScheduleUI(root) {
    if (!root) return;
    root.querySelectorAll('[data-lf-publish-schedule]').forEach(bindPublishTimingSelect);
  }

  function populateScopeChecklist(listEl, rows, labelKey, force) {
    if (!listEl) return;
    var filterEl = listEl.closest('[data-lf-scope-filter]');
    var inputName = listEl.getAttribute('data-input-name') || 'lf_ai_scope_service_slugs';
    var schedulePrefix = inputName.indexOf('area') !== -1 ? 'lf_service_area' : 'lf_service';
    var modeInput = filterEl ? filterEl.querySelector('[data-lf-scope-mode]') : null;
    var mode = (modeInput && modeInput.value) ? modeInput.value : (listEl.getAttribute('data-scope-mode') || 'all');
    var hasExisting = listEl.querySelectorAll('.lf-scope-filter__item').length > 0;
    if (!force && hasExisting && rows && rows.length > 0) {
      return;
    }
    var prev = {};
    var prevSchedule = {};
    listEl.querySelectorAll('.lf-scope-filter__item').forEach(function (item) {
      var cb = item.querySelector('.lf-scope-filter__checkbox');
      if (cb && cb.checked) prev[String(cb.value || '')] = true;
      var sched = item.querySelector('[data-lf-publish-schedule]');
      if (sched) {
        var key = sched.getAttribute('data-schedule-key') || '';
        var t = sched.querySelector('[data-lf-publish-timing]');
        var d = sched.querySelector('[data-lf-publish-date]');
        if (key && t) {
          prevSchedule[key] = { timing: t.value, date: d ? d.value : '' };
        }
      }
    });
    var hadPrev = Object.keys(prev).length > 0;
    var storedSchedule = (cfg && cfg.publishSchedule) ? cfg.publishSchedule : {};
    listEl.innerHTML = '';
    var hasRows = false;
    (rows || []).forEach(function (row) {
      if (!row) return;
      var value = String(row.slug || '');
      var label = String(row[labelKey] || row.title || row.label || value);
      if (!value) return;
      hasRows = true;
      var scheduleKey = schedulePrefix + ':' + value;
      var schedStored = prevSchedule[scheduleKey] || storedSchedule[scheduleKey] || { timing: 'now', date: '' };

      var item = document.createElement('div');
      item.className = 'lf-scope-filter__item';
      item.setAttribute('data-lf-scope-item', '1');

      var checkLabel = document.createElement('label');
      checkLabel.className = 'lf-scope-filter__check';
      var input = document.createElement('input');
      input.type = 'checkbox';
      input.className = 'lf-scope-filter__checkbox';
      input.name = inputName + '[]';
      input.value = value;
      if (mode === 'all') {
        input.checked = true;
      } else if (mode === 'none') {
        input.checked = false;
      } else {
        input.checked = hadPrev ? !!prev[value] : false;
      }
      var span = document.createElement('span');
      span.className = 'lf-scope-filter__label';
      span.textContent = label;
      checkLabel.appendChild(input);
      checkLabel.appendChild(span);
      item.appendChild(checkLabel);
      item.appendChild(buildPublishScheduleNode(scheduleKey, schedStored));
      listEl.appendChild(item);
    });
    if (!hasRows) {
      var empty = document.createElement('p');
      empty.className = 'lf-scope-filter__empty';
      empty.textContent = (cfg.strings && cfg.strings.scopeFilterNoOptions)
        ? cfg.strings.scopeFilterNoOptions
        : 'No items for this project.';
      listEl.appendChild(empty);
    } else {
      listEl.setAttribute('data-scope-mode', mode);
      initPublishScheduleUI(listEl);
    }
    if (filterEl) {
      bindScopeFilter(filterEl);
      if (filterEl.open === false && hasRows) {
        filterEl.open = true;
      }
    }
  }

  function populateMultiSelect(selectEl, rows, labelKey, force) {
    if (!selectEl) return;
    if (selectEl.classList && selectEl.classList.contains('lf-scope-filter__list')) {
      populateScopeChecklist(selectEl, rows, labelKey, force);
      return;
    }
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
    selectEl.disabled = false;
  }

  function fetchScopePreviewForRecord(record, force) {
    if (!record || !record.id || !cfg || !cfg.ajaxUrl || !cfg.nonce) return;
    if (!force && scopePreviewRecordId === record.id) {
      return;
    }
    var svcSelect = document.getElementById('lf-ai-scope-service-slugs');
    var areaSelect = document.getElementById('lf-ai-scope-area-slugs');
    if (!svcSelect && !areaSelect) return;
    scopePreviewRecordId = record.id;
    setStatus('Loading scope preview…', 'info');
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
        if (!payload || !payload.success || !payload.data) {
          var msg = (payload && payload.data && payload.data.message) ? payload.data.message : 'Scope preview failed.';
          setStatus(msg, 'error');
          return;
        }
        populateMultiSelect(svcSelect, payload.data.services || [], 'title', !!force);
        populateMultiSelect(areaSelect, payload.data.service_areas || [], 'label', !!force);
        var meta = payload.data.meta || {};
        var version = meta.theme_version ? String(meta.theme_version) : '';
        var src = meta.services_source ? String(meta.services_source) : '';
        var svcCount = (typeof meta.services_count === 'number') ? meta.services_count : null;
        var areasCount = (typeof meta.areas_count === 'number') ? meta.areas_count : null;
        var sOk = (meta.sitemaps_ok === true);
        var sErr = meta.sitemaps_error ? String(meta.sitemaps_error) : '';
        var sRows = (typeof meta.sitemaps_rows === 'number') ? meta.sitemaps_rows : null;
        var sSpecs = (typeof meta.sitemaps_specs === 'number') ? meta.sitemaps_specs : null;
        var sFound = (typeof meta.sitemaps_services_found === 'number') ? meta.sitemaps_services_found : null;
        var sFallback = (meta.sitemaps_services_fallback_used === true);

        var parts = [];
        if (version) parts.push('theme ' + version);
        if (typeof svcCount === 'number') parts.push('services ' + svcCount);
        if (typeof areasCount === 'number') parts.push('areas ' + areasCount);
        if (typeof sFound === 'number') parts.push('sitemaps services ' + sFound);

        var msg = parts.length ? ('Scope preview loaded (' + parts.join(', ') + ').') : 'Scope preview loaded.';
        if (sOk && typeof sRows === 'number' && typeof sSpecs === 'number') {
          msg += ' (Sitemaps rows ' + sRows + ', specs ' + sSpecs + (sFallback ? ', fallback used' : '') + ').';
        } else if (!sOk) {
          msg += ' (Sitemaps fetch failed' + (sErr ? (': ' + sErr) : '') + ').';
        }
        if (src && src !== 'airtable') {
          msg += ' Services list came from fallback (' + src + ').';
          setStatus(msg, 'warn');
        } else {
          setStatus(msg, 'success');
        }
      })
      .catch(function () {
        setStatus('Scope preview failed (network error).', 'error');
      });
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

  function setActionButtonsDisabled(disabled) {
    if (primaryBuildBtn) primaryBuildBtn.disabled = disabled;
    if (secondaryGenerateBtn) secondaryGenerateBtn.disabled = disabled;
  }

  function updatePrimaryState() {
    var canAct = hasAirtableSelection();
    if (!canAct) {
      setActionButtonsDisabled(true);
      setPrimaryStatus('Select an Airtable project to continue.', 'info');
      return;
    }
    setActionButtonsDisabled(false);
    if (secondaryGenerateBtn && cfg.scope && cfg.scope.hasTargets === false) {
      secondaryGenerateBtn.disabled = true;
    }
    var readyMsg = (cfg.strings && cfg.strings.readyBuild) ? cfg.strings.readyBuild : 'Ready to build from Airtable.';
    setPrimaryStatus(readyMsg, 'success');
    if (secondaryGenerateBtn && secondaryGenerateBtn.disabled && cfg.strings && cfg.strings.scopeNoTargets) {
      setPrimaryStatus(cfg.strings.scopeNoTargets, 'warn');
    }
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

  function handleAjaxRun(options) {
    if (!selectedRecord || !selectedRecord.id) return;
    var action = options.action;
    var statusMsg = options.statusMsg;
    var successMsg = options.successMsg;
    var useHomepageConfirm = !!options.useHomepageConfirm;
    if (useHomepageConfirm && !confirmHomepageOnlyIfNeeded()) {
      updatePrimaryState();
      return;
    }
    setActionButtonsDisabled(true);
    setStatus(statusMsg, 'info');
    setPrimaryStatus(statusMsg, 'info');
    var body = new URLSearchParams({
      action: action,
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
          var msg = payload && payload.data && payload.data.message ? payload.data.message : (options.errorMsg || 'Request failed.');
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
        setStatus(successMsg, 'success');
        setPrimaryStatus(successMsg, 'success');
        updatePrimaryState();
      })
      .catch(function () {
        var failMsg = options.errorMsg || 'Request failed.';
        setStatus(failMsg, 'error');
        setPrimaryStatus(failMsg, 'error');
        updatePrimaryState();
      });
  }

  function buildSiteFromRecord() {
    handleAjaxRun({
      action: 'lf_ai_airtable_build_site',
      statusMsg: (cfg.strings && cfg.strings.building) ? cfg.strings.building : 'Building site from Airtable…',
      successMsg: 'Site built. Opening manifest…',
      errorMsg: 'Site build failed.'
    });
  }

  function generateFromRecord() {
    handleAjaxRun({
      action: 'lf_ai_airtable_generate',
      statusMsg: (cfg.strings && cfg.strings.generating) ? cfg.strings.generating : 'Generating with orchestrator…',
      successMsg: 'Generation queued.',
      errorMsg: 'Generation failed.',
      useHomepageConfirm: true
    });
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

  if (primaryBuildBtn) {
    primaryBuildBtn.addEventListener('click', function () {
      if (!hasAirtableSelection()) {
        updatePrimaryState();
        setPrimaryStatus('Select an Airtable project to continue.', 'error');
        return;
      }
      setProgress(15, 'Building…');
      buildSiteFromRecord();
    });
  }

  if (secondaryGenerateBtn) {
    secondaryGenerateBtn.addEventListener('click', function () {
      if (!hasAirtableSelection()) {
        updatePrimaryState();
        setPrimaryStatus('Select an Airtable project to continue.', 'error');
        return;
      }
      setProgress(5, 'Queued…');
      generateFromRecord();
    });
  }

  (function initScopePanel() {
    var form = document.getElementById('lf-scope-form');
    if (!form) return;

    var pageTypeIds = [
      'lf_ai_gen_homepage',
      'lf_ai_gen_services',
      'lf_ai_gen_service_areas',
      'lf_ai_gen_core_pages',
      'lf_ai_gen_blog_posts',
      'lf_ai_gen_projects'
    ];

    function byId(id) {
      return document.getElementById(id);
    }

    function setPageTypes(on) {
      pageTypeIds.forEach(function (id) {
        var el = byId(id);
        if (el) el.checked = !!on;
      });
      syncScopeSectionVisibility();
    }

    form.addEventListener('click', function (e) {
      var target = e.target;
      if (!target || !target.closest) return;
      var allBtn = target.closest('[data-lf-scope-all]');
      var noneBtn = target.closest('[data-lf-scope-none]');
      var presetBtn = target.closest('[data-lf-scope-preset]');
      if (allBtn) {
        e.preventDefault();
        setScopeFilterChecks(allBtn.closest('[data-lf-scope-filter]'), true);
        return;
      }
      if (noneBtn) {
        e.preventDefault();
        setScopeFilterChecks(noneBtn.closest('[data-lf-scope-filter]'), false);
        return;
      }
      if (presetBtn) {
        e.preventDefault();
        var preset = presetBtn.getAttribute('data-lf-scope-preset');
        if (preset === 'everything') {
          setPageTypes(true);
          document.querySelectorAll('[data-lf-scope-filter]').forEach(function (filterEl) {
            setScopeFilterChecks(filterEl, true);
          });
        } else if (preset === 'homepage-only') {
          setPageTypes(false);
          var home = byId('lf_ai_gen_homepage');
          if (home) home.checked = true;
          document.querySelectorAll('[data-lf-scope-filter]').forEach(function (filterEl) {
            setScopeFilterChecks(filterEl, false);
          });
        }
      }
    });

    form.addEventListener('change', function (e) {
      var t = e.target;
      if (t && pageTypeIds.indexOf(t.id) !== -1) {
        syncScopeSectionVisibility();
      }
    });

    form.addEventListener('submit', function () {
      document.querySelectorAll('[data-lf-scope-filter]').forEach(syncScopeModeOnSubmit);
    });

    syncScopeSectionVisibility();
    initPublishScheduleUI(form);
  })();

  initPublishScheduleUI(document.getElementById('lf-scope-form'));

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
    if (hasAirtableUI) {
      fetchScopePreviewForRecord(storedSelection, true);
    }
  }
  updatePrimaryState();

  initScopeFilters();

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
