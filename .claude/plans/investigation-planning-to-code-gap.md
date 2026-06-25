# Investigation Line: Why Planning Doesn't Reach UI/Backend (2026-06-24)

**Trigger:** Owner saw the client-side "Collaborators" panel and suspected it was UI built
*without* basis in design.md / ARCHITECTURE.md / cases.md — and that the same disconnect
prevents planning from reaching real UI code and backend endpoints in general.

**Method:** 4-agent forensic team (frontend trace, planning-docs trace, backend trace,
generalization probe over 9 features). Read-only. Evidence is file:line throughout.

---

## TL;DR — the premise is half-wrong, the worry is half-right

- **"Collaborators" is NOT orphan/unplanned UI.** It is specified extensively (design.md:22,
  76-100, 510-517, 563-566; cases.md:36; platform-graph.md), has a real backend
  (`CollaboratorController` + `collaborators` table + 3 RBAC-gated routes, api.php:78-80),
  and the client UI calls those endpoints for real
  (`campaign-detail.component.ts:385,609,628`). It is a **fully-traced feature**, not a phantom.
- **But the systemic gap the owner senses is real** — it just doesn't look like phantom panels
  or mock data. Almost nothing in this frontend is mocked; ~30 components call real endpoints.
  The disconnect shows up as **three different failure modes** (below), and Collaborators itself
  exhibits one of them (spec drift: account-scoped design vs campaign-scoped build).

---

## What actually happens to "Collaborators" specifically

| Aspect | Spec says | Code does | Verdict |
|---|---|---|---|
| Exists at all | design.md §Collaborator Roles | 3 routes + controller + table + UI form | ✅ built |
| Scope | **account-wide**, across all campaigns/spaces (design.md:79-80, 563-566; owner decision 2026-06-20) | **campaign-scoped** — `collaborators.campaign_id` FK | ❌ drift |
| Roles | installator / publicist / manager | stored + validated correctly (Collaborator.php) | ✅ |
| Role shown in list | implied | UI renders name+email but **never the role** (campaign-detail ~290) | ❌ UX gap |
| Status (pending/accepted/revoked) | invitation lifecycle | column exists; UI ignores it | ❌ UX gap |
| Provider-side collaborators | both ecosystems (platform-graph.md:11-15,125) | only `Client\CollaboratorController` exists | ❌ unbuilt |
| Under a "Configurations" nav tab | design.md:565, platform-graph.md:123 | embedded at bottom of campaign-detail instead | ⚠️ partial |
| Dead code | — | `collaborator-role.helper.ts` defined, imported nowhere | 🧹 |

So the panel the owner is staring at is **real and working at the CRUD level**, but it embodies
the systemic pattern in miniature: built end-to-end as a campaign CRUD resource, while the
*spec-specific* requirements (account scope, role display, status, provider side, nav placement)
silently fell away.

---

## The general pattern (probe over 9 features)

| Feature | docs | UI | backend | wired | gap type |
|---|---|---|---|---|---|
| Campaign create/edit | ✅ | ✅ | ✅ | ✅ | none — full trace |
| Space search + map + booking | ✅ | ✅ | ✅ | ✅ | none |
| Chat / messaging | ✅ | ✅ | ✅ | ✅ | none |
| Collaborators panel | ✅ | ✅ | ✅ | ✅ | drift (scope) |
| Adset geolocation (lat/lng/radius) | ✅ | ❌ inputs | ✅ validates | ❌ | **backend-ahead-of-UI** |
| Media-spec validation (CMYK/DPI/bleed) cases.md:26-28 | ✅ | ❌ | ⚠️ only size+type | — | **spec'd, not implemented** |
| Provider star rating cases.md:20 | ✅ | ❌ | ❌ | — | spec'd, fully missing |
| Pre-pay / waiting list cases.md:30 | ✅ | ❌ | ❌ | — | spec'd, fully missing |
| what3words lookup | ❌ | ✅ live call | ❌ | ✅ ext | **UI drift (not in docs)** |

### Three failure modes

1. **Selective implementation depth (primary).** Anything that maps cleanly to a CRUD resource
   (campaigns, spaces, bookings, chat, collaborators) got built end-to-end. Requirements buried
   as *algorithmic rules inside long prose bullets* — media-spec conformance (cases.md:26-28),
   ratings (cases.md:20), pre-pay escrow (cases.md:30) — got skipped. They live in narrative, not
   in any case-ID→route→component table, so **nothing forces them into code.**
2. **Backend-ahead-of-UI.** Columns + validation land before the matching UI input exists, leaving
   silent half-features (adset lat/long validated in `AdsetController.php:30-33`, but
   `campaign-detail.component.ts:504` posts only `name`).
3. **Doc-after-code reality.** By the owner's own "latest-decision-wins / momentum over
   perfection / ~90% compliance" rule, docs are continuously rewritten around what shipped
   (Google Maps → Leaflet+what3words, etc.). Some "gaps" are intentional deferrals, not accidents.

---

## Root cause: there is no traceability spine

The docs are **story-first and prose-dense** (cases.md user journeys → design.md subsystems →
ARCHITECTURE.md schema/routes). There is **no case-ID → endpoint → component mapping**. Features
survive the trip from doc to code *only when a human happens to model them as a CRUD resource*.
Requirements expressed as rules, secondary features, or scope qualifiers buried in paragraphs have
no carrier and evaporate. `ui-endpoint-audit.md` is the closest thing to a tracker, but it audits
*after* the fact rather than gating implementation.

---

## Recommended next steps (not yet done)

1. **Build a traceability table** (case-ID | requirement | route | component | wired? | status) and
   make it the gate — every cases.md bullet gets an ID and a row. This directly attacks failure
   mode #1 and gives the owner the "is planning reaching code?" dashboard they were reaching for.
2. **Resolve the Collaborators drift** as the worked example: migrate `campaign_id` → account scope
   (design.md:79-80), render role + status in the list, add provider-side collaborators, move under
   a Configurations nav tab, delete the dead `collaborator-role.helper.ts` or wire it in.
3. **Sweep for backend-ahead-of-UI half-features** (start with adset geolocation) and either finish
   the UI input or remove the orphan validation.
4. **Decide-and-record the prose-buried requirements** (media-spec validation, ratings, pre-pay):
   each is either a real backlog item with an ID or an explicit deferral in design.md — no silent
   middle state.

---

*Investigation by agent team, 2026-06-24. Evidence available in the four trace reports; all claims
carry file:line. See also [ui-endpoint-audit.md](ui-endpoint-audit.md) and
[platform-graph.md](platform-graph.md).*
