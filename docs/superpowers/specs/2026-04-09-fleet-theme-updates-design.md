---
title: "Fleet Theme Updates (Controller + Agent) — design"
date: 2026-04-09
status: approved
---

## Goal

Enable LeadsForward to operate a “fleet” of separate WordPress installs where **theme code updates** can be pushed from a control center with **staged rollouts**, without logging into each site.

Constraints:
- Sites are **separate WP installs** (not multisite).
- Do **not** expose that sites are connected (no public UI, no visible branding, no public headers).
- Updates must be **code/config/design** only; **never overwrite saved site content** (posts/pages/CPT/meta) as part of fleet updates.
- Push happens only when an admin clicks a **“Push to selected sites”** action in wp-admin on the controller.
- Client sites **auto check-in** to controller(s) so the controller can display live status.

## Non-goals

- Managing plugins/core WP updates (theme only).
- Content synchronization between sites.
- Making controller publicly discoverable as a network hub.

---

## Architecture overview (WordPress mental model)

### Components

1) **Controller** (WordPress site)
   - Primary: `theme.leadsforward.com`
   - Secondary (allowed controller): existing monitoring control site
   - Provides:
     - Theme release registry (versions + zip URL + checksum)
     - Fleet dashboard (sites, cohorts, status, push UI)
     - Secure “push update” orchestrator

2) **Agent** (small plugin installed on each client site)
   - Stores:
     - Allowed controllers (URL + shared secret)
     - Site identity (opaque site ID), cohort tags (optional), last-seen
   - Provides:
     - **Auto check-in** (heartbeat) to controller
     - Secure endpoint to accept “install theme version X” commands
     - Install/update theme using WP upgrader APIs
     - Report results back to controller

### Update model

- Controller “pushes” by making a signed request to a client’s Agent.
- Agent downloads a specific theme zip for a specific version and installs it.
- Agent reports status back (success/failure + error).

### Security model (HMAC signed requests)

Each controller has a shared secret with the agent:
- Request includes:
  - timestamp (short TTL)
  - nonce (replay prevention)
  - payload (version, download URL, checksum)
  - HMAC signature (e.g. SHA-256)
- Agent verifies:
  - controller URL matches one of the allowed controllers
  - signature is valid for that controller secret
  - request is within TTL, nonce unused

This enables **Either controller** to push updates safely.

---

## Fleet admin UX (on controller)

### Menu

LeadsForward → **Fleet Theme Updates**

### Views

1) **Sites**
   - Table: Site name (internal), masked URL, cohort, current theme version, last check-in, health
   - Multi-select checkboxes
   - Filters:
     - cohort/group
     - “out of date”
     - “failed last update”
   - Bulk actions:
     - Push current controller version
     - Push a selected version
     - Pause updates
     - Pin version

2) **Releases**
   - List of theme versions available
   - Per-version notes (optional)
   - “Mark stable” / “pilot only” (optional)

3) **Groups/Cohorts**
   - Create group (name + set of sites)
   - Saved rollout sets (Pilot 10, Beta 50, Stable All)

### “Push” flow

Admin chooses:
- Target version:
  - default: current controller theme version
  - optionally: pick from Releases
- Target sites:
  - all sites, or
  - saved group, or
  - manual selection

Controller:
- queues jobs (don’t hammer all at once)
- shows live progress and final report

---

## Auto check-in (“heartbeat”)

Agent sends periodic check-ins to controller(s):
- minimal payload:
  - opaque site_id
  - current theme version
  - WP version / PHP version (optional)
  - last error (optional)

Controller stores and surfaces:
- last_seen timestamp
- “offline” detection if no check-in for N hours

---

## Airtable integration (optional, later)

Use Airtable as metadata source:
- site labels/client info/cohort tags
- allowed enrollment list

