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

== 1. Overview & Roles ==  [F01]

Web advertising-spaces marketplace: clients book provider ad spaces (billboards, screens,
radio, transit, kiosks), pay per time span, and verify display via proof. Laravel 12 (API,
headless) + Angular 18 SPA; Mexico-first (Monterrey launch), single currency MXN. The five
SYSTEM roles (`users.role` enum = `client|provider|admin|support|payments`):

- **Client** — owns campaigns→adsets→ads; searches spaces on the map, books, pays per
  campaign, reviews proof, chats with providers. No dashboard (lands on Map+Search).
- **Provider** — owns spaces (+photos, availability, pricing); approves/rejects bookings,
  uploads the PRIMARY proof. Lands on a stats dashboard.
- **Admin** — eagle-eye: sees EVERYTHING (all client↔provider chats, Support threads,
  internal Support↔Payments threads), moderates listings (takedown/restore/freeze), edits
  any object, configures System Configurations, reads the immutable audit log, manages users
  + the RBAC matrix. Reads/posts into any thread SILENTLY (🤫, no join announcement). The
  only role above Support and Payments.
- **Support** — investigates user-reported problems on any ad/adset/campaign/space;
  near-admin: edits every NON-money object, joins client↔provider chats ANNOUNCED (📣), can
  only FLAG a refund/payout-hold (never execute), adjudicates mismatches, removes strikes
  (audited), edits provider collaborator roles. Every action audit-logged.
- **Payments** — the ONLY role that moves money ($): approves/rejects payments, executes
  refunds and payouts. Does NOT review proof CONTENT (owner 2026-06-20) and has NO content
  powers; strike accrual is NOT a Payments concern. Lands on a stats dashboard.

(Legacy `Manager\UserController` is the old 3-role MVP name for Admin — compat only.)

== 2. Role × Object Power Matrix ==  [F01,F14]

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
| Proof | 🟢 review+flag | inst:create / publ+mgr:review+flag | 🟢 create (PRIMARY) | inst:create \| sales:· \| sup:review | 👁✏📝 (mismatch) | 👁 (no content review), $hold-on-reject | 👁🤫✏📝 |
| Payment | 👁(own) | mgr:👁 | 👁(payout) | sup:👁 | 👁 flag$ | 🟢 $📝 | 👁🤫 $📝 |
| Wallet / Refund | 👁(own) | mgr:👁 | · | · | flag only | $ execute | 👁🤫 $📝 |
| Invoice | 👁(own) | mgr:👁 | 👁(own) | sup:👁 | 👁✏📝 | 👁 | 👁🤫✏📝 |
| Conversation / Msg | 🟢(own) | publ:🟢 | 🟢(own) | publ:🟢 / sup:🟢 | 📣 join 📝 | 📣 join | 🤫 silent or 📣 📝 |
| Ticket | 🟢 raise | publ+mgr | 🟢 raise | publ:🟢 / sup:🟢 | 🟢 handle 📝 | 🟢 handle | 👁🤫✏📝 |
| Internal thread | · | · | · | · | 🟢 w/Payments | 🟢 w/Support | 👁🤫✏📝 |
| User / account | · | · | · | · | 👁✏📝 (no $) | · | 🟢✏📝 |
| Collaborator | 🟢(own) | · | 🟢(own) | · | 👁✏📝 (provider collab roles) | · | 👁🤫✏📝 |
| RBAC permissions | · | · | · | · | 👁 collab-roles | · | 🟢 employees |
| System config | · | · | provider-admin:🟢(own acct) | · | · | · | 🟢 global |
| Audit log | · | · | · | · | 👁 (writes) | 👁 (writes) | 👁🤫 read-all |

Separation-of-powers (load-bearing; roles defined in §1):
- A client-rejected proof AUTO-HOLDS the payment; an accepted proof makes it releasable for
  MANUAL Payments release. Strikes route Support→Admin, never Payments.
- **Internal Support↔Payments threads** are membership-ACL'd; the client never sees them; Admin
  observes (and may post).
- **Auto-approve**: Admin System Config toggle + amount threshold (auto-release payments under $X),
  default OFF; larger payments still need manual Payments review.
- Audit log is append-only/immutable; every staff (✏/$) action is 📝-logged.

== 3. Accounts & Collaborators ==  [F02]

**Account object** (owner 2026-06-25): a first-class object. Every owner user — a client or a
provider — has exactly ONE account; for MVP an account IS that single owner user (1 user = 1
account). The separate `accounts` object exists from the start so a team of multiple
owner-users can later share one account WITHOUT a schema change. [owner 2026-06-27] An EMAIL
is the account identity: exactly ONE account per email, no two accounts share an email. A
person wanting both a client and a provider account registers two different emails and
switches manually — we do NOT enforce, link, or merge them.

**Collaborators** are invited by email from an account-scoped Collaborators screen under the
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
  SECONDARY proofs; chat with providers; open support tickets. CANNOT see money/billing.
- **Manager** — everything the owner can do INCLUDING money and billing (the ONLY client
  collaborator that sees money).

PROVIDER-side subroles (owner 2026-06-25, confirmed 2026-06-26):
- **Installator** — can ONLY upload proofs (crews who install physical ads and photograph
  them same-day). Sees nothing about money, listings, or other topics.
- **Sales** — provider-side equivalent of Support: answers client messages for information.
  Can ONLY reply to messages/chats — CANNOT remove people, add/edit/delete spaces, or touch
  money. Purely communicational.
- **Supervisor** — can see and EDIT almost everything in the provider account INCLUDING
  money/payouts, EXCEPT three owner-only powers: cannot remove people (manage collaborators),
  cannot adjust account configurations, cannot DELETE spaces.

Money/billing is visible only to the owner and each side's money role (client Manager /
provider Supervisor) — never to Publicists, Sales, or Installators.

== 4. Core Objects & Lifecycles ==  [F04,F06,F07]

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

= Map stack =  [F03]
Leaflet/OpenStreetMap is the map provider, with **what3words** layered on the location picker —
a geocoding service mapping every 3m×3m square on Earth to a fixed three-word address (e.g.
`///filled.count.soap`) so a provider can pin a space precisely without reading raw lat/long.
what3words is OPTIONAL client-side sugar (autosuggest only), stores NO column; the canonical
location stays latitude/longitude/location_name. The whole map stack is swappable to Google
Maps via `environment.mapProvider`.

= Landing & nearest-first sidebar =  [F03]
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

= Booking action & per-ad go-live =  [F06]
Provider acts on a request with **Approve** or **Reject** only — NO counter-offers. The
rejection reason is OPTIONAL (a given reason helps the client re-submit a corrected file). The
request queue shows client, dates, total, and the attached media file. Confirmed bookings move
to an installation queue (install date + the client's file), staffed by Installator-role
collaborators. Go-live is PER-AD, not all-or-nothing: each ad is approved, goes live, and bills
on its own approval independently — a slow/rejected provider never blocks the rest of the
campaign. An inquiry chat becomes a confirmed booking thread only when BOTH provider approval
AND client payment are complete; either alone leaves it an inquiry.

== 6. Book-for-later, Pre-pay & Escrow (AFTER-MVP) ==  [F15]

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

= Primary & secondary proofs =  [F07]
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

= Review, payout & client reject → auto-hold =  [F07]  [owner 2026-07-10: NO re-upload loop; 2026-07-14: dispute opens 3 Support threads]
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
  proof") — attached to the payment so Payments has context; and (3) THREE Support-anchored chat
  threads auto-open so humans can inspect the whole situation:
    · **Support↔Payments** (internal, `type=internal`) — the money decision; the ONLY thread Payments is in.
    · **Support↔Client** — Support investigates with the client (provider not present).
    · **Support↔Provider** — Support asks for a real/corrected proof (client not present).
  There is NO automatic re-upload window or clock — any request for a new proof is a HUMAN one
  Support makes in the Support↔Provider thread.
- Releasing a held payout is a MANUAL, human decision by **Payments together with Support** after
  they inspect the case across those threads: either release to the provider (e.g. once a
  satisfactory proof is posted and the client accepts), or cancel the booking and refund the
  client in full. Support leans toward the client and watches for fraud; Payments executes the
  money movement. Nothing beyond the hold + flag + thread-opening is automatic.

Proof photos are NOT auto geotag-checked against the space lat/long — the space belongs to the
provider, who knows where it is; a proof that doesn't match the booked ad is caught by the
client/collaborator mismatch-flag → Support/Payments path instead.

= Strikes (AFTER-MVP) =  [F16]
Strikes accrue on missed proof deadline, provider cancel of a confirmed booking, or upheld
mismatch; accrual is decoupled from notification delivery. NOT a Payments power — routes Support
then Admin. 3 strikes in a trailing 90-day window flag the account for Admin review (possible
freeze); count shows on the provider dashboard. Provider appeals via ticket; SUPPORT (not
Payments) can remove a strike from the counter (reversible, audited).

== 8. Money ==  [F10]

All money is **decimal MXN pesos** — `decimal(12,2)`, single currency, no centavos (owner
2026-06-15: "forget cents"). Gateway callbacks settling in another currency (e.g. PayPal USD)
are normalized to MXN **at capture**; never treat the raw gateway number as MXN. Timestamps
stored UTC; deadline math against the admin-default timezone (Monterrey at launch).

**Gateway.** Mercado Pago and PayPal (`payments.payment_platform = mercadopago|paypal`), mocked
until keys exist. Webhooks signature-verified, persisted raw, idempotent by gateway event id,
with a nightly reconciliation job. The gateway processing fee is paid GROSS by the CLIENT —
added on top at checkout, never absorbed by the platform or netted from the payout.

**Payment lifecycle.** `payments` belongs to a booking; status `pending|completed|failed|
refunded|held|released`. The client's payment is HELD from booking until the CLIENT accepts the
primary proof (Payments does NOT review proof content — §7/§11). Client-accept → releasable;
client-reject → HELD with a dual Support↔Payments ticket. `payout-held` is a `payments.status`
concept, NOT a booking state.

**Escrow (AFTER-MVP — see §6).** Pre-pay funds → escrow (`wallet_entries.type=escrow_capture`),
released on confirm/cancel (`escrow_release`); capture/release + booking state change in ONE
row-locked txn. Payout state machine `held → released → settled` (single transition, holds
re-checked in the locked txn).

**Wallet ledger.** `wallet_entries` is append-only, double-entry: `user_id`, signed `amount`
(`decimal(12,2)` MXN), `type {refund|withdrawal|escrow_capture|escrow_release|adjustment}`,
`ref_type`, `ref_id`, `idempotency_key` (UNIQUE), `created_at`. Balance = `SUM(amount)`, never a
writable column. Wallet states `{active|frozen}`; withdraw is blocked while any dispute/hold is
open (`POST /client/wallet/withdraw`). Every refund/payout carries an idempotency key so a
retried gateway call or re-fired cron cannot double-pay; enforce `SUM(refunds) ≤ captured`.

**Refunds.** Payments executes ALL refunds (mocked); Support only FLAGS. [owner 2026-06-27]
Cancellation refunds are **100% by default** UNLESS the booking was already IN PROCESS (install
work started / teams dispatched), then reduced to cover work performed — stated in the Terms &
Conditions accepted at checkout (supersedes the earlier 90/5/5 split). Free pre-approval cancel;
nothing post-display unless a proof fails; full refund on proof-miss auto-cancel or provider
cancel. Clawback (refunded client later suspended for fraud): platform FREEZES the wallet + alerts
Payments; Payments cannot pull funds — the frozen balance waits for an Admin decision (claw back
via negative ledger entry / keep frozen / release).

**Payouts.** Happen ASAP but must be APPROVED by Payments first; Support flags only. Once
approved and nothing holds it, **Admin** has a configurable window (default 24 h) to stop it;
if Admin doesn't, funds release automatically to the provider's Mercado Pago / PayPal. Payouts
are **PER-PROVIDER** even within one multi-provider campaign invoice — each provider is paid on
their own schedule. A payout held by an open dispute stays held until human adjudication (not a
timer), then pays in full if resolved in the provider's favor (or is refunded per outcome).

**Invoices.** `invoices` belongs to campaign: `invoice_number` (unique), `total_amount`, status
`draft|issued|paid|overdue|cancelled`. **ONE invoice per campaign** (one checkout covers all ads
regardless of provider), with **per-ad line items** and **per-provider payouts**. An orphan ad
(not in an adset) cannot go live and its payment cannot be processed.

== 9. Ratings (AFTER-MVP) ==  [F17]

Provider star rating (1–5 + count) on each space detail = average of per-campaign ratings +
optional comment; `GET /spaces/{s}` returns avg+count. **Eligibility:** only clients rate, only
after a campaign with that provider **ended OR ran live ≥30 days**, **one per client per campaign**
(`POST /client/campaigns/{c}/rating`). [owner 2026-06-27] Ratings are IMMUTABLE and fully
transparent — no client edit/delete; abusive comments flagged for Support, removed only by rare
MANUAL admin action, never auto-hidden.

== 10. Chat & PII ==  [F08]

**Three chat surfaces:** (1) contextual client↔provider chat on every ad/adset/campaign; (2) a
global Help-menu chat for every user on an account; (3) the support-ticket chat (auto-attaches
object context: provider, dates, payment status, proof state).

**PII masking** is one server-side service, evaluated **per-message at render** against the
viewer's role and the thread's join state — never store only-masked content if Admin/audit needs
the raw. Masked in client↔provider chat: phone, email, full URL, off-space street address.
Masking RELAXES when Support joins (announced by a system message) and NEVER applies on internal
staff threads. When Support joins it can read/post and sees the FULL prior history unmasked
(near-admin level). **Admin is the exception to the announcement rule:** Admin reads and posts into
any thread SILENTLY/incognito — no system message announces entry.

**Staff access to a client↔provider thread** [owner 2026-07-14]: the two participants always;
SUPPORT only AFTER it has joined (announced) — before joining Support cannot read it; ADMIN via
the read-only oversight endpoints (silent). PAYMENTS does NOT enter client↔provider threads at
all — it works through the internal Support↔Payments thread, where Support relays the needed
context. (So the masking-relax trigger for a client↔provider thread is a Support join, not a
Payments one.)

**Street-address whitelist.** In a thread tied to a space's booking, that space's ONE stored
street address (and close variants) is whitelisted and shown normally — the client needs it to
install/photograph the ad — while every OTHER address-like string is still masked.

**Polymorphic conversation + travel history.** A thread anchor (`type`, `subject_type`,
`subject_id`) + a `conversation_links` history of contexts it has traveled (inquiry → booking →
ticket), + `messages.kind` for system-transition messages. One conversation travels as one
chronological thread. (Current table `conversations` is keyed on space+client+provider with
`unique(space_id, client_user_id)` — to be reconciled to polymorphic anchors.)

**Internal Support↔Payments threads** are SEPARATE conversation rows (`type=internal`) with a
membership-based ACL — never a visibility boolean on a shared client thread. The client never
sees them. (Add a test asserting a client can never fetch an internal-thread message.) Admin can
observe AND post into all internal threads. The account owner can see any conversation a
collaborator has.

**Support-anchored dispute threads** [owner 2026-07-14]: on a proof-reject dispute (§7) Support
opens a direct 1:1 thread with EACH party — `type=support_client` (Support↔Client) and
`type=support_provider` (Support↔Provider), alongside the `type=internal` Support↔Payments
thread. Each is membership-based: only Support (+Admin) and that ONE counterparty; the other
counterparty and Payments never see it. These carry no PII masking (staff thread). This is how
Support inspects the situation and, by human decision, asks the provider for a real proof — there
is no automatic re-upload.

== 11. Tickets & Support ==  [F09]

**Ticket from any object.** A "Talk to support" button on any campaign/adset/ad/space opens a
thread that auto-attaches object context (provider, dates, payment status, proof state).
`tickets` is polymorphic: reporter `user_id`, `ticketable_type`/`ticketable_id`, `subject`,
`description`, status `open|in_progress|waiting_user|resolved|closed`, priority
`low|medium|high|urgent`, `assigned_to_user_id`. Ticketable covers Ad|Adset|Campaign|Space —
**plus `payment`** (the dual-ticket path below). `ticket_messages` carry `is_internal` staff-only
notes. Reference-less tickets are also allowed.

**Shared CRUD:** `GET,POST /tickets · GET /tickets/{t} · POST /tickets/{t}/reply` for any
authenticated user; Support has its own queue (`/support/tickets`, `…/reply`).

**Support powers.** Support **edits any NON-money object** (`PUT /support/{spaces|ads|users|
bookings|collaborators}/{id}` — audited) but has NO money authority: it only **FLAGS** a refund
or payout-hold (`POST /support/payments/{p}/flag-refund`, `…/flag-payout-hold`) for Payments to
decide+execute. Joins client↔provider chats via `POST /support/conversations/{c}/join` (announced;
Admin silent). Strike accrual/removal is Support, not Payments. Support can CLOSE a ticket without
user consent, but closing resolves only the ticket STATE — the conversation stays LIVE/re-openable
(not deletion, not a gag). Support-initiated ad-hoc tickets are visible only to the provider involved.

**Dispute on proof reject** — see §7. Automatic: payout AUTO-HELD; a FLAG (`ticketable = payment`,
reason text `Payment held — <reason>`) so Payments has money context; and THREE Support-anchored
chat threads open — Support↔Payments (`type=internal`, the only one Payments is in), Support↔Client,
Support↔Provider. NO re-upload cycle (any new-proof request is a human one in the Support↔Provider
thread). Release/refund is a manual Payments+Support decision; Support leans client, default
resolution = cancel + full refund.

== 12. Admin: Moderation, Freeze, Audit, Configurations ==  [F11,F12]

Admin is the **eagle-eye** role: reads EVERYTHING (all chats, all Support threads, all internal
Support↔Payments threads, the audit log, users, RBAC). Admin posts into any thread
**silently/incognito** (the announcement rule applies only to Support). Admin has no
money-execution role beyond the payout-stop window.

**Three levels of taking a listing/provider "off":**
- **Pause / unpublish** — provider's own action; hides their own listing; reversible by them.
- **Takedown / restore** — admin moderation; forcibly hides/restores a listing; the provider
  CANNOT self-reverse. `POST /admin/spaces/{s}/takedown`, `POST /admin/spaces/{s}/restore`.
- **Freeze** — admin, account-level: pauses NEW bookings AND auto-cancels ALL upcoming bookings
  on that provider, each with a full refund to the client's wallet. `POST /admin/providers/{u}/
  freeze`, `…/unfreeze`. A freeze busts that user's permission/session cache immediately and
  revokes active Sanctum tokens (do not wait for the 60-min RBAC cache).

**Deletion guardrails (EVERY deletable object — users, campaigns, adsets, ads, spaces):** a hard
delete is BLOCKED whenever the object has open support tickets, a booking in progress, or
unsettled funds. A CAMPAIGN cannot be hard-deleted while any of its ads has an active/booked
space or an open ticket; a USER cannot be deleted with active bookings or unsettled funds.
Instead deletion is a **"programmed for deletion"** state: the object is unpublished/closed to
new activity, stops receiving new bookings/payments, and stays visible with the legend "this is
programmed for deletion" until current bookings finish, then is removed.

**Immutable audit log.** Append-only; one entry per action of ANY role with **actor, target,
before/after, timestamp**; queryable as per-object history; Admin read-only. Internals (AFTER-MVP):
DB-level immutability (INSERT-only grant + trigger raising on UPDATE/DELETE, optional hash-chain);
audit row written in the SAME txn as the state change (async via outbox); `insert_data.py
--erase-all-data` env-guarded and never truncates audit in a real env. API: `AuditLog::record()`
+ `GET /admin/audit` (`?target_type`, `?target_id`).

**System Configurations (Admin-only; defaults in parentheses):** proof deadline (5 d) ·
strike window (90 d) + 3-strike threshold · pre-pay offer expiry ·
calendar-staleness threshold (7 d) · payout-stop window (24 h) · cancellation refund policy
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
rejected (payment held), support tickets, payments stopped — plus queue depths, refund rate, strike rate,
gateway health. Each indicator links to a list; each list item opens its full detail in
eagle-eye mode (conversations opened SILENTLY).

**Rate limits.** Per-IP and per-account caps on login, chat send, file upload, search, booking
submission, with a burst allowance for known-good accounts; throttled responses include
retry-after. Thresholds are admin-configurable.

== 13. Notifications & Calendar ==  [F13]

**Dispatcher (AFTER-MVP).** Emit domain events (`BookingApproved`, `ProofRejected`, …); queued
listeners fan out via a channel/urgency lookup — no scattered `Notification::send`. API: internal
dispatch + `GET /notifications`, `POST /notifications/{n}/read`. The full ~35-event alert schema
table is in §19.

**Channels.** in-app / email / SMS-mock; most are simple in-app text at the top of the UI, some
surface as a Support/Payments ticket. SMS is reserved for Payments alerts and cancellations only
(Twilio default, Vonage fallback; mocked until keys set). Strike alerts go to Provider + Support
(and Admin on the 3rd) — never Payments.

**Calendar (iCal sync + staleness).** The provider availability UI strongly recommends
connecting a live calendar URL (.ical or public Google/Outlook) because it's easier to keep
current; a "?" help button explains how to find the URL. API: `GET,POST /provider/spaces/{s}/
availabilities`, `DELETE /…/{av}`, `POST /…/sync-ical`, `POST /…/import-ical`.
- **Staleness clock** is measured from the LAST SUCCESSFUL SYNC (URL/iCal) or LAST MANUAL EDIT
  (hand-managed) — never from space creation. If older than the threshold (default 7 d), an
  alert appears next to that space in the provider's list. A healthy auto-syncing URL never fires.
- If an imported calendar goes OFFLINE, the space FALLS BACK to its last-known/manual range
  rather than vanishing, and the staleness alert prompts a fix.

== 14. RBAC & Capabilities ==  [F01,F14]

**Two layers.** The global `role_permissions` matrix governs SYSTEM roles only; a SEPARATE
per-ACCOUNT grant overlay governs collaborators, evaluated AFTER the global matrix and scoped so
grants never leak across accounts.

**Global matrix (`role_permissions`).** Which actions (create, read, update, delete) each role
has on each resource/screen: users, campaigns, adsets, ads, spaces, space_photos,
space_availabilities, bookings, payments, proofs, tickets, conversations, invoices,
collaborators, dashboard.
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
`payout.release`, `strike.accrue` vs `strike.reverse`, `internal_thread.read` vs `.post`.
Support gets flag-only; Payments gets execute-only; Admin gets read on internal threads.

**Timezone** (owner 2026-06-25). All timestamps STORED in UTC. Deadline math (proof deadline,
reminders, auto-cancel) is computed against the platform DEFAULT timezone — **America/Monterrey**
at launch — an Admin System-Configurations setting, retargetable without code. DISPLAY is
localized PER USER (each user may set their own tz; default = platform tz). The canonical
deadline instant is identical for everyone; only rendering varies — never compute deadlines off
the requester's local clock.

== 15. Data Schema ==  [infra]

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
  id, booking_id, amount, status (enum: pending|completed|failed|refunded|held|released),  -- payout-held lives here
  payment_method (mocked), payment_platform (mercadopago|paypal), transaction_id,
  approved_by_payments, approved_by_user_id, timestamps

conversations  [belongs to: space + client_user + provider_user]
  id, space_id, client_user_id, provider_user_id, type (direct|support|internal…), support_joined_at,
  unique(space_id, client_user_id), timestamps
  -- target: polymorphic anchor + conversation_links history; internal threads = SEPARATE rows (type=internal), membership ACL

messages  [belongs to: conversation + sender_user]
  id, conversation_id, sender_user_id, body, kind (user|system…), is_read, timestamps

proofs  [belongs to: ad + booking + uploaded_by_user]
  id, ad_id, booking_id, uploaded_by_user_id, media_type (image|video), file_path, file_name, notes,
  status (enum: pending|uploaded|client_accepted|client_rejected),  -- CLIENT accepts/rejects, NOT Payments
  reviewed_by_user_id (= acting CLIENT), reviewed_at, deadline, timestamps

tickets  [belongs to: user(reporter) + assigned_to_user; polymorphic ticketable]
  id, user_id, ticketable_type, ticketable_id, subject, description,
  status (enum: open|in_progress|waiting_user|resolved|closed), priority (enum: low|medium|high|urgent),
  assigned_to_user_id, timestamps   -- ticketable covers Ad|Adset|Campaign|Space (PLANNED: add 'payment')

ticket_messages  [belongs to: ticket + user]
  id, ticket_id, user_id, body, is_internal (staff-only notes), timestamps

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
─> Proof. Space+client+provider ─> Conversation ─< Message. User ─< Ticket >─ ticketable ─<
TicketMessage. User ─< WalletEntry.

== 16. Data Model & Integrity Invariants ==  [infra]

Integrity rules (terse; most are AFTER-MVP — build when their subsystem ships):
- **Money [MVP]:** wallet = append-only entries, balance = `SUM(amount)` (decimal pesos), never a
  writable column; every refund/payout has a UNIQUE idempotency_key (no double-pay); `SUM(refunds) ≤ captured`.
- **Money [AFTER-MVP]:** escrow capture/release + booking change in one row-locked txn; payout
  `held→released→settled` single-transition; gateway webhooks signed/idempotent + nightly reconcile.
- **Concurrency:** MVP = lightweight app-level overlap check on booking create. AFTER-MVP = Postgres
  `EXCLUDE USING gist` + `SELECT … FOR UPDATE` on slot/payout/expiry; pre-pay confirm atomically
  cancels+refunds sibling offers; auto-cancel cron re-checks `proof IS NULL` in-txn.
- **Snapshots [MVP]:** config snapshot onto booking at creation; "apply to in-flight" checkbox re-applies.
- **RBAC:** global matrix for system roles; collaborators via per-account `account_grants` overlay
  (scoped, no cross-account leak). Provider freeze busts perm/session cache + revokes tokens. [account_grants = AFTER-MVP]
- **AFTER-MVP:** `display_start` stored timestamp for proof crons; polymorphic conversations +
  internal-thread membership ACL; DB-level audit immutability (INSERT-only + trigger); strikes
  decoupled from notifications + reversible.
- **NOTE:** `proofs.status` is currently a SUPERSET (legacy `pending_review/approved/rejected` +
  B9 `client_accepted/client_rejected`) until the deprecated Payments proof-review is removed.

== 17. Backend Endpoint Map ==  [infra]

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
  POST /proofs/{proof}/reject — client rejects → payout HELD + ticket on payment (B9, LIVE; flag-mismatch aliases this)
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
  GET /oversight/conversations · GET /…/{c}/messages · GET /oversight/tickets · GET /…/{t} — eagle-eye (read-only)
  GET,PUT /configurations — tunable key/values

Support (role:support, prefix /support):
  GET /dashboard · GET /tickets · GET,PUT /tickets/{t} · POST /tickets/{t}/reply
  ⚠ tickets-only — edit-any-non-money, conversation-join, flag→Payments bridge are Planned

Payments (role:payments, prefix /payments):
  GET /dashboard · GET /payments · GET /payments/{p} · POST /…/approve · POST /…/reject
  POST /…/refund · POST /…/payout/release · POST /…/payout/hold — money moves (idempotent, → wallet)
  GET /proofs · POST /proofs/{proof}/approve · POST /…/reject — ⚠ VIOLATES B9, REMOVE (Payments must not review proof content)

Shared (any authenticated):
  GET,POST /conversations · GET,POST /conversations/{c}/messages — chat (PII-masked at render)
  GET,POST /tickets · GET /tickets/{t} · POST /tickets/{t}/reply — ticketable ad|adset|campaign|space ⚠ add 'payment'

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
Support powers: PUT /support/{spaces|ads|users|bookings|collaborators}/{id} · POST /support/payments/{p}/flag-refund · /flag-payout-hold · POST /support/conversations/{c}/join
Dual ticket: on client proof reject, auto-open ONE ticket (ticketable=payment) for BOTH Support+Payments
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
  ticket internal-note hiding.

== 18. System & Dev ==  [dev]

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

> The verbose, story-level source of truth for what each role does. The canonical DESIGN
> (modules, enums, policies) is in §1–18 — when a story and a spec ever disagree, the spec wins.
> All `[owner YYYY-MM-DD]` decisions are folded in. This is intentionally the longest section.

Scenario prose for each role is folded into §1–14 specs and the journeys below; only the
non-redundant UX details unique to stories are kept here.

= CLIENT UX notes (not already in specs) =
- Multi-select spaces → "Add to a new/existing campaign" + a 10-second "Go to my new campaign"
  toast; selected spaces sit in the backlog until placed in an adset.
- "Send all selected to a new adset"; the campaign view's Orphan-spaces list has a one-click
  "Move all to new adset"; orphans can't reach checkout.
- The upload control shows the provider's upload-instructions summary inline, kept visible
  during/after upload; on spec-mismatch the upload is rejected with a clear message (e.g. "your
  file must be PDF/X-1a, CMYK, 150 DPI at trim, 1 cm bleed").
- Refund policy, terms & conditions, and privacy policy are linked in the footer and on the
  checkout screen before paying.

= PROVIDER UX notes =
- Media specs auto-populate from a per-type template (provider can tighten) plus free-text rules.
- Confirmed bookings move to an installation queue (install date + client file), viewable by
  Installator collaborators.
- Revenue dashboard: upcoming / held / paid / refunded + trailing-90-day strike counter.

(SUPPORT / PAYMENTS / ADMIN story details are fully covered by §2 powers, §7 proof/strikes,
§8 money, §11 tickets, §12 admin — not restated here.)

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
| Proof rejected | CLIENT rejects / collab flags | Provider, Client, Support+Payments (ticket, payout held) | in-app+email | high | "Proof on {ad} rejected: {reason}. Payment held; Payments+Support will review the case." |
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
| Support joined chat | Support enters thread | Client, Provider | in-app | low | "Support joined this thread. PII masking relaxed." |
| PII attempted | Pattern match phone/email/URL | Sender | in-app | low | "We masked your message — sharing contact info isn't allowed before Support joins." |
| Collaborator invited | Client adds collaborator email | Invited collaborator | email | low | "You've been invited to collaborate on {account}. Accept to view its campaigns and proofs." |
| Listing taken down | Admin moderates | Provider | in-app+email | high | "Your listing {space} was taken down: {reason}." |
| Listing restored | Admin restores | Provider | in-app+email | low | "Your listing {space} is live again." |
| Provider frozen | Admin freezes | Provider, Payments | in-app+email | critical | "Your provider account is frozen. New bookings paused, upcoming cancelled (clients refunded)." |
| Ad rejected by provider | Provider rejects a file | Client(+collabs) | in-app+email | high | "Your ad on {space} was rejected: {reason}. No charge. Re-upload a corrected file." |
| Pre-pay offer placed | Client pre-pays a slot | Provider, Client | in-app+email | low | "Pre-pay offer on {space} for {dates}. {total} MXN in escrow." |
| Pre-pay offer expired | Slot didn't open in window | Client | in-app+email | med | "Pre-pay offer on {space} expired. {amount} MXN returned to wallet." |
| Calendar setup stale | Calendar older than threshold (7d) | Provider | in-app | low | "Calendar for {space} is {n} days old. Refresh it or connect a live URL." |
| Collaborator opened a ticket | Collaborator opens ticket/chat | Client(owner), Support | in-app+email | med | "{collaborator} opened a support ticket on {campaign}." |
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
1. First-time client, single billboard: map → detail → new campaign → adset → upload validated
   PDF → checkout → wait approval → live → proof received → accept → done.
2. First-time provider: sign up → list space + declare specs → set calendar via URL → first
   booking → approve → install → upload proof day 2 → payout released.
3. Disputed proof that travels: inquiry chat → booking thread on approval → client rejects proof
   for blur → payment auto-held + ONE ticket auto-routed to Support&Payments (masking relaxes when
   Support joins) → Payments+Support review the case together and decide (release to provider, or
   cancel + full refund) — NO automatic re-upload; all one chronological thread with
   system-transition messages.
4. Ad creative & installation — WHO does WHAT (actor boundaries; INVARIANT, added to stop the
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

These owner decisions SUPERSEDE earlier text where they conflict (latest wins). The team builds
toward ~90% compliance across the design, not 100% — some older text is intentionally stale.

Revised decisions (override above where older):
- MONEY: DECIMAL MXN pesos everywhere (no centavos).
- COLLABORATORS: ACCOUNT-WIDE for BOTH clients and providers; subroles per ecosystem (client:
  publicist/manager; provider: installator/sales/supervisor); under a "Configurations" tab.
- PROOF/PAYMENTS (B9): the CLIENT accepts/rejects the primary proof — acceptance releases payout,
  rejection HOLDS it; Payments doesn't judge content up front (only a read-only view when a
  payment is on hold). Remove Payments proof approve/reject of content.
- CONVERSATIONS: a separate object that can have one attached object; Admin+Support eagle-eye;
  only SUPPORT entry is announced, Admin is silent.
- PROVIDER BOOKING ACTION: Approve (and send to an installator) OR Reject (reason optional). Not
  bare Confirm/Cancel.

Roadmap, per-feature build order, and the live code-vs-design gap list are tracked in
`.claude/todos/mvp-sprint.json` (not duplicated here). Shorthand: PHASE 1 = owner-decision fixes +
UI wiring; PHASE 2 = Support edit-any + audit log, Admin eagle-eye; PHASE 3 (deferred) = strikes,
ratings, notifications, escrow/pre-pay, media-spec validator, moderation, invoice line-items.
