# Compliance Report — code/UI vs design.md · ARCHITECTURE.md · cases — 2026-06-15

Produced by a 6-agent read-only audit + consolidation. Verdicts: COMPLIANT / PARTIAL /
DIVERGENT (code contradicts doc) / MISSING (doc requires, code lacks) / UNDOCUMENTED.

## Headline
The code implements the original **11-table MVP + a thin overlay** (wallet ledger,
system_configurations, RBAC matrix, PII masking, iCal sync, oversight/role dashboards).
The **large design.md subsystems are mostly unbuilt at the schema level**. design.md
(dated 2026-06-20) describes a much bigger system than the MVP code — so most "gaps"
are the code being an intentional MVP subset, NOT regressions. The items that need a
decision are (a) the cheap doc-vs-doc inconsistencies, and (b) the few places where code
**contradicts an explicit owner decision**.

## A. DOC-vs-DOC inconsistencies (cheap to fix; do first)
1. **`cases-v2.md` does not exist** — only `.claude/plans/cases.md`. design.md defers
   canonical content (media-delivery spec table @design.md:259-275, ~28-row notification
   schema @design.md:332-345) to the missing file → two canonical refs point at nothing.
   FIX: rename cases.md→cases-v2.md OR repoint design.md refs to cases.md and inline the
   two tables into design.md.
2. **ARCHITECTURE.md stale counts**: says 29 migrations (30), 23 controllers (32), 77+
   routes (93). Models 19 correct.
3. **ARCHITECTURE.md collaborators enum** still `proof_uploader` only; real schema is
   varchar(20) default 'manager', roles installator/publicist/manager.
4. **ARCHITECTURE.md missing route** GET /client/invoices/{invoice}/pdf (exists + used).
5. **Booking/ad state enum mismatch**: design.md names draft/live/proof-pending/payout-held;
   code+ARCHITECTURE.md use pending/waiting_approval/confirmed/active/waiting_proof/...
   Pick one canon in design.md, reconcile both.
6. **RBAC "every route" claim** vs 3 stats-dashboard routes intentionally role-only (no
   permission middleware). Document the exception.

## B. CODE CONTRADICTS OWNER DECISION (DIVERGENT — high priority)
- **Payments still reviews proof content** — design.md:137-139 (B9) + ARCHITECTURE.md:329-333
  say Payments does NOT review proofs; the CLIENT accepts/rejects and Payments only releases
  money. But GET /payments/proofs + approve/reject are live (api.php:188-190, ProofReviewController),
  and the client accept/reject endpoint does NOT exist (only the unwired flag-mismatch).
- **Collaborators campaign-scoped, not account** — design.md wants an account_grants overlay
  (account-level); code has collaborators.campaign_id + campaigns/{campaign}/collaborators,
  and NO provider-side collaborator surface at all (providers have no campaigns).
- **Money unit**: design.md mandates integer MXN centavos everywhere; only wallet_entries uses
  centavos — bookings/payments/ads/spaces/campaigns are decimal(10,2) pesos.
- **Conversations not polymorphic** — design wants type/subject_type/subject_id + conversation_links
  + separate internal-thread rows; code is still hard-bound space_id+client_user_id.
- **Provider booking action** — design wants Approve / Reject-WITH-REASON + install queue; UI
  has Confirm/Cancel, no reason column, no install queue.

## C. MISSING SUBSYSTEMS (design requires, code lacks — these are scope decisions)
HIGH: audit_logs (immutable, per-object, admin-read) · strikes · notifications dispatcher +
~28-row event schema (Twilio mocked) · overlapping-booking EXCLUDE gist constraint + row locks ·
escrow / pre-pay waiting list / sibling cancel-refund · media-delivery spec validator (2 spec
sets + upload gate + snapshot) · admin moderation (takedown/restore/freeze + deletion guardrails).
MEDIUM: provider ratings · wallet withdraw + frozen state + clawback · display_start timestamp ·
admin write-into-thread (incognito post) + per-object eagle-eye · admin user list role-split/filters.

## D. MISSING ENDPOINTS (design implies, routes/api.php lacks)
- POST /client/wallet/withdraw
- POST /client/proofs/{proof}/accept + /reject  (client gates payout; replaces Payments review)
- POST /client/campaigns/{campaign}/rating (+GET)
- account-scoped collaborator endpoints (account_grants) + provider-side collaborators
- admin moderation: POST /admin/{spaces|ads}/{id}/takedown · /restore · /admin/users/{user}/freeze
- POST /admin/oversight/conversations/{id}/messages (incognito post)
- admin per-object oversight detail (spaces/bookings/payments/proofs/campaigns/...)
- GET /admin/audit-logs (+ per-object filter)
- Support flag endpoints (refund.flag / payout.hold-flag)
- notifications/alerts endpoints + dispatcher
- media-delivery spec template + upload-time validation
- GET /admin/users with role-split/search/filter query params

## E. CONFIRMED COMPLIANT (working as designed)
RBAC matrix + lockout-safe routes · System Configurations + apply_scope · polymorphic +
reference-less tickets · Payments money execution (idempotent wallet refund/payout) ·
multi-select basket→campaign→backlog→adset · iCal/calendar sync + help · per-booking proof
upload + radio→video rule.

## F. PARTIAL / UI-UNWIRED (backend ready, frontend not calling it)
- space-search green/red availability flag (backend returns `available`, UI ignores it)
- adset PUT, ad PUT/show, client booking show/PUT, invoice show — routes exist, no component calls them
- contextual chat: POST /conversations is never called by any component; clients can't start a chat
- calendar staleness threshold hard-coded (not from config), no last-manual-edit clock
- config apply-to-in-flight only patches proof_deadline_days
- proof-reject ticket doesn't attach the payment nor route to both Support+Payments

## TOP ACTIONS (ordered: doc fixes are cheap & first)
1. Resolve missing cases-v2.md (rename or repoint + inline media-spec & notification tables).
2. Refresh ARCHITECTURE.md (counts, collaborator enum, invoice pdf route).
3. Decide canonical booking/ad state enum in design.md; reconcile migration + ARCHITECTURE.md.
4. audit_logs table (DB-level immutable) + admin read endpoint.
5. Money → integer centavos across all money columns; payments FX columns.
6. Overlapping-booking EXCLUDE gist + btree_gist + lockForUpdate on confirm/refund/payout.
7. Re-scope collaborators campaign→account (account_grants) + provider-side surface.
8. Remove Payments proof-review (B9); add client accept/reject gating payout + wire UI.
9. Real escrow + pre-pay waiting list + atomic sibling cancel/refund + display_start.
10. notifications dispatcher + strikes + ratings.
11. admin moderation (takedown/restore/freeze) + deletion guardrails + incognito post + filtered user mgmt.
12. UI wiring: green/red flag, adset/ad/booking/invoice edit+detail, wallet withdraw, staleness from config, Approve/Reject-with-reason + install queue, start-conversation + contextual chat.
