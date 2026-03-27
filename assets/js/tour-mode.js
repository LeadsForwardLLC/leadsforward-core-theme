(() => {
  if (!window.LFTourMode || !window.LFTourMode.enabled || !window.LFTourMode.canManage) {
    return;
  }

  const cfg = window.LFTourMode;
  const storageBase = `${cfg.storageKey || "lf_tour_mode"}:${cfg.area}:${cfg.page || "default"}`;
  const progressKey = `${storageBase}:progress`;

  const trackSets = {
    admin: {
      quick: {
        label: "Quick Start",
        description: "Fast walkthrough of the highest-impact controls.",
        steps: {
          default: [
            {
              selector: "#toplevel_page_lf-ops",
              title: "LeadsForward Hub",
              body: "All workflow-critical controls live under this menu.",
              why: "Keeps your team in one operating lane."
            },
            {
              selector: '#adminmenu a[href="admin.php?page=lf-ops"]',
              title: "Website Manifester",
              body: "Run generation jobs and monitor callbacks.",
              why: "This drives full-site content execution."
            },
            {
              selector: '#adminmenu a[href="admin.php?page=lf-homepage-settings"]',
              title: "Homepage Settings",
              body: "Tune section-level content and ordering.",
              why: "Homepage quality drives conversion and local SEO trust."
            }
          ],
          "lf-ops": [
            {
              selector: 'button[name="lf_ai_studio_generate_full"]',
              title: "Generate Full Site",
              body: "Runs your full manifester pipeline.",
              why: "Use this for production-ready regeneration."
            },
            {
              selector: 'button[name="lf_ai_studio_audit"]',
              title: "Run Audit",
              body: "Checks missing fields and quality gaps after generation.",
              why: "Audit catches breakages before publish."
            },
            {
              selector: 'input[name="lf_dev_reset_confirm"]',
              title: "Reset Safely",
              body: "Type RESET and confirm to reset content only.",
              why: "Lets you re-run builds while keeping global integrations."
            }
          ]
        }
      },
      daily: {
        label: "Daily Workflow",
        description: "Best-practice sequence for regular team operations.",
        steps: {
          "lf-ops": [
            {
              selector: "h1",
              title: "Start in Manifester",
              body: "Set scope and run full generation or homepage refresh.",
              why: "Consistent entry point reduces mistakes."
            },
            {
              selector: 'button[name="lf_ai_studio_generate"]',
              title: "Homepage Refresh",
              body: "Use this for fast iteration on local page updates.",
              why: "Avoids unnecessary full-site runs."
            },
            {
              selector: 'button[name="lf_ai_studio_generate_full"]',
              title: "Full-Site Generation",
              body: "Run before milestone reviews or staging validation.",
              why: "Keeps all selected pages aligned in tone and schema."
            },
            {
              selector: 'button[name="lf_ai_studio_audit"]',
              title: "Post-Run QA",
              body: "Always run audit after generation.",
              why: "Flags content and structure problems early."
            }
          ],
          "lf-global": [
            {
              selector: '#lf_ai_studio_webhook_global',
              title: "Webhook Integrity",
              body: "Confirm webhook URL targets your active n8n workflow.",
              why: "Wrong endpoint causes silent run failures."
            },
            {
              selector: '#lf_ai_studio_secret_global',
              title: "Shared Secret",
              body: "Keep this synced with n8n auth header.",
              why: "Auth mismatches break callbacks."
            },
            {
              selector: 'button[form="lf-reviews-sync-form"]',
              title: "Reviews Sync",
              body: "Refresh proof signals from Airtable.",
              why: "Fresh trust content improves conversion."
            }
          ]
        }
      },
      troubleshoot: {
        label: "Troubleshooting",
        description: "Diagnostic path for failed runs and callback errors.",
        steps: {
          "lf-ops": [
            {
              selector: "h1",
              title: "Pinpoint Failure Stage",
              body: "Check generation status, recent errors, and callback responses.",
              why: "You need stage-level diagnosis before any fix."
            },
            {
              selector: 'button[name="lf_ai_studio_audit"]',
              title: "Re-run Audit",
              body: "Use audit output to identify missing section/field mappings.",
              why: "Audit gives deterministic failure clues."
            }
          ],
          "lf-global": [
            {
              selector: '#lf_ai_studio_webhook_global',
              title: "Verify Endpoint",
              body: "Ensure n8n production path and this webhook are aligned.",
              why: "Path collisions and stale endpoints are common blockers."
            },
            {
              selector: '#lf_ai_studio_secret_global',
              title: "Verify Secret",
              body: "Confirm Bearer token value matches n8n credentials.",
              why: "Secret mismatch returns callback authentication errors."
            }
          ],
          default: [
            {
              selector: "#toplevel_page_lf-ops",
              title: "Return to Ops Hub",
              body: "Use the LeadsForward menu as your diagnostic base.",
              why: "Centralized triage shortens fix cycles."
            }
          ]
        }
      }
    },
    frontend: {
      quick: {
        label: "Frontend QA",
        description: "Validate visible quality before signoff.",
        steps: {
          default: [
            {
              selector: "header, .site-header",
              title: "Header and CTA",
              body: "Check branding consistency and CTA clarity.",
              why: "First-screen trust impacts lead conversion."
            },
            {
              selector: "main",
              title: "Section Quality",
              body: "Review flow, hierarchy, and local relevance.",
              why: "Page structure impacts both users and search engines."
            },
            {
              selector: "footer, .site-footer",
              title: "Footer Trust Layer",
              body: "Verify legal links, NAP, and supporting navigation.",
              why: "Footer consistency improves legitimacy and crawl paths."
            }
          ]
        }
      }
    }
  };

  function getTrackMap() {
    return trackSets[cfg.area] || trackSets.frontend;
  }

  function getTrackSteps(trackId) {
    const track = getTrackMap()[trackId];
    if (!track) return [];
    const byPage = track.steps[cfg.page] || track.steps.default || [];
    return byPage.filter((step) => document.querySelector(step.selector));
  }

  const root = document.createElement("div");
  root.className = "lf-tour-root";
  root.innerHTML = `
    <div class="lf-tour-backdrop" hidden></div>
    <div class="lf-tour-card" hidden>
      <div class="lf-tour-card__title"></div>
      <div class="lf-tour-card__body"></div>
      <div class="lf-tour-card__why" hidden></div>
      <div class="lf-tour-card__progress"></div>
      <div class="lf-tour-card__actions">
        <button type="button" class="lf-tour-btn lf-tour-btn--ghost" data-action="reset">Reset</button>
        <button type="button" class="lf-tour-btn" data-action="prev">Back</button>
        <button type="button" class="lf-tour-btn" data-action="next">Next</button>
        <button type="button" class="lf-tour-btn lf-tour-btn--ghost" data-action="close">Close</button>
      </div>
    </div>
    <button type="button" class="lf-tour-launcher">Guided Tour</button>
  `;
  document.body.appendChild(root);

  const backdrop = root.querySelector(".lf-tour-backdrop");
  const card = root.querySelector(".lf-tour-card");
  const titleEl = root.querySelector(".lf-tour-card__title");
  const bodyEl = root.querySelector(".lf-tour-card__body");
  const whyEl = root.querySelector(".lf-tour-card__why");
  const progressEl = root.querySelector(".lf-tour-card__progress");
  const launcher = root.querySelector(".lf-tour-launcher");
  const btnReset = root.querySelector('[data-action="reset"]');
  const btnPrev = root.querySelector('[data-action="prev"]');
  const btnNext = root.querySelector('[data-action="next"]');
  const btnClose = root.querySelector('[data-action="close"]');

  const tracks = getTrackMap();
  const trackIds = Object.keys(tracks);
  if (!trackIds.length) return;

  let currentTrackId = "";
  let steps = [];
  let index = 0;
  let activeTarget = null;

  function clearHighlight() {
    if (activeTarget) activeTarget.classList.remove("lf-tour-highlight");
    activeTarget = null;
  }

  function placeCardNear(el) {
    const rect = el.getBoundingClientRect();
    const cardRect = card.getBoundingClientRect();
    let top = rect.bottom + 12;
    let left = rect.left;

    if (top + cardRect.height > window.innerHeight - 12) {
      top = rect.top - cardRect.height - 12;
    }
    if (top < 12) top = 12;
    if (left + cardRect.width > window.innerWidth - 12) {
      left = window.innerWidth - cardRect.width - 12;
    }
    if (left < 12) left = 12;

    card.style.top = `${Math.round(top + window.scrollY)}px`;
    card.style.left = `${Math.round(left + window.scrollX)}px`;
  }

  function saveProgress(done) {
    try {
      localStorage.setItem(
        progressKey,
        JSON.stringify({
          track: currentTrackId,
          index,
          done: Boolean(done),
          at: Date.now()
        })
      );
    } catch (e) {
      // ignore storage failures
    }
  }

  function renderTrackChooser(lastProgress) {
    clearHighlight();
    titleEl.textContent = "Choose a tour track";
    const lines = [
      '<div class="lf-tour-tracks">',
      ...trackIds.map((id) => {
        const track = tracks[id];
        const count = getTrackSteps(id).length;
        return `<button type="button" class="lf-tour-track" data-track="${id}">
          <strong>${track.label}</strong>
          <span>${track.description}</span>
          <em>${count} steps on this page</em>
        </button>`;
      }),
      "</div>"
    ];
    if (lastProgress && lastProgress.track && getTrackSteps(lastProgress.track).length) {
      lines.push(`<p class="lf-tour-resume">Resume available: <strong>${tracks[lastProgress.track]?.label || "Previous track"}</strong>.</p>`);
      lines.push('<button type="button" class="lf-tour-btn" data-action="resume">Resume previous</button>');
    }
    bodyEl.innerHTML = lines.join("");
    whyEl.hidden = true;
    progressEl.textContent = "Pick a track to begin.";
    btnReset.hidden = true;
    btnPrev.hidden = true;
    btnNext.hidden = true;
    btnClose.textContent = "Close";
  }

  function renderStep() {
    clearHighlight();
    const step = steps[index];
    if (!step) {
      renderTrackChooser(readProgress());
      return;
    }
    const target = document.querySelector(step.selector);
    if (!target) return;

    activeTarget = target;
    activeTarget.classList.add("lf-tour-highlight");
    activeTarget.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });

    titleEl.textContent = step.title;
    bodyEl.textContent = step.body;
    if (step.why) {
      whyEl.hidden = false;
      whyEl.textContent = `Why this matters: ${step.why}`;
    } else {
      whyEl.hidden = true;
      whyEl.textContent = "";
    }
    progressEl.textContent = `${tracks[currentTrackId]?.label || "Tour"} - Step ${index + 1} of ${steps.length}`;

    btnReset.hidden = false;
    btnPrev.hidden = false;
    btnNext.hidden = false;
    btnPrev.disabled = index === 0;
    btnNext.textContent = index === steps.length - 1 ? "Finish" : "Next";
    btnClose.textContent = "Close";

    saveProgress(false);
    requestAnimationFrame(() => placeCardNear(target));
  }

  function startTrack(trackId, startIndex) {
    const selected = getTrackSteps(trackId);
    if (!selected.length) {
      return;
    }
    currentTrackId = trackId;
    steps = selected;
    index = Math.max(0, Math.min(startIndex || 0, steps.length - 1));
    renderStep();
  }

  function readProgress() {
    try {
      const raw = localStorage.getItem(progressKey) || "";
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") return null;
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function clearProgress() {
    try {
      localStorage.removeItem(progressKey);
    } catch (e) {
      // ignore storage failures
    }
  }

  function openTour() {
    backdrop.hidden = false;
    card.hidden = false;
    launcher.hidden = true;
    card.style.top = `${Math.round(window.scrollY + 24)}px`;
    card.style.left = `${Math.round(window.scrollX + 24)}px`;
    renderTrackChooser(readProgress());
  }

  function closeTour(markDone) {
    clearHighlight();
    backdrop.hidden = true;
    card.hidden = true;
    launcher.hidden = false;
    if (markDone) {
      saveProgress(true);
    }
  }

  launcher.addEventListener("click", openTour);
  card.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const trackId = target.getAttribute("data-track");
    if (trackId) {
      startTrack(trackId, 0);
      return;
    }
    const action = target.getAttribute("data-action");
    if (action !== "resume") return;
    const progress = readProgress();
    if (!progress || !progress.track) return;
    startTrack(progress.track, Number.isFinite(progress.index) ? progress.index : 0);
  });

  btnReset.addEventListener("click", () => {
    clearProgress();
    currentTrackId = "";
    steps = [];
    index = 0;
    renderTrackChooser(null);
  });
  btnPrev.addEventListener("click", () => {
    if (index > 0) {
      index -= 1;
      renderStep();
    }
  });
  btnNext.addEventListener("click", () => {
    if (index < steps.length - 1) {
      index += 1;
      renderStep();
      return;
    }
    closeTour(true);
  });
  btnClose.addEventListener("click", () => closeTour(true));
  backdrop.addEventListener("click", () => closeTour(true));
  window.addEventListener("resize", () => {
    if (!card.hidden && activeTarget) placeCardNear(activeTarget);
  });
  if (readProgress()?.done !== true) openTour();
})();
