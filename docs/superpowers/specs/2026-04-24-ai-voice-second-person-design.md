# AI Voice Consistency — 2nd Person (“you”) with exceptions

Date: 2026-04-24

## Goal

Ensure AI-generated and AI-edited site copy consistently reads as the business speaking directly to the customer using **2nd person voice**:

- Customer: **you / your**
- Business: **we / our**

Avoid mixed POV inside a section/page (e.g. “we help you…” followed by “homeowners should…”).

## Scope

Apply across:

- Frontend editor AI section rewrites (inline AI assistant)
- AI Studio / orchestrator payload generation (n8n-driven generation)

## Exceptions (Option B)

Allow a more narrative tone for:

- **About Us**
- **Reviews**

These pages may use occasional third-person phrases (e.g., “homeowners in the area…”) where it reads naturally, but should still **prefer** “you/your” when possible.

## Proposed enforcement

### 1) Frontend editor prompt (inline AI edits)

Update system prompt rules to include:

- Always address the reader as **you/your**
- Speak as the business using **we/our**
- Avoid “homeowners/customers/they/the customer” phrasing
- Do not change titles/H1/URLs etc (existing guardrails remain)

### 2) AI Studio / orchestrator payload prompts

Where we build blueprint instructions/system rules for the orchestrator, add:

- Global default: enforce 2nd-person voice
- Page-type exception: About/Reviews allow narrative tone but should avoid “switching voices” within a paragraph/section

Implementation detail: the payload should carry a deterministic flag or instruction string derived from page type (e.g., `voice_mode=direct` vs `voice_mode=narrative_allowed`) so n8n prompts can remain consistent.

## Success criteria

After generating or rewriting content:

- No mixed POV inside a section (no “you…” + “homeowners…” mismatch).
- Most sections read as direct, customer-facing copy.
- About/Reviews can be slightly more narrative but should not fully switch into detached 3rd-person voice.

## Non-goals

- Does not address visual/UX issues captured in screenshots (menu behavior, section deletion, process styling, blog title uniqueness, etc.). Those should be triaged and tracked separately.

