# MVP Build — Change Log (for rollback safety)

Purpose: record every change made during the MVP sprint so we can trace/roll back
a specific step without losing the rest. We are NOT rolling back wholesale — this
is an MVP, not public for a couple more versions — so the log is for surgical fixes.

Rollback primitives:
- Branch: `mvp-build` (all sprint work lives here; `main` is the pre-sprint baseline).
- Checkpoint commit `60741f0` = partial agent-team output (64 files, php-lint clean, unverified).
- To see a step's diff: `git show <sha>`. To revert one file to baseline: `git checkout main -- <path>`.
- Pre-sprint `.beads` (incl. dolt store) backed up at `.beads-backup-20260613/`.

## Conventions for this sprint (after the parallel-write failure)
- **Write files SEQUENTIALLY, one area at a time** — the first parallel run caused
  IDE save-conflicts (agents wrote files the user had open) and 4 packet failures.
- **Log each change here** with: what file, why, and how to undo.
- **Check LOGIC first** — DB is down this session, so correctness is reviewed
  statically (php -l, ng build, route/method/column wiring) not at runtime.

---

## Log

### [2026-06-13] STEP 0 — Beads restore + tracking
- Re-init beads to sqlite backend (dolt unreadable; see learnings). 23 issues restored.
- Created `.claude/todos/mvp-sprint.json` (canonical) + 5 beads issues (nez/5gk/4v7/wl7/c9x).
- Undo: restore `.beads-backup-20260613/` over `.beads/`.

### [2026-06-13] STEP 1 — design.md/ARCHITECTURE.md reconciliation (pre-agent)
- design.md: folded owner decisions O1–O24 (Round 4). history.md: session 19 entry.
- Undo: `git checkout main -- design.md history.md`.

### [2026-06-13] STEP 2 — Agent-team checkpoint `60741f0` (PARTIAL, unverified)
New files (landed, php-lint clean):
- backend Controllers: Admin/ConfigurationController, Admin/OversightController,
  Client/BacklogController, Client/ProofFlagController, Client/WalletController
- backend Models: SystemConfiguration, WalletEntry
- backend Services: PiiMaskingService
- migrations: 000001 thread cols on conversations/messages, 000010 collaborators.role→string,
  000020 ads.adset_id nullable, 000030 spaces.calendar_synced_at, 000040 wallet_entries,
  000050 system_configurations
Modified (shared + feature files): routes/api.php, Models/User|Booking|Collaborator|Conversation|Message|Ticket,
  seeders (DatabaseSeeder, RolePermissionSeeder), several Client/Provider/Payments/Shared controllers,
  IcalSyncService, several Angular components/models, ARCHITECTURE.md.
- Status: 4 of N implement packets FAILED mid-run → some wiring may be incomplete.
- Undo whole step: `git revert 60741f0` (or `git checkout main -- <file>` per file).

### [2026-06-13] STEP 3 — Logic verification (DONE)
Static verification of the checkpoint (DB is DOWN — no runtime test possible):
- `php -l` on all changed/new backend PHP: CLEAN (no syntax errors).
- routes/api.php: 95 routes, ALL resolve to existing Controller@method (incl. aliased imports).
- Model wiring CONFIRMED present: WalletEntry::balanceFor, SystemConfiguration::setMany,
  Booking.config_snapshot cast, Ticket polymorphic ticketable()/assignedTo()/fillable,
  TicketMessage.is_internal, Conversation client()/provider()/space(), Message.sender(),
  proofs.notes, tickets.priority + polymorphic columns.
- Logic spot-checks PASSED: ProofFlagController auto-ticket (C09), PiiMaskingService render-time
  mask + space-address whitelist + privileged bypass (C06), OversightController eagle-eye no-filter
  reads incl is_internal (C11), ConfigurationController apply-scope new_only|all + in-flight
  snapshot patch (C12), PaymentController idempotent wallet refund/payout via firstOrCreate (C10).
- `ng build` (development): SUCCESS, exit 0, 42s, all new components bundled+routed
  (oversight, wallet, config, collaborator-role helper).
- C02 collaborator subroles installator/publicist/manager: backend validation + Collaborator::ROLES
  + permission map — DONE. C04 ticket-from-object (ticketable_type/id payload) — DONE.

GAP FOUND (likely a failed packet): C03/C13 FRONTEND — space-search.component.ts only tracks a
single `selectedSpace` and has NO multi-accumulate / "add to campaign" wiring, though the backend
backlog flow is complete (POST campaigns/{c}/backlog, GET .../orphans, POST .../adsets/move).

### [2026-06-13] STEP 4 — Build C03/C13 frontend accumulate->campaign flow (DONE)
- Edited ONLY frontend/src/app/features/client/spaces/space-search.component.ts (sequential, no agents).
- Added: selection basket signal, per-card "+ Add to selection" toggle, a selection bar with
  "Add to new campaign" (prompts name -> POST /client/campaigns -> POST .../backlog -> navigate to
  campaign) and "Add to existing campaign" (loads /client/campaigns -> picker -> POST .../backlog),
  plus Clear. Wires the already-complete backend backlog/orphan endpoints.
- Verified: `ng build` SUCCESS (space-search chunk 28kB -> 38.5kB, compiles clean).
- Undo: `git checkout main -- frontend/src/app/features/client/spaces/space-search.component.ts`.

### [2026-06-13] STEP 5 — Cleanup + docs (DONE)
- Removed `.claude/plans/design-md-reconciliation-questions.md` (all decisions folded into design.md;
  scrubbed its 2 dangling refs in design.md). Decision history preserved in git (main) + history.md.
- Added 4 entries to CLAUDE.md "Learnings / Common Mistakes" (beads/CGO, parallel-write conflicts,
  OneDrive ENOENT, PG-down).
- Closed beads wl7 (doc sync). Updated `.claude/todos/mvp-sprint.json`.

## OUTSTANDING (needs the DB up to verify at runtime)
- PostgreSQL is DOWN (port 5434 refused). Cannot run `php artisan migrate` / seed / endpoint tests.
- TO FINISH: start PG17 (or Docker), then: `cd backend && php artisan migrate` (7 new migrations are
  additive/alters), `php artisan db:seed` if needed, then exercise the 13 flows. Runtime behaviour of
  the agent-written controllers is UNVERIFIED until then.
