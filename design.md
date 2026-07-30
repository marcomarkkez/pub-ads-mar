This document is the SINGLE canonical design reference for the whole app (API + headless
frontend). It absorbed the former ARCHITECTURE.md, cases.md, platform-graph.md, and the
useful parts of compliance-report.md (consolidated 2026-06-27) — design.md is now the one
home for schema, routes, specs, decisions, the role/object graph, and user stories.
`[owner YYYY-MM-DD]` marks an explicit owner decision; latest decision wins on conflict.
Sections 1–18 are the SPECS (kept tight); section 19 is the verbose USER STORIES.
Writing rule: state each rule ONCE in the fewest human-readable words — no redundancy,
verbosity or over-engineering. Code traceability: every file/class/method implementing a
behavior here carries a short comment naming the spec it satisfies (e.g. `design.md §7 proof`).
Feature codes: section/sub-section headers carry a `[Fxx]` tag matching the feature index in
`.claude/todos/mvp-sprint.json` → `feature_review` (e.g. `[F06]` = booking action & per-ad
go-live). Use these shared codes when referring to a feature. `[infra]`/`[dev]`/`[all]` mark
cross-cutting sections that are not a single feature.
Spec-Kanban: every spec header ALSO carries a one-line `kanban:` state tag (status/weight/
impacts/deps/ids) so this doc doubles as a live board — **read §0 first** for the notation.

== 0. Spec-Kanban (how to read this doc) ==  [dev]

Every spec header carries a one-line `kanban:` tag right under it, so this doc IS the board — no
second tool, no status duplicated elsewhere. It renders as tidy chips and parses cleanly for a
future visual-kanban UI.

Line grammar (stable — a renderer can rely on it):
  kanban: `status:VALUE` · `weight:VALUE` · `impacts:LIST` · `deps:LIST` · `ids:LIST`
  - one line, starts literally with `kanban:` (grep the board: `grep '^kanban:' design.md`).
  - each field is a backticked `key:value` chip; chips are separated by ` · ` (middot).
  - parser: take every backtick span matching `([a-z]+):([^`]*)`; a LIST is comma-separated with
    no spaces; `-` means none.

Fields:
  - status — lifecycle; NOTHING is `done` until it clears the DOUBLE gate:
      · `backlog` — specced, not started.
      · `wip`     — being implemented (code in flight).
      · `review`  — implemented AND under verification. `review` is a WEIGHTED state, not a rubber
                    stamp: cleared ONLY by BOTH (a) automated tests green on Postgres AND (b) a
                    human walkthrough of the flow. Missing either → it stays in review.
      · `done`    — verified: tests green + human-confirmed. A regression sends it back to `wip`.
  - weight — total effort incl. build + automated tests + human verification: `S | M | L | XL`.
  - impacts — features/specs this ENABLES or breaks downstream (→).
  - deps    — what must land first, upstream (←).
  - ids     — granular task ids in `mvp-sprint.json → spec_backlog` (design ↔ todos trace).

Prose vs kanban: the PROSE is the spec (what to build); the `kanban:` line is the live STATE of
that work; `[Fxx]` still tags the feature. When they disagree, fix the lagging one.

== 1. Overview & Roles ==  [F01]
kanban: `status:done` · `weight:M` · `impacts:F02,F11,F14` · `deps:-` · `ids:AD-usercrud-01,AD-rbac-02`

Web advertising-spaces marketplace: clients book provider ad spaces (billboards, screens,
radio, transit, kiosks), pay per time span, and verify display via proof. Laravel 12 (API,
headless) + Angular 18 SPA; Mexico-first (Monterrey launch), single currency MXN. The five
SYSTEM roles (`users.role` enum = `client|provider|admin|support|payments`):

- **Client** — owns campaigns→adsets→ads; searches spaces on the map, books, pays per
  campaign, reviews proof, chats with providers. No dashboard (lands on Map+Search).
- **Provider** — owns spaces (+photos, availability, pricing); approves/rejects bookings,
  uploads the PRIMARY proof. Lands on a stats dashboard.
- **Admin** — eagle-eye: sees EVERYTHING (every chat — client↔provider, Support-joined,
  internal Support↔Payments, disputes), moderates listings (takedown/restore/freeze), edits
  any object, configures System Configurations, reads the immutable audit log, manages users
  + the RBAC matrix. Reads every chat SILENTLY/incognito (🤫, read-only oversight — never
  posts as a ghost; the one chat write-power is reopening a closed chat for investigation —
  §10, UC-28). The only role above Support and Payments.
- **Support** — investigates user-reported problems on any ad/adset/campaign/space;
  near-admin: edits every NON-money object, joins client↔provider chats ANNOUNCED (📣), can
  only FLAG a refund/payout-hold (never execute), adjudicates mismatches, removes strikes
  (audited), edits provider collaborator roles. Every action audit-logged.
- **Payments** — the ONLY role that moves money ($): approves/rejects payments, executes
  refunds and payouts. Does NOT review proof CONTENT (owner 2026-06-20) and has NO content
  powers; strike accrual is NOT a Payments concern. Lands on a stats dashboard.

(Legacy `Manager\UserController` is the old 3-role MVP name for Admin — compat only.)

== 2. Role × Object Power Matrix ==  [F01,F14]
kanban: `status:review` · `weight:S` · `impacts:F09,F10,F11` · `deps:F01` · `ids:AD-rbac-02`

Rows = object, cols = role. Legend: 🟢 own CRUD · 👁 inspect (read) · ✏ edit-any · $ money
action · 🤫 silent observe · 📣 announced join · 📝 audit-logged · · = no access. Collaborator
columns split by ecosystem subrole (client: **publ**=publicist, **mgr**=manager · provider:
**inst**=installator, **sales**, **sup**=supervisor); see §3 for exact subrole capabilities.

| Object | CLIENT | COLLAB (client) | PROVIDER | COLLAB (provider) | SUPPORT | PAYMENTS | ADMIN |
|---|---|---|---|---|---|---|---|
| Campaign / Adset | 🟢 | publ:🟢 | · | · | 👁✏📝 | 👁 | 👁🤫✏📝 |
| Ad | 🟢 | publ:🟢 | 👁(theirs) | · | 👁✏📝 | 👁 | 👁🤫✏📝 |
| Space (+photo/avail) | 👁 | 👁 | 🟢 | inst:· \| sales:👁 \| sup:🟢 | 👁✏📝 | 👁 | 👁🤫✏📝 |
| Booking | 🟢 | publ:🟢 | 👁✏(theirs) | inst:· \| sales:👁 \| sup:✏ | 👁✏📝 | 👁 | 👁🤫✏📝 |
| Proof | 🟢 review+flag | publ+mgr:review+flag | 🟢 create (PRIMARY) | inst:create \| sales:· \| sup:create | 👁✏📝 (mismatch) | 👁 (no content review), $hold-on-reject | 👁🤫✏📝 |
| Payment | 👁(own) | mgr:👁 | 👁(payout) | sup:· | 👁 flag$ | 🟢 $📝 | 👁🤫 $📝 |
| Wallet / Refund | 👁(own) | mgr:👁 | · | · | flag only | $ execute | 👁🤫 $📝 |
| Invoice | 👁(own) | mgr:👁 | 👁(own) | sup:👁 | 👁✏📝 | 👁 | 👁🤫✏📝 |
| Chat (Objetos & Flags) | 🟢 own | publ:🟢 \| mgr:🟢 | 🟢 own | inst:· \| sales:🟢 \| sup:🟢 | 📣 join+flag$ 📝 | 🤫 internal only | 🤫 read-all 📝 |
<!-- [owner 2026-07-17] Payments chat-join is SILENT (🤫), not announced; Supervisor does NOT see Payments (sup:· on Payment); Supervisor CAN upload the primary proof (sup:create on Proof); Installator is provider-side only (removed from the client collab column). -->

| User / account | · | · | · | · | 👁✏📝 (no $) | · | 🟢✏📝 |
| Collaborator | 🟢(own) | · | 🟢(own) | · | 👁✏📝 (provider collab roles) | · | 👁🤫✏📝 |
| RBAC permissions | · | · | · | · | 👁 collab-roles | · | 🟢 employees |
| System config | · | · | provider-admin:🟢(own acct) | · | · | · | 🟢 global |
| Audit log | · | · | · | · | 👁 (writes) | 👁 (writes) | 👁🤫 read-all |

Separation-of-powers (load-bearing; roles defined in §1):
- A client-rejected proof AUTO-HOLDS the payment; an accepted proof makes it releasable for
  MANUAL Payments release. Strikes route Support→Admin, never Payments.
- **Chat (one primitive — §10):** every conversation, "ticket", internal money huddle, and dispute
  is ONE `chat`; a "ticket" is just a chat with a **Support** participant. Nature is DERIVED from
  participants+objects+flags (no `type` column). Chat **flags** = payment_held/refund/payout_hold/
  cancel/mismatch; Support **RAISES** money flags, Payments **EXECUTES** the money (§8) — a flag never
  grants access and the money authority is `payments.status`, never a flag (UC-22, UC-25).
- **Internal Support↔Payments chats** are membership-ACL'd; the client never sees them; Admin
  observes read-only/incognito (never posts).
- **Auto-approve**: Admin System Config toggle + amount threshold (auto-release payments under $X),
  default OFF; larger payments still need manual Payments review.
- Audit log is append-only/immutable; every staff (✏/$) action is 📝-logged.

== 3. Accounts & Collaborators ==  [F02]
kanban: `status:review` · `weight:L` · `impacts:F05,F07,F09` · `deps:F01` · `ids:AC-accounts-01,AC-collab-04,AC-grants-07`

**Account object** (owner 2026-06-25): a first-class object. Every owner user — a client or a
provider — has exactly ONE account; for MVP an account IS that single owner user (1 user = 1
account). The separate `accounts` object exists from the start so a team of multiple
owner-users can later share one account WITHOUT a schema change. [owner 2026-06-27] An EMAIL
is the account identity: exactly ONE account per email, no two accounts share an email. A
person wanting both a client and a provider account registers two different emails and
switches manually — we do NOT enforce, link, or merge them.

**Collaborators** (UC-19 owner manages; UC-20 collaborator acts in subrole scope) are invited by
email from an account-scoped Collaborators screen under the
Configurations tab. They are ACCOUNT-scoped (owner 2026-06-20, reaffirmed 2026-06-24/25): a
collaborator points to **account_id** (never a campaign or space) and, per role, sees/acts
across ALL of that account's campaigns/spaces. Enforced by a SEPARATE per-account RBAC grant
overlay (`account_grants`) evaluated AFTER the global role matrix, scoped so grants never leak
across accounts. The account owner can see any conversation a collaborator has. (DB:
`collaborators` table, `unique(account_id, email)`; campaign_id→account_id migration PENDING.)

Client-side and provider-side are TWO SEPARATE ecosystems with DIFFERENT subrole sets; the
sets share NO names and a subrole on one side is unrelated to the other.

CLIENT-side subroles:
- **Publicist** — operational: create/edit campaigns, adsets, ads; review proofs; upload
  SECONDARY proofs; chat with providers; open support chats. CANNOT see money/billing.
- **Manager** — everything the owner can do INCLUDING money and billing (the ONLY client
  collaborator that sees money).

PROVIDER-side subroles (owner 2026-06-25, confirmed 2026-06-26):
- **Installator** — can ONLY upload proofs (crews who install physical ads and photograph
  them same-day). Sees nothing about money, listings, or other topics.
- **Sales** — provider-side equivalent of Support: answers client messages for information.
  Can ONLY reply to messages/chats — CANNOT remove people, add/edit/delete spaces, or touch
  money. Purely communicational.
- **Supervisor** — can see and EDIT almost everything in the provider account EXCEPT the
  owner-only powers (cannot remove people / manage collaborators, adjust account configurations,
  DELETE spaces) AND, [owner 2026-07-17], EXCEPT money/Payments — the Supervisor does NOT see
  Payments for now (was "including money/payouts"; the owner narrowed it).

Money/billing on the CLIENT side is visible to the owner and the client Manager. On the PROVIDER
side money is owner-only for now [owner 2026-07-17: Supervisor no longer sees Payments]. Never to
Publicists, Sales, or Installators.

== 4. Core Objects & Lifecycles ==  [F04,F06,F07]
kanban: `status:review` · `weight:M` · `impacts:F05,F07,F08,F10` · `deps:F03` · `ids:SB-enum-07,CA-crud-03`

= Object hierarchy =
- **Campaign** — top user-level unit; holds a budget and one or more adsets. Lists with
  columns like total campaign value and invoice. Invoicing is per-ad but printed at campaign
  level.
- **Adset** — a PURE GROUPING LABEL with a name. No effect on price, dates, or location; it
  only orders/groups ads. Ads from multiple providers can sit in one adset.
- **Ad** — the basic publicity unit: audio (radio), image (billboard), video (big-screen),
  gif, or other. Each ad has its OWN price, time span, location, and provider. MVP = ONE file
  per ad/space; multi-file ads are FUTURE/deferred. Two gates apply to the file and BOTH must
  pass: automated upload-time validation against the space's media-delivery specs AND manual
  provider approval — a file may pass automated validation yet still be manually rejected. On
  manual rejection the provider asks for a corrected re-upload or rejects permanently; when
  rejected the client is NOT charged.
- **Space** — a provider's publicity slot. Has name, text address, lat/long, photos, pricing
  per day/month/custom span, two spec sets (physical + media-delivery), availability calendar.

= CANONICAL booking state enum =
[owner 2026-06-25] THIS enum wins; prose and code are reconciled to it (live `bookings.status`
column migration is PENDING).

`draft → waiting-approval → waiting-booking-confirmation → live → proof-pending →
proof-uploaded → (completed | rejected | cancelled)`

- **draft** — client is still assembling the booking (ad attached, not yet submitted).
- **waiting-approval** — submitted; provider must Approve or Reject.
- **waiting-booking-confirmation** — "book for later"/pre-pay: slot isn't free yet, so it
  waits in the queue (funds escrowed if pre-paid) until the calendar opens.
- **live** — approved and inside its booked display window; the ad is running.
- **proof-pending** — display has started; provider owes a proof before the deadline.
- **proof-uploaded** — provider posted the proof; awaiting the client's accept/reject.
- **completed** — client accepted the proof; payout becomes releasable.
- **rejected** — provider rejected the booking request (no charge). (A client-rejected PROOF does
  NOT re-enter this state — it auto-holds the payout and routes to Payments+Support for a manual
  decision; see §7.)
- **cancelled** — withdrawn or auto-cancelled (e.g. proof deadline missed → refund + strike).

`payout-held` is NOT a booking state — it lives on `payments.status` (Payments-only). ADS keep
their OWN SEPARATE enum (`draft|pending_approval|approved|rejected|active|paused|completed|
cancelled`) — ads and bookings are deliberately NOT unified. The client UI's three visible
legends (waiting for approval / waiting for booking confirmation / live) map onto this set.

= proofs.status enum (FOUR values) =
- **pending** — deadline running, no file uploaded yet.
- **uploaded** — provider posted the proof, awaiting the client's decision.
- **client_accepted** — client (or a collaborator, per role) approved it → payout releasable.
- **client_rejected** — client rejected or flagged a mismatch → payout AUTO-HELD + case auto-routed
  to Payments+Support; NO re-upload window; release/refund is a manual Payments+Support decision.

A missed deadline is a BOOKING outcome (auto-cancel), NOT a proof status.

= One canonical enum list =
An enum is the fixed CLOSED set of values a status column may hold; we keep a SINGLE canonical
list so DB, backend, Angular UI, and docs agree on the states — otherwise (`active` vs `live`,
`waiting_proof` vs `proof-pending`) the state machine, filters, and deadline/payout rules drift.

= Cheapest-decomposition pricing =
When a booked window straddles two of a provider's pricing tiers (e.g. per-day and per-month),
the price is the CHEAPEST valid decomposition: fit the largest cheaper blocks first (e.g. 40
days = 1 month + 10 days if that beats 40 × daily), so a longer booking is never penalized.

== 5. Catalog, Search & Booking ==  [F03,F04,F05,F06]
kanban: `status:review` · `weight:L` · `impacts:F07,F10` · `deps:F03` · `ids:SB-search-01,CA-backlog-04,MS-specs-06`

= Map stack =  [F03]
Leaflet/OpenStreetMap is the map provider, with **what3words** layered on the location picker —
a geocoding service mapping every 3m×3m square on Earth to a fixed three-word address (e.g.
`///filled.count.soap`) so a provider can pin a space precisely without reading raw lat/long.
what3words is OPTIONAL client-side sugar (autosuggest only), stores NO column; the canonical
location stays latitude/longitude/location_name. The whole map stack is swappable to Google
Maps via `environment.mapProvider`.

= Landing & nearest-first sidebar =  [F03]  (UC-1 search/multi-select; UC-2 organize backlog→adset; UC-3 upload creative)
Client lands on a map centered on their geolocation (default Monterrey, NL). The right rail
(3-pane layout: menu | map | detail sidebar) lists nearby spaces sorted near-to-far via the
map provider's distance calc (or simple lat/long triangulation), each tagged with type
(billboard, big screen, little screen, radio station, kiosk, transit). The list is
multi-select; selecting one or more shows two buttons below: "Add to an existing campaign" /
"Add to a new campaign" (new campaign → 10-second toast with "Go to my new campaign").
Selecting a space shows its full detail in the right sidebar.

= Radio-in-map-range =  [F03]
"In range" for a radio station = inside the current MAP view range (typically a whole city) —
NOT listener location or broadcast footprint. Changing the map center re-sorts the list and
re-queries radio stations in range; pan/zoom updates the station list.

= Filters (exact dates) & backlog =  [F04,F05]
Filters: space type, date range, budget per day. Dates are EXACT — there is NO date-flexibility
(±X days) filter; clients/providers add slack via wider windows or "book for later."
Availability turns green/red for the chosen date range. Spaces whose calendar doesn't coincide
are TAGGED ("doesn't coincide with your timespan"), not hidden, and remain bookable "for later";
they are SORTED to the BOTTOM of the nearest-first list (available spaces first, unavailable last).
The campaign view shows an Orphan-spaces list (backlog spaces not yet in any adset); a client
MAY check out a partial campaign, but any orphaned ad cannot go live and its payment cannot be
processed — orphans can be bulk-moved into an adset (either a NEW adset created
inline, or an EXISTING adset chosen from the campaign) to become bookable. The upload control
shows the provider's media-delivery summary + free-text instructions inline, kept visible
during and after upload.

INVARIANT — ad origin. A client NEVER creates a space or an ad from scratch;
that is a provider/catalog concept. Every ad ALWAYS belongs to an existing
provider space (ad = space + provider + the client's creative). Ads originate
ONLY by selecting existing spaces on the map → campaign backlog (orphan ads) →
adset. Therefore, inside an adset there is NO "create a new ad/space" action:
the way to populate an adset is to move ads in FROM the unassigned backlog (a
NEW or EXISTING adset, per above). The only per-ad action inside an adset is
uploading THAT ad's creative media (one file per ad, MVP), validated against the
space's media-delivery specs. Any UI that lets a client type an ad name + pick a
media type + upload a file to mint a brand-new, space-less ad is a bug — such an
ad has no space and can never be booked.

= Booking action & per-ad go-live =  [F06]  (UC-15 provider approves/rejects; UC-13 provider lists space)
Provider acts on a request with **Approve** or **Reject** only — NO counter-offers. The
rejection reason is OPTIONAL (a given reason helps the client re-submit a corrected file). The
request queue shows client, dates, total, and the attached media file. Confirmed bookings move
to an installation queue (install date + the client's file), staffed by Installator-role
collaborators. Go-live is PER-AD, not all-or-nothing: each ad is approved, goes live, and bills
on its own approval independently — a slow/rejected provider never blocks the rest of the
campaign. An inquiry chat becomes a confirmed booking chat only when BOTH provider approval
AND client payment are complete; either alone leaves it an inquiry (UC-8, UC-15).

== 6. Book-for-later, Pre-pay & Escrow (AFTER-MVP) ==  [F15]  (UC-5)
kanban: `status:backlog` · `weight:XL` · `impacts:F10` · `deps:F10` · `ids:PE-checkout-01,PE-prepay-04`

When a requested window isn't yet free the client sees "Book it for later." Two per-space queues:
- **PRE-PAY** — funds captured into escrow; the booking joins the provider's per-space waiting
  list (provider sees a SIMPLE list with NO amounts + a trash/reject action). The provider picks
  which to confirm when the slot opens, by their own criteria. If the slot never opens within the
  admin-configurable expiry window, the client is refunded to wallet (notified; logged for
  Payments/Admin).
- **NON-PRE-PAY** — queued with no funds, resolved FCFS when the calendar frees.
- Cross-queue precedence is the PROVIDER's choice (no auto rule that funded beats older FCFS).
  Confirming one offer for a freed slot atomically cancels+refunds every sibling pre-pay offer.
- [owner 2026-06-27] Pre-pay is OPTIONAL. Confirming an offer is NOT final file approval and locks
  in NEITHER party: provider may still REJECT the file repeatedly, client may CANCEL before
  display; on any cancel/reject escrowed funds return to the client's wallet. (One checkout per
  campaign covers all ads regardless of provider.)

== 7. Proof of Display, Deadlines & Strikes ==  [F05,F07,F16]
kanban: `status:wip` · `weight:L` · `impacts:F08,F09,F10` · `deps:F06` · `ids:PR-accept-03,PR-reject-04,ST-strikes-01`

= Primary & secondary proofs =  [F07]  (UC-16 provider/installator uploads primary)
The PROVIDER owns the PRIMARY proof of display — only the primary proof makes a booking
eligible for payout. Within the proof deadline the PRIMARY proof is uploaded by the provider
owner OR the provider's Installator or Supervisor subrole — NOT Sales, and no client-side role
[owner 2026-07-10; "manager" = the provider's Supervisor subrole]. It is a photo (printed/screen
ads) or a short video (radio/audio ads). Client-side collaborators (per role) may upload SECONDARY
proofs (verifications) and flag mismatches, and can see the proofs relevant to them within the deadline.

= Deadline & reminders [MVP rule; cron is AFTER-MVP] =
Proof deadline is admin-configured (default 5 days), counted from `display_start` = the booking's
calendar start_date set at confirmation (stored timestamp the cron keys off). Reminders fire day 3
and day 4 (day-4 includes SMS). Missing the day-5 deadline auto-cancels the booking, refunds the
client to wallet in full, and accrues a provider strike. The auto-cancel cron locks the booking
and re-checks `proof IS NULL` inside the transaction.

= Review, payout & client reject → auto-hold =  [F07]  [owner 2026-07-10: NO re-upload loop; 2026-07-14: dispute opens 3 Support chats]  (UC-6 accept, UC-7 reject/dispute)
**Two phases.** PHASE 1 (the ONLY automatic opportunity): the provider must post the primary
proof within the proof deadline (admin default 5 d, above). PHASE 2 (human analysis): once the
client reviews the proof, humans take over — there is NO second automatic window before flagging.
The client's payment is HELD from booking until the CLIENT accepts the primary proof. The
**CLIENT** — and their collaborators, per role — review proof content. **Payments does NOT
review proof content**; Payments only sees the resulting money state.
- If the client ACCEPTS, the payment becomes releasable and Payments MANUALLY releases the payout.
- If the client REJECTS (or a collaborator flags a mismatch / the deadline passes with no
  satisfactory proof), the ONLY automatic actions are: (1) the payout is AUTO-HELD; (2) a FLAG is
  raised carrying informative reason text — `Payment held — <reason>` (e.g. "unsatisfactory
  proof") — attached to the payment so Payments has context; and (3) THREE Support-anchored CHATS
  auto-open (one chat primitive — §10; UC-7, UC-35) so humans can inspect the whole situation. Each
  is an instance of the SAME primitive, anchored to the held payment+booking object with a
  `payment_held` flag, differing ONLY by participant set:
    · **Support↔Payments** (internal) — the money decision; the ONLY chat Payments is in.
    · **Support↔Client** — Support investigates with the client (provider not a participant).
    · **Support↔Provider** — Support asks for a real/corrected proof (client not a participant).
  There is NO automatic re-upload window or clock — any request for a new proof is a HUMAN one
  Support makes in the Support↔Provider chat.
- Releasing a held payout is a MANUAL, human decision by **Payments together with Support** after
  they inspect the case across those chats: either release to the provider (e.g. once a
  satisfactory proof is posted and the client accepts), or cancel the booking and refund the
  client in full. A release does NOT wire instantly — it enters the §8 payout **processing queue**
  (a reversible grace window) before the money leaves, so Payments OR Support can still pull it
  back to `held`. Support leans toward the client and watches for fraud; Payments executes the
  money movement. Nothing beyond the hold + flag + chat-opening is automatic.

Proof photos are NOT auto geotag-checked against the space lat/long — the space belongs to the
provider, who knows where it is; a proof that doesn't match the booked ad is caught by the
client/collaborator mismatch-flag → Support/Payments path instead.

= Strikes (AFTER-MVP) =  [F16]
Strikes accrue on missed proof deadline, provider cancel of a confirmed booking, or upheld
mismatch; accrual is decoupled from notification delivery. NOT a Payments power — routes Support
then Admin. 3 strikes in a trailing 90-day window flag the account for Admin review (possible
freeze); count shows on the provider dashboard. Provider appeals via a support chat; SUPPORT (not
Payments) can remove a strike from the counter (reversible, audited) (UC-18 appeal, UC-24 reverse).

== 8. Money ==  [F10]
kanban: `status:wip` · `weight:L` · `impacts:F07,F09,F11` · `deps:F06` · `ids:PM-money-02,PM-payouttxn-05,PM-procqueue-05b`

All money is **decimal MXN pesos** — `decimal(12,2)`, single currency, no centavos (owner
2026-06-15: "forget cents"). Gateway callbacks settling in another currency (e.g. PayPal USD)
are normalized to MXN **at capture**; never treat the raw gateway number as MXN. Timestamps
stored UTC; deadline math against the admin-default timezone (Monterrey at launch).

**Gateway.** Mercado Pago and PayPal (`payments.payment_platform = mercadopago|paypal`), mocked
until keys exist. Webhooks signature-verified, persisted raw, idempotent by gateway event id,
with a nightly reconciliation job. The gateway processing fee is paid GROSS by the CLIENT —
added on top at checkout, never absorbed by the platform or netted from the payout.

**Payment lifecycle.** `payments` belongs to a booking; status `pending|completed|failed|
refunded|held|processing|released|settled` (the `processing`/`settled` states are the payout
queue — see *Payout processing queue & gateway truth* below; DB-enum migration pending, doc leads
code). The client's payment is HELD from booking until the CLIENT accepts the
primary proof (Payments does NOT review proof content — §7/§10). Client-accept → releasable;
client-reject → HELD with a `payment_held` flag on the payment + three auto-opened Support chats
(§7/§10; UC-7, UC-25, UC-27). `payout-held` is a `payments.status`
concept, NOT a booking state.

**Escrow (AFTER-MVP — see §6).** Pre-pay funds → escrow (`wallet_entries.type=escrow_capture`),
released on confirm/cancel (`escrow_release`); capture/release + booking state change in ONE
row-locked txn. Payout state machine `held → processing → released → settled` (see below; holds
re-checked in the locked txn at each transition).

**Wallet ledger.**  (UC-11)  `wallet_entries` is append-only, double-entry: `user_id`, signed `amount`
(`decimal(12,2)` MXN), `type {refund|withdrawal|escrow_capture|escrow_release|adjustment}`,
`ref_type`, `ref_id`, `idempotency_key` (UNIQUE), `created_at`. Balance = `SUM(amount)`, never a
writable column. Wallet states `{active|frozen}`; withdraw is blocked while any dispute/hold is
open (`POST /client/wallet/withdraw`). Every refund/payout carries an idempotency key so a
retried gateway call or re-fired cron cannot double-pay; enforce `SUM(refunds) ≤ captured`.

**Refunds.**  (UC-26 execute; UC-10 client cancel)  Payments executes ALL refunds (mocked); Support only FLAGS. [owner 2026-06-27]
Cancellation refunds are **100% by default** UNLESS the booking was already IN PROCESS (install
work started / teams dispatched), then reduced to cover work performed — stated in the Terms &
Conditions accepted at checkout (supersedes the earlier 90/5/5 split). Free pre-approval cancel;
nothing post-display unless a proof fails; full refund on proof-miss auto-cancel or provider
cancel. Clawback (refunded client later suspended for fraud): platform FREEZES the wallet + alerts
Payments; Payments cannot pull funds — the frozen balance waits for an Admin decision (claw back
via negative ledger entry / keep frozen / release).

**Payouts.**  (UC-17 provider receives; UC-25 Payments executes; UC-32 Admin payout-stop)  Happen
ASAP but must be APPROVED by Payments first; Support flags only. Once
approved and nothing holds it, **Admin** has a configurable window (default 24 h) to stop it;
if Admin doesn't, funds release automatically to the provider's Mercado Pago / PayPal. Payouts
are **PER-PROVIDER** even within one multi-provider campaign invoice — each provider is paid on
their own schedule. A payout held by an open dispute stays held until human adjudication (not a
timer), then pays in full if resolved in the provider's favor (or is refunded per outcome).

**Payout processing queue & gateway truth.** [owner 2026-07-16] Releasing a payout is NOT an
instant wire. When Payments releases it (and nothing holds it) the payout enters a **processing
queue**: `payments.status = processing` for a configurable **grace window** (Admin System Config
`payout_processing_grace`, default 1 h) before a queued job actually dispatches the money-out to
the gateway. This buffer is a **local, fully reversible** window — during it Payments OR Support
can still pull the payout back to `held` (or cancel → refund) with **no gateway call**, so a
mistaken release is recoverable. The `processing` state is surfaced **only on the Payments and
Support UIs** (the client/provider just see "payout pending"); it is never a booking state.

Once the grace window elapses the payout is dispatched — **one idempotency key per payout** so a
retry never double-pays — and the **gateway becomes the source of truth**. We learn the money
actually moved from Mercado Pago via a signature-verified **webhook (IPN)** plus a status query,
recording `gateway_status` + `gateway_settled_at`. Mercado Pago money-in payment states are
`pending|in_process|approved|rejected|refunded|charged_back`; once the gateway reports the
transfer/payment as `approved`/settled the payout is **TERMINAL** (`released`, then `settled` on
reconciliation) and a local `holdPayout` is a **NO-OP** — undoing it now requires a gateway-side
**REFUND**, never a local status flip. This is precisely why `holdPayout`, Support's
`flagPayoutHold`, and proof accept/reject all guard against `released|refunded`: the platform must
never claim a payment is "held" when the gateway has already sent or returned the funds. A nightly
**reconciliation job** re-queries any `processing`/`released` payout whose webhook never arrived
(gateway offline → retry with backoff) so local state can never silently diverge from Mercado
Pago's. Full payout state machine: `held → processing → released → settled`; `processing → held`
is the ONLY backward edge and ONLY within the grace window. (Gateway calls stay MOCKED until keys
exist — the queue, grace window, reversible state machine and Payments/Support UI are MVP-scoped
and real now; live dispatch + webhooks + reconciliation land with the gateway integration,
AFTER-MVP.)

**Invoices.**  (UC-4 checkout & pay, one invoice per campaign)  `invoices` belongs to campaign: `invoice_number` (unique), `total_amount`, status
`draft|issued|paid|overdue|cancelled`. **ONE invoice per campaign** (one checkout covers all ads
regardless of provider), with **per-ad line items** and **per-provider payouts**. An orphan ad
(not in an adset) cannot go live and its payment cannot be processed.

== 9. Ratings (AFTER-MVP) ==  [F17]  (UC-12)
kanban: `status:backlog` · `weight:M` · `impacts:-` · `deps:F07` · `ids:RT-table-01,RT-post-02`

Provider star rating (1–5 + count) on each space detail = average of per-campaign ratings +
optional comment; `GET /spaces/{s}` returns avg+count. **Eligibility:** only clients rate, only
after a campaign with that provider **ended OR ran live ≥30 days**, **one per client per campaign**
(`POST /client/campaigns/{c}/rating`). [owner 2026-06-27] Ratings are IMMUTABLE and fully
transparent — no client edit/delete; abusive comments flagged for Support, removed only by rare
MANUAL admin action, never auto-hidden.

== 10. Chats, Objetos & Flags ==  [F08,F09]
kanban: `status:wip` · `weight:XL` · `impacts:F07,F11,F12` · `deps:F06` · `ids:CH-mask-02,CH-startchat-08,TK-flagbridge-06,TK-join-07`

**One primitive.** Every conversation on the platform — a client asking a provider a question, a
"talk to support" request, an internal Support↔Payments money huddle, a proof dispute — is ONE
object: a **chat**. There is no separate "ticket" entity, table, area, or menu; a chat IS a
"ticket" purely by having a **Support** participant. A chat's nature is NEVER stored in a
`type`/`kind` column — it is **DERIVED** at read time from three things: its **participants**
(which sides are present), its **attached objects**, and its **flags**. Why a chat was opened and
every later transformation live in its persisted **message/event history** (`kind=system`
messages), not in a column. [owner 2026-07-17: merged former §10 Chat + §11 Tickets.]

**Participants — a per-person membership table.** Access is by **MEMBERSHIP/role, never by a
(mutable) flag**. `chat_participants` lists each person on a chat with their `side`
(client|provider|support|payments|admin), `announced`, `joined_at`, `left_at`. A person may
read/post a chat when they hold a participant row OR they belong to a participating ACCOUNT with a
chat-capable subrole (so a collaborator added later still reaches their account's chats). Sides:
- **Client side** — the client account + ANY of its collaborators (publicist/manager) — for now.
- **Provider side** — the provider account + its **Sales** and **Supervisor** collaborators;
  **NOT Installators** (their role is proof-upload only).
- **Support side** — the Support role; any support agent can pick up a chat that has a Support
  participant (the support "queue" is DERIVED, not a separate inbox). A Support join into a
  client↔provider chat is **ANNOUNCED** (a system message) and **relaxes PII** for that chat (UC-21).
- **Payments** — joins only money-relevant chats (a chat anchored to a payment): the internal and
  dispute chats. Payments never enters a plain client↔provider chat.
- **Admin** — **eagle-eye, read-only, incognito**: reads EVERY chat (including closed ones, full
  unmasked history) via the oversight view; entry is never shown in the user UI and Admin does NOT
  post as a ghost participant. Admin's only chat write-power is a moderation action (reopen a
  closed chat for investigation). [reconciled 2026-07-17: the old "admin can post into any thread"
  is narrowed to read-only per the ACL review — a silent ghost-poster is a hazard.]

**Objects — zero..many polymorphic attachments.** A chat may attach ZERO or MANY objects, each of
`ad | adset | campaign | space | payment | booking`. A chat opened *from* an object carries it from
the start as **context** (provider, dates, payment status, proof state) — but the object is ONLY an
attachment for the conversation; it does **NOT** define the counterparty (see "Entry point" next).
A chat with NO object is a general question. The UI attaches an object via two dropdowns (object
type → the object, e.g. Ads → "My Ad #1"). **Attaching is ownership-checked:** a client/provider may
attach only THEIR OWN objects; Support/Admin may attach any. An object never grants chat access, and
a chat never exposes an object its attacher could not already see (guard against private-object leakage).

**Entry point sets the counterparty — the object is only context.** [owner 2026-07-25] A chat's
opposite party is decided by WHERE the client opens it, **never inferred from an attached object's
owner**:
- from the **Messages** tab, or from an **ad / adset / campaign** object → **Support**
  (`support_client`); the object rides along as context Support can see — it does NOT pull in the provider.
- from a **provider's published space/ad listing** → that **Provider** (`client_provider`, the space
  attached). This is the ONLY path to a provider: providers have **no public profile** (none in the
  UI), so a client can only reach one from a listing the provider published.
- from **Billing / invoices** → **Support**, with the payment/invoice attached; Support fronts it and
  loops **Payments** in through the internal Support↔Payments chat. Payments never faces the client
  directly — the client still "reaches billing", just via Support. [owner 2026-07-25: chose B]

Counterparties are **not fixed at creation**: they **enter or are notified as the context demands** —
Payments may join a money-relevant chat later, Support may join a client↔provider inquiry (announced).
So "who is on a chat" evolves over its life; the attached object stays a mere context attachment throughout.

**Multiple contact entry points per page, routed by context.** [owner 2026-07-25] A single screen may
offer SEVERAL "contact" affordances, each opening a chat with a different counterparty depending on
WHAT the user is asking about — e.g. a **global** button in the header (near the user icon) → **Support**
(general help, objectless); an **in-context** button inside a space's body → that **Provider** (the space
auto-attached as evidence); a button on an invoice → **Support** with the payment attached. The object
attaches itself so the counterparty knows what the conversation is about. **Payments and Admin are
INTERNAL**: a client can **NEVER** contact them directly — they only talk to **Support**, or are **added**
to an existing conversation when the context requires it (Payments to a money chat, Admin as read-only oversight).

**Flags — zero..many, with history.** A chat carries ZERO or MANY flags. A flag =
`{ type, reason, active, created_by, superseded_at }`, `type ∈ {payment_held, refund, payout_hold,
cancel, mismatch, …}`. Flags are the **volatile human annotations**; on any change the old flag is
deactivated (`superseded_at` set), the new one created, and a **system message** records it ("flag
payment_held → cancel by Ana"). History is persisted. A flag NEVER grants access. The authoritative
**money STATE is NOT a flag** — it stays on `payments.status` (`held|processing|released|settled|…`),
which is what gates automation; flags only annotate the WHY. Support RAISES money flags
(refund/payout_hold/payment_held); **Payments EXECUTES** the money via the §8 routes (UC-22 flag, UC-25/UC-26 execute).

**States.** `open | in_progress | resolved | closed`. Support sets **in_progress** on pickup;
**resolved** = Support did its part; **closed** = the OPENER confirmed it is solved (a Support
force-close is an Admin-style override that writes a system event). The user-facing UI shows only
**Abierto / Cerrado** (closed → Cerrado, everything else → Abierto). A closed chat is **NOT
reopenable by users** — they open a NEW chat; only **Admin** reopens (investigation). A closed
chat's flags and history are hidden from normal users, visible to Admin.

**PII masking** is one server-side service, evaluated **per-message at render** against the viewer
and the chat's participants. It applies ONLY to a chat that has BOTH a client side and a provider
side with NO Support joined (a client↔provider chat): phone, email, full URL, and off-space street
addresses are masked, while the booked space's OWN stored street address (and close variants) is
whitelisted (the client needs it to install/photograph the ad). Masking **relaxes** the moment
Support joins (announced) and **NEVER** applies on any staff-only chat (Support↔Client, ↔Provider,
↔Payments, Admin). Raw bodies are always stored so Admin/audit can read originals.

**Single "Messages" surface.** Every role reaches all of this through ONE **Messages** area — there
are NO "Support" tabs or menus at any level. "Contact support" is simply "open a chat" (objectless,
or object-attached via the two dropdowns). Support's Messages shows the chats it may pick up (its
derived queue); each side sees only its own chats. (UC-8 client↔provider inquiry; UC-9 contact support.)

**Dispute (see §7).** When a client rejects proof, `payments.status = held` (stops automation) and
**three chats** auto-open — each an instance of THIS primitive, each anchored to the held
payment + booking object and carrying a `payment_held` flag: **Support↔Client**, **Support↔Provider**,
**Support↔Payments** (the last auto-opened so Payments sees the held payment as an attached object
and can proactively ask Support before releasing or holding longer — human safety, no automation).
They differ only by participant set; the opposite party is simply not a participant. (UC-7, UC-35.)

**Admin oversight** — Admin reads every chat via ONE read-only/incognito screen
`GET /admin/oversight/chats` with filters; full spec stated once in §12 [dedup 2026-07-17]. (UC-28.)

== 11. (merged into §10) ==  [F09]
kanban: `status:wip` · `weight:S` · `impacts:F09` · `deps:F08` · `ids:-`

Tickets & Support are no longer a separate system: a "ticket" is just a **chat with a Support
participant** — see §10 "Chats, Objetos & Flags". Support's non-chat powers live with their
domains: edit-any-NON-money object (audited, UC-23) and strike accrual/removal → §7/§12 (UC-24);
money **FLAGS** that Payments executes → §8 (UC-22 flag, UC-25/UC-26 execute). Support "resolves" a
chat; the opener (or Admin) "closes" it.

== 12. Admin: Moderation, Freeze, Audit, Configurations ==  [F11,F12]
kanban: `status:wip` · `weight:L` · `impacts:F08,F10,F13` · `deps:F09` · `ids:AD-oversight-03,AU-log-01,CF-config-01`
<!-- [owner 2026-07-29] F11 (Admin oversight / eagle-eye · UC-28) CLOSED as done: OversightController (GET /admin/oversight/chats + /{chat}, read-only/incognito, unmasked) + admin/oversight frontend + ChatAclMatrixTest (6 tests green, full suite 44 passed). §12 stays wip until F12 (config · UC-30). Untracked admin scope suggested only (design.json lineage.suggestedTodoProposals): UC-29 moderation takedown/freeze (routes designed in §17, not built), UC-31 audit log, UC-32 payout-stop/clawback. -->


Admin is the **eagle-eye** role: reads EVERYTHING (every chat — one primitive, §10 — plus the audit
log, users, RBAC). Chat oversight is ONE screen `GET /admin/oversight/chats` with filters
(nature / flag / object / status / participant) — **read-only/incognito**: Admin observes chats
silently, never posts as a ghost, and its only chat write-power is reopening a closed chat for
investigation (UC-28). It replaces the two former oversight views (conversations + tickets). Admin
has no money-execution role beyond the payout-stop window.

**Three levels of taking a listing/provider "off":**  (UC-29)
- **Pause / unpublish** — provider's own action; hides their own listing; reversible by them.
- **Takedown / restore** — admin moderation; forcibly hides/restores a listing; the provider
  CANNOT self-reverse. `POST /admin/spaces/{s}/takedown`, `POST /admin/spaces/{s}/restore`.
- **Freeze** — admin, account-level: pauses NEW bookings AND auto-cancels ALL upcoming bookings
  on that provider, each with a full refund to the client's wallet. `POST /admin/providers/{u}/
  freeze`, `…/unfreeze`. A freeze busts that user's permission/session cache immediately and
  revokes active Sanctum tokens (do not wait for the 60-min RBAC cache).

**Deletion guardrails (EVERY deletable object — users, campaigns, adsets, ads, spaces):** a hard
delete is BLOCKED whenever the object has open support chats, a booking in progress, or
unsettled funds. A CAMPAIGN cannot be hard-deleted while any of its ads has an active/booked
space or an open support chat; a USER cannot be deleted with active bookings or unsettled funds.
Instead deletion is a **"programmed for deletion"** state: the object is unpublished/closed to
new activity, stops receiving new bookings/payments, and stays visible with the legend "this is
programmed for deletion" until current bookings finish, then is removed.

**Immutable audit log.**  (UC-31)  Append-only; one entry per action of ANY role with **actor, target,
before/after, timestamp**; queryable as per-object history; Admin read-only. Internals (AFTER-MVP):
DB-level immutability (INSERT-only grant + trigger raising on UPDATE/DELETE, optional hash-chain);
audit row written in the SAME txn as the state change (async via outbox); `insert_data.py
--erase-all-data` env-guarded and never truncates audit in a real env. API: `AuditLog::record()`
+ `GET /admin/audit` (`?target_type`, `?target_id`).

**System Configurations (Admin-only; defaults in parentheses):**  (UC-30) proof deadline (5 d) ·
strike window (90 d) + 3-strike threshold · pre-pay offer expiry ·
calendar-staleness threshold (7 d) · payout-stop window (24 h) · payout processing grace (1 h — §8) · cancellation refund policy
(100% default; reduced only if in-process — §8) · currency (MXN) + unit (decimal pesos) ·
default timezone (Monterrey at launch — §14) · auto-approve/auto-release amount threshold ·
rate-limit thresholds · audit-log retention per role.

**Apply-to-in-flight + config snapshot.** Changing a configurable value offers a CHECKBOX:
"apply to existing/in-flight bookings too" vs "new bookings only." Unchecked (default) snapshots
config onto each booking at creation (`bookings.config_snapshot` JSON) so a live deal isn't
changed mid-flight; checked re-applies to running bookings. Media-delivery specs are likewise
snapshotted onto the booking/ad at checkout so later provider edits don't retroactively fail a
paid file. API: `GET,PUT /admin/configurations`.

**Admin operational/eagle-eye dashboard.** Indicators — ads rejected by providers, proofs
rejected (payment held), open chats with a Support participant, payments stopped — plus queue depths,
refund rate, strike rate, gateway health. Each indicator links to a list; each list item opens its
full detail in eagle-eye mode (chats opened SILENTLY).

**Rate limits.** Per-IP and per-account caps on login, chat send, file upload, search, booking
submission, with a burst allowance for known-good accounts; throttled responses include
retry-after. Thresholds are admin-configurable.

== 13. Notifications & Calendar ==  [F13]
kanban: `status:backlog` · `weight:L` · `impacts:F07,F10` · `deps:F12` · `ids:NT-dispatch-01,CL-avail-01`

**Dispatcher (AFTER-MVP).** Emit domain events (`BookingApproved`, `ProofRejected`, …); queued
listeners fan out via a channel/urgency lookup — no scattered `Notification::send`. API: internal
dispatch + `GET /notifications`, `POST /notifications/{n}/read`. The full ~35-event alert schema
table is in §19.

**Channels.** in-app / email / SMS-mock; most are simple in-app text at the top of the UI, some
surface as a Support/Payments chat. SMS is reserved for Payments alerts and cancellations only
(Twilio default, Vonage fallback; mocked until keys set). Strike alerts go to Provider + Support
(and Admin on the 3rd) — never Payments.

**Calendar (iCal sync + staleness).**  (UC-14)  The provider availability UI strongly recommends
connecting a live calendar URL (.ical or public Google/Outlook) because it's easier to keep
current; a "?" help button explains how to find the URL. API: `GET,POST /provider/spaces/{s}/
availabilities`, `DELETE /…/{av}`, `POST /…/sync-ical`, `POST /…/import-ical`.
- **Staleness clock** is measured from the LAST SUCCESSFUL SYNC (URL/iCal) or LAST MANUAL EDIT
  (hand-managed) — never from space creation. If older than the threshold (default 7 d), an
  alert appears next to that space in the provider's list. A healthy auto-syncing URL never fires.
- If an imported calendar goes OFFLINE, the space FALLS BACK to its last-known/manual range
  rather than vanishing, and the staleness alert prompts a fix.

== 14. RBAC & Capabilities ==  [F01,F14]
kanban: `status:review` · `weight:M` · `impacts:F02,F09,F11` · `deps:F01` · `ids:AC-grants-07,AC-namedcaps-11`

**Two layers.** The global `role_permissions` matrix governs SYSTEM roles only; a SEPARATE
per-ACCOUNT grant overlay governs collaborators, evaluated AFTER the global matrix and scoped so
grants never leak across accounts.

**Global matrix (`role_permissions`).** Which actions (create, read, update, delete) each role
has on each resource/screen: users, campaigns, adsets, ads, spaces, space_photos,
space_availabilities, bookings, payments, proofs, chats (retires the old tickets +
conversations resources — alias both to `chats`), invoices, collaborators, dashboard.
- `PermissionMiddleware` checks every route via `permission:resource,action`.
- Permissions cached per-role for 60 min, auto-invalidated on admin edit.
- Login and `/me` return the user's permissions for the frontend.
- Admin permission routes carry NO permission middleware (only `role:admin`) to prevent lockout.
- Defaults seeded to match the original hardcoded role access.
- API: `GET /admin/permissions` (grouped); `GET /admin/permissions/{role}`; `PUT /admin/
  permissions/{role}` (bulk-replace); `PATCH /admin/permissions/{role}/{resource}`.
- Admin panel includes a roles editor: resources (rows) × actions (cols) per role, toggle
  checkboxes, cannot lock out the admin role.

**Per-account collaborator overlay.** Collaborators are account-scoped via `account_grants`
(account_id, collaborator_user_id, role, capability); subroles + exact capabilities + scoping
rule are in §3. Per-object scoping may be layered later (not MVP). API: `GET,POST
/client/collaborators` + `DELETE /…/{col}` (publicist, manager); `GET,POST
/provider/collaborators` + `DELETE /…/{col}` (installator, sales, supervisor).

**Named capabilities** (beyond CRUD): `refund.flag` vs `refund.execute`, `payout.hold` vs
`payout.release`, `strike.accrue` vs `strike.reverse`, `chat.flag` vs `chat.join` vs `chat.oversight`.
Support gets flag-only + announced join; Payments gets execute-only; Admin gets read-only chat
oversight (never posts).

**Timezone** (owner 2026-06-25). All timestamps STORED in UTC. Deadline math (proof deadline,
reminders, auto-cancel) is computed against the platform DEFAULT timezone — **America/Monterrey**
at launch — an Admin System-Configurations setting, retargetable without code. DISPLAY is
localized PER USER (each user may set their own tz; default = platform tz). The canonical
deadline instant is identical for everyone; only rendering varies — never compute deadlines off
the requester's local clock.

== 15. Data Schema ==  [infra]
kanban: `status:review` · `weight:M` · `impacts:all` · `deps:-` · `ids:SB-overlap-06,AU-log-01`

Authoritative table list. PostgreSQL 17 — db `pub_ads_mar`, user postgres (trust auth). All
money is DECIMAL MXN pesos (`decimal(12,2)`) — no centavos. Timestamps stored in UTC.

```
users
  id, name, email, password, role (enum: client|provider|admin|support|payments),
  phone, company_name, address, is_active, remember_token,
  timezone (nullable — per-user display tz; default = platform tz; PENDING column), timestamps

personal_access_tokens  [Sanctum]
  id, tokenable_type, tokenable_id, name, token (hash), abilities, last_used_at, expires_at, timestamps

accounts  [belongs to: owner user]   -- MVP: exactly ONE per owner user; exists so multiple
  id, owner_user_id (unique), name, type (enum: client|provider), timestamps   -- owner-users can share later

campaigns  [belongs to: user(client)]
  id, user_id, name, description, status, start_date, end_date, budget, timestamps

adsets  [belongs to: campaign]
  id, campaign_id, name, status, timestamps
  (latitude/longitude/location_name/radius_km are LEGACY/unused — adsets are PURE GROUPING LABELS)

ads  [belongs to: adset (nullable) + space + provider_user]
  id, adset_id (nullable — backlog/orphan), space_id, provider_user_id,
  name, media_type (enum: image|video|sound|gif), file_path, file_name,
  price, pricing_unit (day|month|custom), start_date, end_date,
  status (enum: draft|pending_approval|approved|rejected|active|paused|completed|cancelled),  -- own enum, NOT unified with bookings
  proof_deadline, timestamps   -- PLANNED: physical_specs + media_specs + upload_instructions

spaces  [belongs to: user(provider)]
  id, user_id, name, type, latitude, longitude,
  price_per_day, price_per_month, pricing_unit (day|month|custom),
  description, location_text, width, height, ical_url, calendar_keyword, is_active, timestamps

space_photos  [belongs to: space]   -- live route /photos; rename target /media (image/video/other)
  id, space_id, file_path, file_name, is_primary, sort_order, timestamps

space_availabilities  [belongs to: space]
  id, space_id, start_date, end_date, status (enum: available|booked|blocked), source (manual|ical), timestamps

bookings  [belongs to: client_user + space + ad + adset]
  id, client_user_id, space_id, ad_id (nullable), adset_id (nullable), start_date, end_date, total_price,
  status — CANONICAL enum (owner 2026-06-25; column migration PENDING):
    draft|waiting_approval|waiting_booking_confirmation|live|proof_pending|proof_uploaded|completed|rejected|cancelled
  display_start (= calendar start_date set at confirmation; deadline/cron key off this),
  config_snapshot (json — e.g. proof_deadline_days), timestamps
  -- EXCLUDE USING gist (space_id WITH =, daterange(start,end) WITH &&) prevents overlapping CONFIRMED bookings

payments  [belongs to: booking]
  id, booking_id, amount, status (enum: pending|completed|failed|refunded|held|processing|released|settled),  -- money state machine; payout-held lives here [synced to §8, owner 2026-07-17]
  payment_method (mocked), payment_platform (mercadopago|paypal), transaction_id,
  approved_by_payments, approved_by_user_id, timestamps

chats  [ONE communication primitive — §10; a "ticket" = a chat with a Support participant]
  id, opened_by_user_id,
  client_user_id (nullable), provider_user_id (nullable),   -- side anchors (MVP 1user=1account)
  status (enum: open|in_progress|resolved|closed) default open,
  support_joined_at (nullable — PII-relax + announced trigger for the client↔provider case),
  resolved_at (nullable), closed_at (nullable), closed_by_user_id (nullable),
  last_message_at (nullable — denormalized list ordering), timestamps
  -- NO type/kind column: nature is DERIVED from participants+objects+flags (§10, §16).
  -- NO unique(space_id, client_user_id, *): a space+client may carry MANY chats (closed chats
  --   spawn new ones, disputes, general questions). space_id is DROPPED as a column — a space is
  --   just one possible chat_object.

chat_objects  [polymorphic N: belongs to chat; morphTo objectable]
  id, chat_id (FK chats cascade), objectable_type, objectable_id,   -- Ad|Adset|Campaign|Space|Payment|Booking
  attached_by_user_id, created_at
  index(chat_id); index(objectable_type, objectable_id)
  -- attach is OWNERSHIP-CHECKED (§16): a chat never exposes an object its attacher couldn't already see

chat_flags  [N + history: belongs to chat]
  id, chat_id (FK chats cascade),
  type (enum: payment_held|refund|payout_hold|cancel|mismatch|other),
  reason (text nullable), active (bool default true), created_by_user_id,
  superseded_at (nullable — set when a newer flag deactivates this one), timestamps
  index(chat_id, active)
  -- flags ANNOTATE the WHY; the authoritative money STATE stays on payments.status, never a flag

chat_participants  [PER-PERSON membership: belongs to chat + user]
  id, chat_id (FK), user_id, side (enum: client|provider|support|payments|admin),
  announced (bool default false — support join = true; admin silent = false/forensic),
  joined_at, left_at (nullable), timestamps, unique(chat_id, user_id)
  -- access is by MEMBERSHIP/role, never by a mutable flag (§16)

messages  [belongs to: chat + sender_user]
  id, chat_id, sender_user_id, body, kind (user|system…), is_read, timestamps
  -- kind=system messages record origin + every transformation (flag change, support announced-join,
  --   object attach/detach, resolve/close) — history IS the record of a chat's nature

proofs  [belongs to: ad + booking + uploaded_by_user]
  id, ad_id, booking_id, uploaded_by_user_id, media_type (image|video), file_path, file_name, notes,
  status (enum: pending|uploaded|client_accepted|client_rejected),  -- CLIENT accepts/rejects, NOT Payments
  reviewed_by_user_id (= acting CLIENT), reviewed_at, deadline, timestamps

-- tickets + ticket_messages: RETIRED (2026-07-17 merge). A "ticket" is now just a chat with a
--   Support participant; ticketable → chat_objects; is_internal notes → staff-only chats + kind=system.

collaborators  [belongs to: account + invited_by_user + user]   -- ACCOUNT-SCOPED
  id, account_id (FK → accounts), invited_by_user_id, user_id (nullable until registered), email,
  role (string — CLIENT: publicist|manager; PROVIDER: installator|sales|supervisor),
  status (enum: pending|accepted|revoked), unique(account_id, email), timestamps
  -- code migration campaign_id → account_id PENDING

invoices  [belongs to: campaign]
  id, campaign_id, invoice_number (unique), total_amount,
  status (enum: draft|issued|paid|overdue|cancelled), issued_at, due_at, timestamps

role_permissions  [RBAC]
  id, role, resource, action, allowed, unique(role, resource, action), timestamps

wallet_entries  [belongs to: user]   -- append-only ledger; balance = SUM(amount)
  id, user_id, amount (signed decimal(12,2) MXN),
  type (enum: refund|withdrawal|escrow_capture|escrow_release|adjustment),
  ref_type, ref_id, idempotency_key (unique), timestamps   -- wallet states {active|frozen}

system_configurations  [admin-tunable]
  id, key (unique), value, updated_by_user_id, timestamps
```

PLANNED tables (designed, not built): `strikes`, `ratings`, `audit_logs`, `account_grants`,
`notifications`. Relationships: User(client) ─< Campaign ─< Adset ─< Ad (adset_id nullable →
orphan); Ad ─> Space + User(provider). Account ─< Collaborator. User(provider) ─< Space ─<
SpacePhoto/SpaceAvailability. Space+Ad+Adset+User(client) ─> Booking ─> Payment. Ad+Booking+User
─> Proof. Chat ─< Message; Chat ─< ChatObject (poly objectable = Ad|Adset|Campaign|Space|Payment|
Booking); Chat ─< ChatFlag; Chat ─< ChatParticipant (per-person). User ─< WalletEntry.

== 16. Data Model & Integrity Invariants ==  [infra]
kanban: `status:review` · `weight:S` · `impacts:F10` · `deps:F15` · `ids:SB-overlap-06`

Integrity rules (terse; most are AFTER-MVP — build when their subsystem ships):
- **Money [MVP]:** wallet = append-only entries, balance = `SUM(amount)` (decimal pesos), never a
  writable column; every refund/payout has a UNIQUE idempotency_key (no double-pay); `SUM(refunds) ≤ captured`.
- **Money [AFTER-MVP]:** escrow capture/release + booking change in one row-locked txn; payout
  `held→processing→released→settled` (grace window, §8) [synced 2026-07-17]; gateway webhooks signed/idempotent + nightly reconcile.
- **Concurrency:** MVP = lightweight app-level overlap check on booking create. AFTER-MVP = Postgres
  `EXCLUDE USING gist` + `SELECT … FOR UPDATE` on slot/payout/expiry; pre-pay confirm atomically
  cancels+refunds sibling offers; auto-cancel cron re-checks `proof IS NULL` in-txn.
- **Snapshots [MVP]:** config snapshot onto booking at creation; "apply to in-flight" checkbox re-applies.
- **Chat [MVP] (§10):** ONE `chats` primitive — nature is DERIVED from participants+objects+flags,
  never a `type` column. Access is by MEMBERSHIP/role, NEVER by a (mutable) flag — a flag flip changes
  no ACL cell. Staff-only chats (Support↔Client/Provider/Payments, Admin) NEVER mask PII; masking
  applies only to a client↔provider chat with no Support joined. Object-attach is OWNERSHIP-CHECKED —
  a chat never exposes an object its attacher could not already see (no private-object leak). Admin
  chat oversight is READ-ONLY/incognito (never posts). Money authority is `payments.status`, never a
  flag; Support RAISES money flags, Payments EXECUTES.
- **RBAC:** global matrix for system roles; collaborators via per-account `account_grants` overlay
  (scoped, no cross-account leak). Provider freeze busts perm/session cache + revokes tokens. [account_grants = AFTER-MVP]
- **AFTER-MVP:** `display_start` stored timestamp for proof crons; per-person `chat_participants`
  as the sole membership source (retire the side-anchor columns); DB-level audit immutability
  (INSERT-only + trigger); strikes decoupled from notifications + reversible.
- **NOTE:** `proofs.status` is currently a SUPERSET (legacy `pending_review/approved/rejected` +
  B9 `client_accepted/client_rejected`) until the deprecated Payments proof-review is removed.

== 17. Backend Endpoint Map ==  [infra]
kanban: `status:review` · `weight:S` · `impacts:all` · `deps:-` · `ids:-`

Source of truth: `backend/routes/api.php`. Format: `METHOD path — purpose`. ⚠ = the LIVE code
diverges from canon. All paths prefixed `/api`. Two parts: LIVE (built) and PLANNED (designed).

--- LIVE endpoints (built) ---
Public / auth: POST /register · POST /login · POST /logout · GET /me

Client (role:client, prefix /client):
  POST,GET /campaigns · GET,PUT,DELETE /campaigns/{c} — campaign CRUD (DELETE ⚠ no guardrail)
  POST,GET /campaigns/{c}/adsets · GET,PUT,DELETE /…/adsets/{a} — adset CRUD (grouping label)
  POST,GET /…/adsets/{a}/ads · GET,PUT,DELETE /…/ads/{ad} — ad CRUD ⚠ no media-spec gate
  POST,GET /bookings · GET,PUT /bookings/{b} — book / cancel ⚠ no one-checkout, no pre-pay/escrow
  GET /spaces/search — geo+price+date catalog
  POST,GET /campaigns/{c}/collaborators · DELETE /…/{col} — ⚠ campaign-scoped (must be account; see Planned)
  GET /invoices · GET /invoices/{i} · GET /invoices/{i}/pdf
  POST /campaigns/{c}/backlog · GET /campaigns/{c}/orphans · POST /campaigns/{c}/adsets/move
  POST /proofs/{proof}/accept — client accepts proof → payout releasable (B9, LIVE)
  POST /proofs/{proof}/reject — client rejects → payout HELD + payment_held flag + 3 Support chats (B9, LIVE; flag-mismatch aliases this)
  GET /wallet — balance + entries ⚠ no withdraw

Provider (role:provider, prefix /provider):
  POST,GET /spaces · GET,PUT,DELETE /spaces/{s} — space CRUD ⚠ no spec columns; DELETE no guard
  POST /spaces/{s}/media · DELETE /…/media/{m} — space media ⚠ live route named /photos; rename to /media
  GET,POST /spaces/{s}/availabilities · DELETE /…/{av} · POST /…/sync-ical · POST /…/import-ical
  GET /bookings · PUT /bookings/{b} — booking queue: Approve / Reject(reason OPTIONAL, stored) — LIVE; ⚠ provider UI button still bare
  GET /dashboard
  GET,POST /proofs · GET /proofs/{proof} — primary proof; deadline EXISTS by design (5 d) ⚠ missing enforcement cron + strikes

Admin (role:admin, prefix /admin):
  GET /dashboard
  POST,GET /users · GET,PUT,DELETE /users/{u} — CRM ⚠ DELETE no guardrail
  GET /permissions · GET,PUT /permissions/{role} · PATCH /permissions/{role}/{resource} — RBAC matrix
  GET /oversight/chats · GET /oversight/chats/{chat} — eagle-eye chat oversight (read-only, incognito;
    filters ?nature ?flag ?object_type ?object_id ?status ?participant). RETIRES /oversight/{conversations,tickets}
  GET,PUT /configurations — tunable key/values

Support (role:support, prefix /support):
  GET /dashboard — Support acts on chats via the shared /chats map (join/flag/resolve/close, below)
  ⚠ RETIRED: /tickets, /tickets/{t}, /tickets/{t}/reply → folded into /chats (a ticket = chat w/ Support)
  ⚠ edit-any-non-money (PUT /support/{resource}/{id}) + flag→Payments bridge are Planned (below)

Payments (role:payments, prefix /payments):
  GET /dashboard · GET /payments · GET /payments/{p} · POST /…/approve · POST /…/reject
  POST /…/refund · POST /…/payout/release · POST /…/payout/hold — money moves (idempotent, → wallet)
  GET /proofs · POST /proofs/{proof}/approve · POST /…/reject — ⚠ VIOLATES B9, REMOVE (Payments must not review proof content)

Shared — Chats (any authenticated; ONE primitive — §10; nature + ACL DERIVED from participants+objects):
  GET,POST /chats — list caller's chats (derived queue) / open a chat. COUNTERPARTY is set by the ENTRY POINT
    (target: support [default, incl. from an ad/adset/campaign object] | provider [only from a published listing, space attached] | billing→support+payment), NOT inferred from the attached object; object is context only (§10). [owner 2026-07-25]
  GET /chats/{chat} — chat + participants + objects + active flags + messages (PII-masked at render)
  POST /chats/{chat}/messages — post (raw stored, masked at render); same derived ACL
  POST /chats/{chat}/objects · DELETE /…/objects/{obj} — attach/detach an object (OWNERSHIP-checked; system msg)
  POST /chats/{chat}/flags — add/change a flag {type,reason} (Support flags; Payments executes money via §8)
  POST /chats/{chat}/join — Support joins a client↔provider chat (announced + PII relax; role:support)
  POST /chats/{chat}/resolve · /close · /reopen — resolve(support) / close(opener) / reopen(admin ONLY)
  ⚠ RETIRED → /chats: GET,POST /conversations(+/messages); GET,POST /tickets(+/reply);
    GET,PUT /support/tickets/{t}(+/reply); POST /support/conversations/{c}/join;
    POST /support/payments/{p}/flag-refund|flag-payout-hold; the dual-ticket auto-open.
    (The §8 Payments money routes /payments/{p}/refund|payout/release|payout/hold are UNCHANGED —
     money execution is not a chat concern.)

--- PLANNED endpoints (designed, not built) ---
Account-scoped collaborators (BOTH sides — replace the campaign-nested client routes):
  Client:   GET,POST /client/collaborators · DELETE /…/{col}   (subroles publicist, manager)
  Provider: GET,POST /provider/collaborators · DELETE /…/{col}  (subroles installator, sales, supervisor)
Media specs: spaces gain physical_specs+media_specs+upload_instructions; GET /client/spaces/{s}/media-specs; ad upload HARD-FAILS on spec mismatch
Checkout/escrow: POST /client/campaigns/{c}/checkout (ONE payment + ONE invoice) · POST /client/bookings {is_prepay} · GET /provider/spaces/{s}/waiting-list · POST /provider/waiting-list/{offer}/confirm
Ratings: POST /client/campaigns/{c}/rating · GET /spaces/{s} (avg+count)
Wallet: POST /client/wallet/withdraw
Audit: AuditLog::record() internal + GET /admin/audit (?target_type,?target_id)
Moderation: POST /admin/spaces/{s}/takedown · /restore · POST /admin/providers/{u}/freeze · /unfreeze
Support powers: PUT /support/{spaces|ads|users|bookings|collaborators}/{id} (edit-any-non-money, audited).
  Money FLAGS + join now go through the unified /chats map above (POST /chats/{c}/flags, /chats/{c}/join) —
  the old /support/payments/{p}/flag-* and /support/conversations/{c}/join are RETIRED.
Dispute (RETIRES "dual ticket"): on client proof reject, auto-open THREE chats (Support↔Client / ↔Provider /
  ↔Payments), each attached to the held payment+booking with a `payment_held` flag (§7/§10; UC-7).
Notifications: dispatcher + GET /notifications · POST /notifications/{n}/read
Proof-deadline engine + strikes: cron (day3/4 reminders, day5 auto-cancel+refund+strike); strikes table + provider-dashboard counter

Compliance (arch/design/cases → backend):
- CONTRADICTS canon (fix LIVE code): Payments proof approve/reject/index (B9 — remove);
  collaborators campaign-scoped (→ account_id, both sides); bookings.status not canonical enum;
  provider booking PUT bare confirm/cancel (→ Approve/Reject, reason optional); hard DELETE with
  no guardrails.
- DOC feature → NO endpoint: everything under "PLANNED" above.
- COMPLIANT: auth; client/provider/admin CRUD + ownership guards; RBAC matrix; eagle-eye reads;
  money = decimal MXN pesos with idempotent refund/payout → wallet; PII masking at render;
  staff-only chat PII-relax (former ticket internal-note hiding).

== 18. System & Dev ==  [dev]
kanban: `status:review` · `weight:S` · `impacts:all` · `deps:-` · `ids:XC-speccomment-02`

Backend: Laravel 12 (API only), `backend/`. Frontend: Angular 18 SPA (SSR), `frontend/`. DB:
PostgreSQL 17 (Docker host :5435 → container :5432; bare-metal PG17 on :5434, trust auth). Auth:
Sanctum Bearer token in localStorage. CORS: allow `http://localhost:4200` with credentials.
Maps: Leaflet/OSM + what3words (swap to Google via `environment.mapProvider`). Payments mocked
(Mercado Pago / PayPal). SMS: Twilio default / Vonage fallback, mocked. Geo search: bounding-box
(PostGIS = upgrade path). Files: `storage/app/public/` via `storage:link`.

= Dev commands =
Docker is canonical (project **publisher**; backend auto-migrates on boot). BuildKit streaming
is broken on this machine — use the classic builder via the Windows binary from WSL:
`export WSLENV=DOCKER_BUILDKIT:$WSLENV; DOCKER_BUILDKIT=0 "/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe" compose up -d --build`
(backend + nginx :8000 + frontend :4200 + postgres :5435). Migrate/seed/psql via
`docker compose exec`. Bare-metal fallback + Postgres connection details live in CLAUDE.md.
Demo accounts (after seed; password `password`): admin@ / support@ / payments@ / provider1@ /
provider2@ / client1@ / client2@ pubads.test. (Older seed used Paris/EUR — migrating to
Monterrey/MXN; frontend booking list still hardcodes `currency:'EUR'`, should be MXN.)

Implementation status: see §17 (LIVE vs PLANNED endpoint map) and §20 for the build phases —
not duplicated here.

== 19. User Stories (Cases) ==  [all]
kanban: `status:done` · `weight:S` · `impacts:-` · `deps:-` · `ids:-`

> The verbose, story-level source of truth for what each role does. The canonical DESIGN
> (modules, enums, policies) is in §1–18 — when a story and a spec ever disagree, the spec wins.
> All `[owner YYYY-MM-DD]` decisions are folded in. This is intentionally the longest section.

Scenario prose for each role is folded into §1–14 specs and the journeys below; only the
non-redundant UX details unique to stories are kept here.

== 19.0 Use-Case Index ==
Every user story / case / journey carries a stable **UC-N** code (backend functions/classes tag the
UC they satisfy — e.g. `design.md UC-7`; chat/dispute/payment UCs are keyed in
`scratchpad/uc-index.md`). `UC-N — title — primary spec §`:

<!-- [owner 2026-07-29] Promoted two suggested proposals to canonical UCs: F01→UC-41 (actor USER, since every role is a type of user; ACCOUNTS stays reserved for multi-user accounts) and §11→UC-42 (actor CLIENT). UC-42 is framed around user SUCCESS with the unified chat UX, not the tickets→chat merge history. The remaining infra/meta proposals (§0,§1,§15–§20,DASH-1) stay 'suggested' — their actors are docs readers / engineers, not platform users, so they are not user stories. -->
USER (cross-cutting — every role is a type of user)
- UC-41 — User signs in and the platform grants exactly their role's capabilities (RBAC-scoped workspace; failed login is clear & non-leaky) — §1, §14

CLIENT
- UC-1 — Client searches & multi-selects spaces on the map — §5
- UC-2 — Client organizes campaign / adset / ads from the backlog — §4, §5
- UC-3 — Client uploads spec-validated creative — §4, §5
- UC-4 — Client checks out & pays (ONE invoice per campaign) — §8
- UC-5 — Client books-for-later / pre-pays into escrow (AFTER-MVP) — §6
- UC-6 — Client reviews & ACCEPTS proof → payout releasable — §7
- UC-7 — Client REJECTS proof / flags mismatch → dispute (3 chats + hold) — §7, §10
- UC-8 — Client opens a provider inquiry FROM the provider's published space/ad listing (space attached; the ONLY path to a provider — providers have no public profile/UI) — §10
- UC-9 — Client contacts Support from Messages or an ad/adset/campaign object (object = context, provider NOT pulled in); a Billing-opened chat is Support-fronted with the payment attached (Payments looped in via the internal chat) — §10
- UC-10 — Client cancels a booking (refund per policy) — §8
- UC-11 — Client views wallet & withdraws — §8
- UC-12 — Client rates a provider (AFTER-MVP) — §9
- UC-42 — User resolves everything in one seamless conversation — no juggling tickets vs chat (one uniform chat UI; Support, and Payments when money is involved, join the same thread) — §10, §11

PROVIDER
- UC-13 — Provider lists a space & declares specs — §4, §5
- UC-14 — Provider manages availability calendar (iCal sync) — §13
- UC-15 — Provider approves / rejects a booking request — §5
- UC-16 — Provider / Installator uploads the PRIMARY proof — §7
- UC-17 — Provider receives a payout — §8
- UC-18 — Provider views strike counter / appeals a strike — §7

ACCOUNTS & COLLABORATORS
- UC-19 — Owner invites & manages collaborators — §3, §14
- UC-20 — Collaborator acts within subrole scope — §3

SUPPORT
- UC-21 — Support joins a client↔provider chat (announced, PII relax) — §10
- UC-22 — Support investigates a dispute & RAISES a money flag — §7, §10
- UC-23 — Support edits a non-money object (audited) — §12
- UC-24 — Support reverses a strike (audited) — §7

PAYMENTS
- UC-25 — Payments approves/executes payment & payout (processing queue) — §8
- UC-26 — Payments executes a refund — §8
- UC-27 — Payments huddles with Support in the internal chat — §10

ADMIN
- UC-28 — Admin chat oversight (read-only, incognito) — §10, §12
- UC-29 — Admin moderation: takedown / restore / freeze — §12
- UC-30 — Admin configures System Configurations + RBAC matrix — §12, §14
- UC-31 — Admin reviews the immutable audit log — §12
- UC-32 — Admin payout-stop window & clawback decision — §8

END-TO-END JOURNEYS (composite — see below)
- UC-33 — Journey: first-time client, single billboard (UC-1..4, UC-6) — §19
- UC-34 — Journey: first-time provider (UC-13..17) — §19
- UC-35 — Journey: disputed proof (UC-7, UC-22, UC-25, UC-27) — §7, §10
- UC-36 — Journey: ad creative & installation actor boundaries — §5, §7
- UC-37 — Admin deletion guardrails / "programmed for deletion" (no hard-delete with open chat / active booking / unsettled funds) — §12
- UC-38 — Notifications & alert-schema dispatch (domain events → channel/urgency fan-out) — §13
- UC-39 — Provider self-pause / unpublish a space (reversible; no new bookings while paused) — §12
- UC-40 — Proof-deadline cron: day-3/4 reminders → day-5 auto-cancel + full refund + strike — §7
<!-- [owner 2026-07-17] UC-37..40 added from the design audit; their specs live in §12/§13/§7. "Dispute opened" is an ALERT emitted by UC-7's auto-open to Support/Payments, not a separate manual action. -->


= CLIENT UX notes (not already in specs) =  (UC-1, UC-2, UC-3, UC-4)
- Multi-select spaces → "Add to a new/existing campaign" + a 10-second "Go to my new campaign"
  toast; selected spaces sit in the backlog until placed in an adset.
- "Send all selected to a new adset"; the campaign view's Orphan-spaces list has a one-click
  "Move all to new adset"; orphans can't reach checkout.
- The upload control shows the provider's upload-instructions summary inline, kept visible
  during/after upload; on spec-mismatch the upload is rejected with a clear message (e.g. "your
  file must be PDF/X-1a, CMYK, 150 DPI at trim, 1 cm bleed").
- Refund policy, terms & conditions, and privacy policy are linked in the footer and on the
  checkout screen before paying.

= PROVIDER UX notes =  (UC-13, UC-14, UC-15, UC-16, UC-17, UC-18)
- Media specs auto-populate from a per-type template (provider can tighten) plus free-text rules.
- Confirmed bookings move to an installation queue (install date + client file), viewable by
  Installator collaborators.
- Revenue dashboard: upcoming / held / paid / refunded + trailing-90-day strike counter.

(SUPPORT / PAYMENTS / ADMIN story details are fully covered by §2 powers, §7 proof/strikes,
§8 money, §10 chats, §12 admin — UC-21..UC-32; not restated here.)

= NOTIFICATION & ALERT SCHEMA =
Columns: Event | Trigger | Recipients (roles) | Channel | Urgency | Message template (≤140).

| Event | Trigger | Recipients | Channel | Urgency | Template |
|---|---|---|---|---|---|
| New booking request | Client checkout completes | Provider | in-app+email | high | "New booking on {space}: {dates}, {total} MXN. Approve/reject within 48h." |
| Booking approved | Provider approves | Client(+collabs), Support(log) | in-app+email | med | "Your booking on {space} is approved. Display starts {date}." |
| Booking rejected | Provider rejects | Client(+collabs) | in-app+email | high | "Booking on {space} rejected: {reason}. Funds returned to wallet." |
| Booking confirmed (provider picks) | Provider approves one from the queue (chooses which offer wins) | Client, Provider | in-app+email | med | "Booking for {space} on {dates} confirmed. Display begins {date}." |
| Day-3 proof reminder | Display +3d, no proof | Provider | in-app+email | med | "Upload proof for {ad} in 2 days or it auto-cancels." |
| Day-4 proof reminder | Display +4d, no proof | Provider | in-app+email+SMS | high | "Last day to upload proof for {ad}. Tomorrow it auto-cancels + strike." |
| Proof uploaded | Provider uploads | Client(+collabs) | in-app+email | low | "Proof uploaded for {ad}. Review under Campaigns." |
| Proof accepted | CLIENT accepts (not Payments) | Client, Provider, Payments | in-app+email | low | "Proof accepted for {ad}. Payment now releasable." |
| Proof rejected | CLIENT rejects / collab flags | Provider, Client, Support+Payments (3 chats, payout held) | in-app+email | high | "Proof on {ad} rejected: {reason}. Payment held; Payments+Support will review the case." |
| Secondary mismatch flagged | Client/collab flags | Provider, Payments, Support | in-app+email | high | "Mismatch flag on {ad}. Payout held pending review." |
| Cancel by client pre-approval | Client cancels before approval | Provider | in-app+email | low | "Booking on {space} cancelled before approval. No charge." |
| Cancel by client post-approval | Client cancels after approval, pre-display | Provider, Payments, Support | in-app+email | high | "Booking on {space} cancelled. Refund per policy." |
| Cancel by provider after confirm | Provider cancels confirmed booking | Client, Payments, Support, Admin | in-app+email+SMS | critical | "{space} booking cancelled by provider. Full refund to wallet." |
| Auto-cancel on proof miss | Day-5 elapsed, no proof | Client, Provider, Payments, Support | in-app+email | critical | "{ad} auto-cancelled — no proof. Full refund. Strike on provider." |
| Refund issued | Refund executed | Client, Support | in-app+email | high | "{amount} MXN refunded to your wallet for {ad}. Withdrawable now." |
| Refund failed | Gateway error | Payments, Support, Admin | in-app+email+SMS | critical | "Refund for {ad} failed at {gateway}. Manual intervention required." |
| Dispute opened | Client opens dispute | Support, Provider | in-app+email | high | "Dispute opened on {ad}. Support reviews within 24h." |
| Dispute resolved valid | Support sides with client | Client, Provider, Payments | in-app+email | high | "Dispute on {ad} resolved for client. Refund processing." |
| Dispute resolved invalid | Support sides with provider | Client, Provider | in-app+email | med | "Dispute on {ad} closed. Provider payout proceeds." |
| Payout held | Mismatch flag / open dispute | Provider, Payments | in-app+email | high | "Payout for {ad} held pending review." |
| Payout released | Proof accepted, no holds | Provider | in-app+email | low | "Payout of {amount} MXN released for {ad}." |
| Strike accrued | Missed proof / provider cancel / upheld mismatch | Provider, Support | in-app+email | high | "Strike accrued: {reason}. {n}/3 in 90 days." |
| 3-strike admin alert | 3 strikes in 90 days | Support, Admin | in-app+email | critical | "Provider {name} hit 3 strikes. Review for freeze." |
| Support joined chat | Support enters chat | Client, Provider | in-app | low | "Support joined this chat. PII masking relaxed." |
| PII attempted | Pattern match phone/email/URL | Sender | in-app | low | "We masked your message — sharing contact info isn't allowed before Support joins." |
| Collaborator invited | Client adds collaborator email | Invited collaborator | email | low | "You've been invited to collaborate on {account}. Accept to view its campaigns and proofs." |
| Listing taken down | Admin moderates | Provider | in-app+email | high | "Your listing {space} was taken down: {reason}." |
| Listing restored | Admin restores | Provider | in-app+email | low | "Your listing {space} is live again." |
| Provider frozen | Admin freezes | Provider, Payments | in-app+email | critical | "Your provider account is frozen. New bookings paused, upcoming cancelled (clients refunded)." |
| Ad rejected by provider | Provider rejects a file | Client(+collabs) | in-app+email | high | "Your ad on {space} was rejected: {reason}. No charge. Re-upload a corrected file." |
| Pre-pay offer placed | Client pre-pays a slot | Provider, Client | in-app+email | low | "Pre-pay offer on {space} for {dates}. {total} MXN in escrow." |
| Pre-pay offer expired | Slot didn't open in window | Client | in-app+email | med | "Pre-pay offer on {space} expired. {amount} MXN returned to wallet." |
| Calendar setup stale | Calendar older than threshold (7d) | Provider | in-app | low | "Calendar for {space} is {n} days old. Refresh it or connect a live URL." |
| Collaborator opened a chat | Collaborator opens a support chat | Client(owner), Support | in-app+email | med | "{collaborator} opened a support chat on {campaign}." |
| Mismatch flag filed (ack) | Client/collab flags mismatch | Flagger | in-app | low | "Your mismatch flag on {ad} was filed. Payout held; Support will review." |

= MEDIA-DELIVERY SPECS BY SPACE TYPE =
Columns: Space type | Provider declares (physical) | Client must deliver (file).

| Space type | Provider declares (physical) | Client must deliver (file) |
|---|---|---|
| Billboard (printed) | W×H cm; substrate (vinyl/paper/mesh); viewing distance; install method | PDF/X-1a or TIFF; CMYK; 150 DPI at trim¹; 1 cm bleed; outlined fonts; ≤200 MB |
| Digital screen (big, outdoor) | Pixel res (e.g. 1920×1080); orientation; loop slot length (s); brightness (nits); refresh | MP4 H.264 or JPG/PNG; exact res; sRGB; 8–15 s video; ≤50 MB; 30 fps |
| Little screen (indoor) | Pixel res; orientation; loop slot length; ambient context | MP4 H.264 or PNG/JPG; exact res; sRGB; 6–10 s; ≤25 MB; 30 fps |
| Radio station | Frequency (MHz); coverage; spot length (15/30 s); daypart | MP3 or WAV; 44.1 kHz/16-bit min; stereo; −14 LUFS²; exactly 15 or 30 s; ≤10 MB |
| Transit (bus/taxi wrap/car card) | Vehicle type; surface dims cm; substrate; route/fleet size | PDF/X-1a; CMYK; 150 DPI at trim; 1 cm bleed; outlined fonts; ≤150 MB |
| Kiosk (mall/street furniture) | Panel dims cm; backlit y/n; double-sided y/n | PDF/X-1a; CMYK; 200 DPI at trim; 1 cm bleed; outlined fonts; ≤150 MB |

¹ 150 DPI at trim is standard for large-format outdoor viewed from 3 m+; small panels (kiosk)
bump to 200 DPI. ² −14 LUFS aligns with common Mexican broadcast loudness; provider can override.

= Representative end-to-end journeys =
UC-33 — First-time client, single billboard (composes UC-1..UC-4, UC-6): map → detail → new
   campaign → adset → upload validated PDF → checkout → wait approval → live → proof received →
   accept → done.
UC-34 — First-time provider (composes UC-13..UC-17): sign up → list space + declare specs → set
   calendar via URL → first booking → approve → install → upload proof day 2 → payout released.
UC-35 — Disputed proof that travels (composes UC-7, UC-22, UC-25, UC-27): inquiry chat → the same
   chat becomes a confirmed booking chat on approval → client rejects proof for blur → payment
   auto-held (`payments.status=held`) + a `payment_held` flag + THREE chats auto-open (Support↔Client
   / ↔Provider / ↔Payments), each anchored to the held payment+booking (masking relaxes when Support
   joins a client↔provider chat) → Payments+Support review the case across those chats and decide
   (release to provider, or cancel + full refund) — NO automatic re-upload; each chat is one
   chronological history with kind=system transition messages.
UC-36 — Ad creative & installation — WHO does WHAT (actor boundaries; INVARIANT, added to stop the
   recurring "client creates a space/ad" drift):
   - PROVIDER creates the space (billboard, photo/video panel, radio station) with its own
     photos + media-delivery specs. A CLIENT never creates a space, never uploads a space photo,
     and never sets media specs — that is exclusively the provider's job.
   - CLIENT rents spaces from the map; each rented space becomes an ad in the campaign backlog.
     The client's ONLY content action is uploading THEIR creative — the image, video, or audio
     they want shown in that space — for each ad, validated against the space's media-delivery
     specs. The client never mints a space-less ad (see §5 ad-origin invariant).
   - PROVIDER approves the booking and dispatches it to an INSTALLATOR (a subrole employee under
     the provider's own account).
   - INSTALLATOR physically installs the ad and uploads the PROOF of display (photo, or video for
     radio/audio), logged in with their installator subrole under the provider's account. Proofs
     are a provider-side artifact; the client only ACCEPTS or REJECTS a proof, never uploads one.

== 20. Roadmap, Revisions & Known Gaps ==  [all]
kanban: `status:wip` · `weight:S` · `impacts:-` · `deps:-` · `ids:-`

These owner decisions SUPERSEDE earlier text where they conflict (latest wins). The team builds
toward ~90% compliance across the design, not 100% — some older text is intentionally stale.

Revised decisions (override above where older):
- MONEY: DECIMAL MXN pesos everywhere (no centavos).
- COLLABORATORS: ACCOUNT-WIDE for BOTH clients and providers; subroles per ecosystem (client:
  publicist/manager; provider: installator/sales/supervisor); under a "Configurations" tab.
- PROOF/PAYMENTS (B9): the CLIENT accepts/rejects the primary proof — acceptance releases payout,
  rejection HOLDS it; Payments doesn't judge content up front (only a read-only view when a
  payment is on hold). Remove Payments proof approve/reject of content.
- CHATS (§10, supersedes the earlier one-object "conversations" note): ONE primitive — a "ticket" is
  just a chat with a Support participant. A chat carries ZERO..MANY attached objects + flags; its
  nature is DERIVED from participants+objects+flags (no `type` column). Admin+Support eagle-eye; only
  SUPPORT entry is announced (relaxes PII), Admin is silent/read-only. The dispute opens THREE chats
  (not a "dual ticket").
- PROVIDER BOOKING ACTION: Approve (and send to an installator) OR Reject (reason optional). Not
  bare Confirm/Cancel.

Roadmap, per-feature build order, and the live code-vs-design gap list are tracked in
`.claude/todos/mvp-sprint.json` (not duplicated here). Shorthand: PHASE 1 = owner-decision fixes +
UI wiring; PHASE 2 = Support edit-any + audit log, Admin eagle-eye; PHASE 3 (deferred) = strikes,
ratings, notifications, escrow/pre-pay, media-spec validator, moderation, invoice line-items.


== Lineage ID Registry (mirror of design/design.json — design.json is authoritative) ==
kanban: `status:done` · `weight:S` · `impacts:all` · `deps:-` · `ids:-`

[owner 2026-07-25] Stable IDs so specs/UCs/todos can REFERENCE graph elements (not regenerate them).
Planning lineage: UC → spec → flow → class → ER (roots = UC + spec). Metadata is reference-only.

**Flow nodes (FL):**
- `FL01` (start) — Client: map + nearest-first search
- `FL02` (step) — Multi-select spaces -> campaign backlog orphan ads
- `FL03` (step) — Move orphans into an adset
- `FL04` (step) — Upload creative, validated vs media-delivery specs
- `FL05` (step) — Checkout: ONE invoice per campaign, fee added on top
- `FL06` (step) — payments.status = held from booking
- `FL07` (decision) — Provider Approve or Reject?
- `FL08` (step) — Booking rejected - no charge, funds to wallet
- `FL09` (step) — Booking live -> dispatched to Installator
- `FL10` (step) — Display window: proof-pending, deadline default 5d
- `FL11` (decision) — Primary proof uploaded in time?
- `FL12` (step) — Auto-cancel + full refund + provider strike
- `FL13` (step) — proof-uploaded: client reviews
- `FL14` (decision) — Client Accept or Reject?
- `FL15` (step) — proofs.status=client_accepted -> payment releasable
- `FL16` (step) — Payments releases -> processing queue grace window 1h reversible
- `FL17` (end) — Dispatch to gateway -> released -> settled per provider
- `FL18` (step) — payments.status=held + payment_held flag
- `FL19` (step) — Auto-open 3 Support chats: Support-Client / Support-Provider / Support-Payments
- `FL20` (decision) — Payments + Support decision
- `FL21` (step) — Cancel booking + full refund to wallet
- `FL22` (end) — Wallet ledger append-only

**Flow decision branches:**
- `FL07>FL08` → Reject, reason optional [Reject, reason optional]
- `FL07>FL09` → Approve per-ad [Approve per-ad]
- `FL11>FL12` → No, day-5 missed [No, day-5 missed]
- `FL11>FL13` → Yes [Yes]
- `FL14>FL15` → Accept [Accept]
- `FL14>FL18` → Reject / mismatch flag [Reject / mismatch flag]
- `FL20>FL16` → Satisfactory proof, client accepts [Satisfactory proof, client accepts]
- `FL20>FL21` → Uphold dispute [Uphold dispute]

**ER entities (ER):** `ER01`=users · `ER02`=personal_access_tokens · `ER03`=accounts · `ER04`=campaigns · `ER05`=adsets · `ER06`=ads · `ER07`=spaces · `ER08`=space_photos · `ER09`=space_availabilities · `ER10`=bookings · `ER11`=payments · `ER12`=chats · `ER13`=chat_objects · `ER14`=chat_flags · `ER15`=chat_participants · `ER16`=messages · `ER17`=proofs · `ER18`=collaborators · `ER19`=invoices · `ER20`=role_permissions · `ER21`=wallet_entries · `ER22`=system_configurations · `ER23`=(PLANNED) strikes / ratings / audit_logs / account_grants / notifications

**Classes (CL):** `CL01`=Chat · `CL02`=ChatParticipant · `CL03`=ChatObject · `CL04`=ChatFlag · `CL05`=Message · `CL06`=ChatController · `CL07`=Booking · `CL08`=Payment · `CL09`=Proof · `CL10`=WalletEntry
