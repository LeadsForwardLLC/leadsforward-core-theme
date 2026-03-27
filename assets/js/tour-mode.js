(() => {
  if (!window.LFTourMode || !window.LFTourMode.enabled || !window.LFTourMode.canManage) {
    return;
  }

  const cfg = window.LFTourMode;
  const storageBase = `${cfg.storageKey || "lf_tour_mode"}:${cfg.area}:${cfg.page || "default"}`;

  const stepSets = {
    admin: {
      default: [
        { selector: "#toplevel_page_lf-ops", title: "LeadsForward Menu", body: "This is your main admin hub for manifester, settings, and operations." },
        { selector: '#adminmenu a[href="admin.php?page=lf-ops"]', title: "Website Manifester", body: "Run generation jobs, review QA status, and reset content when needed." },
        { selector: '#adminmenu a[href="admin.php?page=lf-global"]', title: "Global Settings", body: "Configure webhook, Airtable, secrets, design, and Tour Mode toggle." },
        { selector: '#adminmenu a[href="admin.php?page=lf-homepage-settings"]', title: "Homepage Builder", body: "Adjust homepage section layout, copy, and CTA controls." },
        { selector: '#adminmenu a[href="admin.php?page=lf-quote-builder"]', title: "Quote Builder", body: "Manage quote flow, fields, and downstream integrations." }
      ],
      "lf-ops": [
        { selector: "h1", title: "Website Manifester", body: "This is your primary generation dashboard." },
        { selector: 'button[name="lf_ai_studio_generate_full"]', title: "Generate Site Content", body: "Runs full-site content generation and applies updates from n8n." },
        { selector: 'button[name="lf_ai_studio_generate"]', title: "Generate Homepage", body: "Homepage-only regeneration for fast updates." },
        { selector: 'button[name="lf_ai_studio_audit"]', title: "Run Audit", body: "Audits missing fields, links, and quality gaps after generation." },
        { selector: 'input[name="lf_dev_reset_confirm"]', title: "Reset Confirmation", body: "Type RESET, check acknowledgment, then reset content-only data." }
      ],
      "lf-global": [
        { selector: "h1", title: "Global Settings", body: "Core configuration for integrations, branding, and global behavior." },
        { selector: '#lf_ai_studio_webhook_global', title: "Orchestrator Webhook", body: "Points WordPress to your n8n production webhook endpoint." },
        { selector: '#lf_ai_studio_secret_global', title: "Shared Secret", body: "Used for authenticated communication with n8n callbacks." },
        { selector: 'input[name="lf_tour_mode_admin"]', title: "Tour Mode Toggle", body: "Enable or disable this guided tour for admin users only." },
        { selector: 'button[form="lf-reviews-sync-form"]', title: "Sync Reviews", body: "Pulls reviews from Airtable using current mapping settings." }
      ]
    },
    frontend: {
      default: [
        { selector: "#wpadminbar", title: "Admin Toolbar", body: "Use quick links to jump back into LeadsForward admin pages." },
        { selector: "header, .site-header", title: "Header + Primary CTA", body: "Validate branding, menu structure, and top conversion action." },
        { selector: "main", title: "Main Content", body: "Review hero, trust, services, CTA, FAQ, and map sections for quality and relevance." },
        { selector: "footer, .site-footer", title: "Footer + Trust Info", body: "Check legal links, NAP details, and supporting internal navigation." }
      ]
    }
  };

  function getSteps() {
    const areaSets = stepSets[cfg.area] || stepSets.frontend;
    return areaSets[cfg.page] || areaSets.default || [];
  }

  function findRenderableSteps() {
    return getSteps().filter((s) => document.querySelector(s.selector));
  }

  const steps = findRenderableSteps();
  if (!steps.length) return;

  const root = document.createElement("div");
  root.className = "lf-tour-root";
  root.innerHTML = `
    <div class="lf-tour-backdrop" hidden></div>
    <div class="lf-tour-card" hidden>
      <div class="lf-tour-card__title"></div>
      <div class="lf-tour-card__body"></div>
      <div class="lf-tour-card__progress"></div>
      <div class="lf-tour-card__actions">
        <button type="button" class="lf-tour-btn" data-action="prev">Back</button>
        <button type="button" class="lf-tour-btn" data-action="next">Next</button>
        <button type="button" class="lf-tour-btn lf-tour-btn--ghost" data-action="close">Close</button>
      </div>
    </div>
    <button type="button" class="lf-tour-launcher">Start Tour</button>
  `;
  document.body.appendChild(root);

  const backdrop = root.querySelector(".lf-tour-backdrop");
  const card = root.querySelector(".lf-tour-card");
  const titleEl = root.querySelector(".lf-tour-card__title");
  const bodyEl = root.querySelector(".lf-tour-card__body");
  const progressEl = root.querySelector(".lf-tour-card__progress");
  const launcher = root.querySelector(".lf-tour-launcher");
  const btnPrev = root.querySelector('[data-action="prev"]');
  const btnNext = root.querySelector('[data-action="next"]');
  const btnClose = root.querySelector('[data-action="close"]');

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

  function renderStep() {
    clearHighlight();
    const step = steps[index];
    const target = document.querySelector(step.selector);
    if (!target) return;

    activeTarget = target;
    activeTarget.classList.add("lf-tour-highlight");
    activeTarget.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });

    titleEl.textContent = step.title;
    bodyEl.textContent = step.body;
    progressEl.textContent = `Step ${index + 1} of ${steps.length}`;

    btnPrev.disabled = index === 0;
    btnNext.textContent = index === steps.length - 1 ? "Finish" : "Next";

    requestAnimationFrame(() => placeCardNear(target));
  }

  function openTour() {
    backdrop.hidden = false;
    card.hidden = false;
    launcher.hidden = true;
    renderStep();
  }

  function closeTour(markDone) {
    clearHighlight();
    backdrop.hidden = true;
    card.hidden = true;
    launcher.hidden = false;
    if (markDone) {
      try {
        localStorage.setItem(storageBase, "done");
      } catch (e) {
        // ignore storage failures
      }
    }
  }

  launcher.addEventListener("click", openTour);
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

  let seen = "";
  try {
    seen = localStorage.getItem(storageBase) || "";
  } catch (e) {
    seen = "";
  }
  if (seen !== "done") {
    openTour();
  }
})();
