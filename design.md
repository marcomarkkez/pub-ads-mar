This document is the SINGLE canonical design reference for the whole app (API + headless
frontend). It absorbed the former ARCHITECTURE.md, cases.md, platform-graph.md, and the
useful parts of compliance-report.md (consolidated 2026-06-27) — design.md is now the one
home for schema, routes, specs, decisions, the role/object graph, and user stories.
`[owner YYYY-MM-DD]` marks an explicit owner decision; latest decision wins on conflict.
Sections 1–18 are the SPECS (kept tight); section 19 is the verbose USER STORIES.

== 1. Overview & Roles ==

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

== 2. Role × Object Power Matrix ==

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

Separation-of-powers (load-bearing):
- **Support FLAGS only** — investigates, edits non-money objects, flags a refund/payout-hold;
  cannot execute money. Adjudicates mismatches; removes strikes (audited).
- **Payments EXECUTES only** — sole money mover; NO content powers, NO proof-content review,
  NOT a strike actor. A client-rejected proof AUTO-HOLDS the payment; an accepted proof makes
  it releasable for MANUAL Payments release.
- **Admin EAGLE-EYE** — sees/edits everything, SILENT by default (may announce per ticket),
  owns global config + employee RBAC, reads all audit. Above Support and Payments.
- **Strikes** route Support→Admin, never Payments.
- **Internal Support↔Payments threads** are membership-ACL'd; the client never sees them;
  Admin observes (and may post).
- **Auto-approve**: Admin System Config toggle + amount threshold (auto-release payments under
  $X), default OFF; larger payments still need manual Payments review.
- Audit log is append-only/immutable; every staff (✏/$) action is 📝-logged.

== 3. Accounts & Collaborators ==

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

== 4. Core Objects & Lifecycles ==

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
- **rejected** — provider rejected the request (no charge), OR the proof stayed rejected past
  the re-upload window with no resolution.
- **cancelled** — withdrawn or auto-cancelled (e.g. proof deadline missed → refund + strike).

`payout-held` is NOT a booking state — it lives on `payments.status` (Payments-only). ADS keep
their OWN SEPARATE enum (`draft|pending_approval|approved|rejected|active|paused|completed|
cancelled`) — ads and bookings are deliberately NOT unified. The client UI's three visible
legends (waiting for approval / waiting for booking confirmation / live) map onto this set.

= proofs.status enum (FOUR values) =
- **pending** — deadline running, no file uploaded yet.
- **uploaded** — provider posted the proof, awaiting the client's decision.
- **client_accepted** — client (or a collaborator, per role) approved it → payout releasable.
- **client_rejected** — client rejected or flagged a mismatch → 48 h re-upload window, payout HELD.

A missed deadline is a BOOKING outcome (auto-cancel), NOT a proof status.

= What an enum is, and why ONE canonical list =
An enum is the fixed, CLOSED set of allowed values a status column may hold — a booking's
status can only ever be one of these named states. We keep a SINGLE canonical list so the DB
column, backend logic, Angular UI, and these docs all agree on which states exist and what
each means; if each layer invents its own names (`active` vs `live`, `waiting_proof` vs
`proof-pending`) the state machine, filters, and deadline/payout rules silently drift and break.

= Cheapest-decomposition pricing =
When a booked window straddles two of a provider's pricing tiers (e.g. per-day and per-month),
the price is the CHEAPEST valid decomposition: fit the largest cheaper blocks first (e.g. 40
days = 1 month + 10 days if that beats 40 × daily), so a longer booking is never penalized.

== 5. Catalog, Search & Booking ==

= Map stack =
Leaflet/OpenStreetMap is the map provider, with **what3words** layered on the location picker —
a geocoding service mapping every 3m×3m square on Earth to a fixed three-word address (e.g.
`///filled.count.soap`) so a provider can pin a space precisely without reading raw lat/long.
what3words is OPTIONAL client-side sugar (autosuggest only), stores NO column; the canonical
location stays latitude/longitude/location_name. The whole map stack is swappable to Google
Maps via `environment.mapProvider`.

= Landing & nearest-first sidebar =
Client lands on a map centered on their geolocation (default Monterrey, NL). The right rail
(3-pane layout: menu | map | detail sidebar) lists nearby spaces sorted near-to-far via the
map provider's distance calc (or simple lat/long triangulation), each tagged with type
(billboard, big screen, little screen, radio station, kiosk, transit). The list is
multi-select; selecting one or more shows two buttons below: "Add to an existing campaign" /
"Add to a new campaign" (new campaign → 10-second toast with "Go to my new campaign").
Selecting a space shows its full detail in the right sidebar.

= Radio-in-map-range =
"In range" for a radio station = inside the current MAP view range (typically a whole city) —
NOT listener location or broadcast footprint. Changing the map center re-sorts the list and
re-queries radio stations in range; pan/zoom updates the station list.

= Filters (exact dates) & backlog =
Filters: space type, date range, budget per day. Dates are EXACT — there is NO date-flexibility
(±X days) filter; clients/providers add slack via wider windows or "book for later."
Availability turns green/red for the chosen date range. Spaces whose calendar doesn't coincide
are TAGGED ("doesn't coincide with your timespan"), not hidden, and remain bookable "for later."
The campaign view shows an Orphan-spaces list (backlog spaces not yet in any adset); a client
MAY check out a partial campaign, but any orphaned ad cannot go live and its payment cannot be
processed — orphans can be bulk-moved into an adset to become bookable. The upload control
shows the provider's media-delivery summary + free-text instructions inline, kept visible
during and after upload.

= Booking action & per-ad go-live =
Provider acts on a request with **Approve** or **Reject** only — NO counter-offers. The
rejection reason is OPTIONAL (a given reason helps the client re-submit a corrected file). The
request queue shows client, dates, total, and the attached media file. Confirmed bookings move
to an installation queue (install date + the client's file), staffed by Installator-role
collaborators. Go-live is PER-AD, not all-or-nothing: each ad is approved, goes live, and bills
on its own approval independently — a slow/rejected provider never blocks the rest of the
campaign. An inquiry chat becomes a confirmed booking thread only when BOTH provider approval
AND client payment are complete; either alone leaves it an inquiry.

== 6. Book-for-later, Pre-pay & Escrow ==

When a requested window isn't yet free, the client sees "Book it for later." Two queues exist
per space:
1. **PRE-PAY** — funds are captured into escrow; the booking joins the provider's per-space
   pre-pay waiting list. The provider picks which booking to confirm when the slot opens, by
   their own criteria (longer window, repeat client, etc.). The provider sees this waiting list
   as a SIMPLE list with NO payment/amount information, and has a trash action to reject
   bookings they don't want. Escrow funds are held until the provider confirms; if the slot
   never opens within the offer's expiry window (admin-configurable), the client is refunded to
   wallet credit, gets a notification, and the refund is logged for Payments/Admin.
2. **NON-PRE-PAY** — queued with NO funds captured, resolved first-come-first-served when the
   calendar frees.

One checkout per campaign covers all ads regardless of provider. Cross-queue precedence is the
PROVIDER's choice — no automatic rule that a funded pre-pay offer beats an older unfunded FCFS
entry. When the provider confirms one booking for a freed slot, every other pre-pay offer for
that slot is atomically cancelled and refunded to wallet credit.

[owner 2026-06-27] Pre-pay is OPTIONAL for the client (for now) — clients may always just book
or queue without pre-paying. Confirming a pre-pay offer is NOT a final file approval and locks
in NEITHER party: the provider may still REJECT the uploaded file as many times as they need
(each reject reopens the re-upload loop), and the client may CANCEL at any time before display.
On any cancel or reject, escrowed funds return to the client's wallet.

== 7. Proof of Display, Deadlines & Strikes ==

= Primary & secondary proofs =
The PROVIDER owns the PRIMARY proof of display — only the primary proof makes a booking
eligible for payout. Within the proof deadline the provider uploads a photo (printed/screen
ads) or a short video (radio/audio ads). Collaborators (per role) may upload SECONDARY proofs
(verifications) and flag mismatches, and can see the proofs relevant to them within the deadline.

= Deadline & reminders =
Proof deadline is admin-configured (default 5 days), counted from `display_start` = the
booking's calendar start_date set at confirmation (the stored timestamp the cron keys off).
Reminders fire on day 3 and day 4 (day-4 includes SMS). Missing the day-5 deadline auto-cancels
the booking, refunds the client to wallet in full, and accrues a strike on the provider. The
auto-cancel cron locks the booking and re-checks `proof IS NULL` inside the transaction.

= Review, payout & the reject→re-upload loop =
The client's payment is HELD from booking until the CLIENT accepts the primary proof. The
**CLIENT** — and their collaborators, per role — review proof content. **Payments does NOT
review proof content**; Payments only sees the resulting money state and executes the release.
- If the client ACCEPTS, the payment becomes releasable and Payments MANUALLY releases the
  payout (optionally auto-released under an admin amount threshold).
- If the client REJECTS (or a collaborator flags a mismatch), the re-upload window opens
  (admin-configured, default 48 h) for the provider to upload a new proof, AND a support ticket
  opens with the **payment attached, routed to BOTH Support and Payments** (one shared ticket).
  Payout stays HELD; the re-upload clock STOPS while the dispute/chat is open and resumes on close.

[owner 2026-06-27] If a proof is still unresolved after the re-upload window elapses, Support
steps in — but Support's role here is ONLY to watch for fraud or bad behavior, and it leans
toward the CLIENT: Support does NOT force a payout to the provider over a client's rejection.
The default resolution is to CANCEL the booking, refund the client in full, and let the client
find another space. Payments only executes the money movement Support's call implies.

Proof photos are NOT auto geotag-checked against the space lat/long — the space belongs to the
provider, who knows where it is; a proof that doesn't match the booked ad is caught by the
client/collaborator mismatch-flag → Support/Payments path instead.

= Strikes =
Strikes accrue on: missed proof deadline, provider cancel of a confirmed booking, or an upheld
mismatch. Accrual is decoupled from notification delivery (an SMS failure must not change
whether a strike accrues). Strike accrual is NOT a Payments power — it routes to Support first,
then Admin. Three strikes within a trailing 90-day window flag the account for Admin review
(may lead to a freeze). The trailing-90d count shows on the provider revenue dashboard. A
provider may appeal via a support ticket; SUPPORT can remove a strike from the 90-day counter
(reversible, audited) — a Support power, not a Payments one.

== 8. Money ==

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

**Escrow.** Pre-pay funds are captured to escrow (`wallet_entries.type=escrow_capture`) and
released on confirm/cancel (`escrow_release`). Escrow capture/release + the booking state change
happen in ONE DB transaction with a row lock on the booking. Payout is a state machine `held →
released → settled` with a single allowed transition, re-checking holds inside the locked txn.
On any cancel or reject, escrowed funds return to the client's wallet.

**Wallet ledger.** `wallet_entries` is append-only, double-entry: `user_id`, signed `amount`
(`decimal(12,2)` MXN), `type {refund|withdrawal|escrow_capture|escrow_release|adjustment}`,
`ref_type`, `ref_id`, `idempotency_key` (UNIQUE), `created_at`. Balance = `SUM(amount)`, never a
writable column. Wallet states `{active|frozen}`; withdraw is blocked while any dispute/hold is
open (`POST /client/wallet/withdraw`). Every refund/payout carries an idempotency key so a
retried gateway call or re-fired cron cannot double-pay; enforce `SUM(refunds) ≤ captured`.

**Refunds.** Payments executes ALL refunds (mocked); Support only FLAGS. [owner 2026-06-27]
Cancellation refunds are **100% by default** — the client gets ALL money back — UNLESS the
booking was already IN PROCESS (installation work started / teams dispatched), in which case the
refund is reduced to cover work already performed. This supersedes the earlier 90/5/5 split, and
the in-process deduction must be stated in the Terms & Conditions accepted at checkout. Other
cases: free pre-approval cancel; nothing for post-display cancel unless a proof fails; full
refund on auto-cancel (proof miss) or provider cancel. Clawback (a refunded client later
suspended for fraud): the platform FREEZES the wallet and alerts Payments; Payments cannot pull
funds — the frozen balance waits for an Admin decision (claw back via negative ledger entry /
keep frozen / release).

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

== 9. Ratings ==

Each space detail shows a provider star rating: **1–5 stars + total count**, the average of
per-campaign ratings left by past clients, plus an optional short comment. `GET /spaces/{s}`
includes the provider's average + count. **Eligibility:** only clients can rate, and only after
one of their campaigns with that provider has **ended OR has been live ≥30 days**; **one rating
per client per campaign** (`POST /client/campaigns/{c}/rating`).

[owner 2026-06-27] Ratings are **IMMUTABLE and fully transparent**: clients CANNOT edit or
delete a rating once left — stars and comment are permanent and 100% real, for statistical
integrity. Abusive comments may be flagged for Support, but removal is a rare MANUAL admin
action (extreme cases only), never automatic hiding; the rating otherwise stays.

== 10. Chat & PII ==

**Three chat surfaces:** (1) contextual client↔provider chat on every ad/adset/campaign; (2) a
global Help-menu chat for every user on an account; (3) the support-ticket chat (auto-attaches
object context: provider, dates, payment status, proof state).

**PII masking** is one server-side service, evaluated **per-message at render** against the
viewer's role and the thread's join state — never store only-masked content if Admin/audit needs
the raw. Masked in client↔provider chat: phone, email, full URL, off-space street address.
Masking RELAXES when Support or Payments joins (announced by a system message) and NEVER applies
on internal staff threads. When Support joins it sees the FULL prior history unmasked (near-admin
level). **Admin is the exception to the announcement rule:** Admin reads and posts into any
thread SILENTLY/incognito — no system message announces entry.

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

== 11. Tickets & Support ==

**Ticket from any object.** A "Talk to support" button on any campaign/adset/ad/space opens a
thread that auto-attaches object context (provider, dates, payment status, proof state).
`tickets` is polymorphic: reporter `user_id`, `ticketable_type`/`ticketable_id`, `subject`,
`description`, status `open|in_progress|waiting_user|resolved|closed`, priority
`low|medium|high|urgent`, `assigned_to_user_id`. Ticketable covers Ad|Adset|Campaign|Space —
**plus `payment`** (the dual-ticket path below). `ticket_messages` carry `is_internal` staff-only
notes. Reference-less tickets are also allowed.

**Shared CRUD:** `GET,POST /tickets · GET /tickets/{t} · POST /tickets/{t}/reply` for any
authenticated user; Support has its own queue (`/support/tickets`, `…/reply`).

**Support powers.** Support can **edit any NON-money object** (`PUT /support/{spaces|ads|users|
bookings|collaborators}/{id}` — every write audited) but has NO money authority: it can only
**FLAG** a refund or payout-hold (`POST /support/payments/{p}/flag-refund`,
`…/flag-payout-hold`), bridging to Payments, which DECIDES and EXECUTES. Support joins
client↔provider chats via `POST /support/conversations/{c}/join` (announced; Admin stays
silent). Strike accrual/removal is a Support (not Payments) power. Support can CLOSE a ticket
without the user's consent (the operational queue is Support's), but closing only resolves the
ticket STATE — the conversation stays LIVE and re-openable; closing is not deletion and not a
gag. Support-initiated ad-hoc tickets are visible only to the provider involved.

**Dual Support↔Payments ticket on proof reject.** When the CLIENT rejects the primary proof (or
a collaborator flags a mismatch), the system auto-opens ONE ticket with the **payment attached,
routed to BOTH Support and Payments** (`ticketable = payment`); both see it. Payout stays HELD;
the 48 h re-upload window opens, its clock stopping while the dispute is open. [owner 2026-06-27]
Support leans toward the CLIENT and does NOT force a payout over a client's rejection; default
resolution = CANCEL + full refund + client finds another space; Payments only executes.

== 12. Admin: Moderation, Freeze, Audit, Configurations ==

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

**Immutable audit log.** Append-only; a log entry for EVERY action of ANY role, with **actor,
target object, before/after, timestamp**. Queryable as per-object history (campaign, adset, ad,
client, ticket, flag, space, provider account, booking, payout). Admin has read-only access.
- Immutability enforced at the DB level: INSERT-only grant + a trigger that raises on
  UPDATE/DELETE (optionally a per-row hash-chain). Convention alone is not immutability.
- The audit row is written in the SAME transaction as the state change (atomic); async events
  use an outbox.
- `insert_data.py --erase-all-data` is env-guarded (refuses unless `APP_ENV` local/testing) and
  never truncates the audit log in a real environment.
- API: internal `AuditLog::record()` + `GET /admin/audit` (`?target_type`, `?target_id`).

**System Configurations (Admin-only; defaults in parentheses):** proof deadline (5 d) · proof
re-upload window (48 h) · strike window (90 d) + 3-strike threshold · pre-pay offer expiry ·
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

**Admin operational/eagle-eye dashboard.** Indicators — ads rejected by providers, proof
re-uploads, support tickets, payments stopped — plus queue depths, refund rate, strike rate,
gateway health. Each indicator links to a list; each list item opens its full detail in
eagle-eye mode (conversations opened SILENTLY).

**Rate limits.** Per-IP and per-account caps on login, chat send, file upload, search, booking
submission, with a burst allowance for known-good accounts; throttled responses include
retry-after. Thresholds are admin-configurable.

== 13. Notifications & Calendar ==

**Dispatcher.** Emit domain events (`BookingApproved`, `ProofRejected`, `StrikeAccrued`, …) and
let queued listeners fan out over channels via a channel/urgency lookup — do NOT scatter
`Notification::send` through controllers. Strike accrual is decoupled from notification delivery.
API: internal dispatch on each event + `GET /notifications`, `POST /notifications/{n}/read`. The
full ~35-event alert schema table (Event | Trigger | Recipients | Channel | Urgency | Template)
is in §19 (User Stories).

**Channels.** in-app / email / SMS-mock. Most notifications are SIMPLE TEXT at the top of the UI
(in-app); some events surface instead as a ticket/chat with Support or Payments.
- **SMS is reserved for Payments alerts and cancellations only** — Twilio default (best MX
  deliverability), Vonage fallback, MSG91 cheaper option later; mocked until keys set.
- Strike-related alerts go to Provider + Support (and Admin on the 3rd) — **never Payments.**

**Calendar (iCal sync + staleness).** The provider availability UI strongly recommends
connecting a live calendar URL (.ical or public Google/Outlook) because it's easier to keep
current; a "?" help button explains how to find the URL. API: `GET,POST /provider/spaces/{s}/
availabilities`, `DELETE /…/{av}`, `POST /…/sync-ical`, `POST /…/import-ical`.
- **Staleness clock** is measured from the LAST SUCCESSFUL SYNC (URL/iCal) or LAST MANUAL EDIT
  (hand-managed) — never from space creation. If older than the threshold (default 7 d), an
  alert appears next to that space in the provider's list. A healthy auto-syncing URL never fires.
- If an imported calendar goes OFFLINE, the space FALLS BACK to its last-known/manual range
  rather than vanishing, and the staleness alert prompts a fix.

== 14. RBAC & Capabilities ==

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

**Per-account collaborator overlay.** A collaborator belongs to **account_id** (NEVER a campaign
or space) and, per role, sees/acts across ALL of that account's campaigns/spaces (e.g.
`account_grants`: account_id, collaborator_user_id, role, capability). Two SEPARATE ecosystems
(see §3 for the subroles + exact capabilities). Per-object scoping may be layered later — not
MVP. API: `GET,POST /client/collaborators` + `DELETE /…/{col}` (publicist, manager); `GET,POST
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

== 15. Data Schema ==

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

== 16. Data Model & Integrity Invariants ==

Load-bearing implementation rules (source: 2026-06-05 architecture review; decision history in
history.md):
- **Money/wallet:** append-only double-entry ledger; balance = `SUM(amount)`, never a writable
  column; cached balance reconciled from the ledger in the same txn. Every refund/payout has a
  UNIQUE idempotency_key so a retried call/cron can't double-pay; `SUM(refunds) ≤ captured`.
- **Escrow/payout:** escrow capture/release + booking state change in ONE txn with a row lock on
  the booking. Payout state machine `held → released → settled`, single allowed transition,
  re-checking holds inside the locked txn. Gateway webhooks signature-verified, raw-persisted,
  idempotent by event id + nightly reconciliation.
- **Concurrency:** `EXCLUDE USING gist (space_id WITH =, daterange(start,end) WITH &&)` blocks
  overlapping CONFIRMED bookings. Slot allocation, pre-pay confirm, payout release, offer expiry
  all `SELECT … FOR UPDATE`. Confirming one pre-pay offer atomically lost-cancels + wallet-refunds
  all sibling offers for that slot. Day-5 auto-cancel cron locks the booking and re-checks
  `proof IS NULL` in-txn.
- **Time:** `display_start` is the concrete stored timestamp the deadline/reminder/auto-cancel
  cron keys off. Store UTC; compute against the admin-default tz (Monterrey); display per-user.
- **Snapshots:** media-delivery specs + relevant config snapshot onto booking/ad at
  creation/checkout; "apply to in-flight" checkbox opts into re-applying a changed config.
- **RBAC:** global matrix for system roles; per-account `account_grants` overlay for
  collaborators, evaluated after and scoped so grants never leak across accounts. Provider freeze
  busts permission/session cache immediately + revokes Sanctum tokens.
- **Conversations/PII:** polymorphic anchor + `conversation_links` travel history; internal
  staff threads are SEPARATE rows with membership ACL (test: a client can never fetch an
  internal-thread message); PII masking evaluated per-message at render, never store only-masked
  if audit needs raw.
- **Audit:** DB-level immutability (INSERT-only grant + trigger raising on UPDATE/DELETE,
  optional hash-chain); audit row written in the same txn as the state change; erase-all-data
  env-guarded.
- **Strikes:** decoupled from notification delivery; reversible by Support/Admin (audited).

== 17. Backend Endpoint Map ==

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
  POST /proofs/{proof}/flag-mismatch — reject proof + ticket ⚠ no client ACCEPT counterpart
  GET /wallet — balance + entries ⚠ no withdraw

Provider (role:provider, prefix /provider):
  POST,GET /spaces · GET,PUT,DELETE /spaces/{s} — space CRUD ⚠ no spec columns; DELETE no guard
  POST /spaces/{s}/media · DELETE /…/media/{m} — space media ⚠ live route named /photos; rename to /media
  GET,POST /spaces/{s}/availabilities · DELETE /…/{av} · POST /…/sync-ical · POST /…/import-ical
  GET /bookings · PUT /bookings/{b} — booking queue: Approve / Reject (reason OPTIONAL) ⚠ live code bare confirm/cancel
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
Client proof gate (B9): POST /client/proofs/{proof}/accept (→ payout releasable) · POST /…/reject (→ re-upload + held + dual ticket)
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

== 18. System & Dev ==

Backend: Laravel 12 (API only), `backend/`. Frontend: Angular 18 SPA (SSR), `frontend/`. DB:
PostgreSQL 17 (Docker host :5435 → container :5432; bare-metal PG17 on :5434, trust auth). Auth:
Sanctum Bearer token in localStorage. CORS: allow `http://localhost:4200` with credentials.
Maps: Leaflet/OSM + what3words (swap to Google via `environment.mapProvider`). Payments mocked
(Mercado Pago / PayPal). SMS: Twilio default / Vonage fallback, mocked. Geo search: bounding-box
(PostGIS = upgrade path). Files: `storage/app/public/` via `storage:link`.

= Folder structure (condensed) =
```
pub-ads-mar/
├── backend/                              # Laravel 12 API
│   ├── app/Http/Controllers/             # AuthController; Client/, Provider/, Admin/, Support/,
│   │                                     #   Payments/ (ProofReviewController DEPRECATED by B9),
│   │                                     #   Manager/ (legacy), Shared/
│   ├── app/Http/Middleware/              # RoleMiddleware, PermissionMiddleware
│   ├── app/Services/IcalSyncService.php
│   ├── app/Models/                       # User + 18 domain models
│   ├── bootstrap/app.php · config/ (cors, sanctum)
│   ├── database/migrations/ + seeders/   # DatabaseSeeder, RolePermissionSeeder (~66 rows)
│   ├── routes/api.php                    # all routes (+ permission mw per route)
│   ├── tests/                            # test_all_endpoints.py, insert_data.py
│   └── storage/app/public/
├── frontend/src/app/                     # core (guards/interceptors/models/services),
│   │                                     #   features (admin/auth/client/dashboard/payments/
│   │                                     #   provider/shared/support), shared (Navbar, Sidebar,
│   │                                     #   NotificationToast, LocationPicker=Leaflet+w3w, layouts)
├── design.md (this file) · CLAUDE.md · README.md · history.md · install_beads.md
└── .agents/skills/ · .beads/ · .claude/ (skills, plans, todos)
```

= Dev commands =
Docker is canonical (project **publisher**; backend auto-migrates on boot). This machine's
BuildKit streaming is broken — use the classic builder:
`export WSLENV=DOCKER_BUILDKIT:$WSLENV; DOCKER_BUILDKIT=0 docker.exe compose up -d --build`
(use the Windows binary `/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe` from WSL).
```bash
docker compose up -d --build        # backend + nginx(:8000) + frontend(:4200) + postgres(:5435)
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec db psql -U postgres -d pub_ads_mar
# Bare-metal fallback:
cd backend && php artisan serve            # :8000
php artisan migrate:fresh --seed; php artisan storage:link; php artisan route:list
cd frontend && npm install && ng serve     # :4200 ;  ng build
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5434 -d pub_ads_mar
```
Demo accounts (after seed; password `password`): admin@ / support@ / payments@ / provider1@ /
provider2@ / client1@ / client2@ pubads.test. (Older seed used Paris/EUR — migrating to
Monterrey/MXN; frontend booking list still hardcodes `currency:'EUR'`, should be MXN.)

= Implementation status =
Backend is fully implemented as an MVP subset; Angular SPA scaffolded across 8 feature modules.
DONE: Laravel+Angular scaffold, PG+`.env`, Sanctum+CORS, domain migrations, 19 models, Role +
Permission middleware, RBAC matrix + Admin Permissions API, all role controllers/routes (each
permission-gated), storage link, iCal sync, demo seeders + fake-data + endpoint tests, Angular
features + LocationPicker + calendar UI. NOT BUILT (designed, see §17 PLANNED + §20): accounts
table, account-scoped collaborators, audit log, strikes, notifications dispatcher, escrow/
pre-pay, media-spec validator, admin moderation, ratings.

== 19. User Stories (Cases) ==

> The verbose, story-level source of truth for what each role does. The canonical DESIGN
> (modules, enums, policies) is in §1–18 — when a story and a spec ever disagree, the spec wins.
> All `[owner YYYY-MM-DD]` decisions are folded in. This is intentionally the longest section.

= CLIENT scenarios =
1. **Open the portal and see nearby spaces.** Land on a map centered on geolocation (Monterrey
   default). The right rail lists nearby spaces sorted near-to-far, each tagged with type.
   Changing the map center re-sorts and re-queries radio stations in range.
2. **Filter the catalog.** Filter by space type, date range, budget per day. Spaces whose
   calendar doesn't match aren't hidden; they show a "doesn't coincide with your timespan" tag
   and stay selectable for "book it for later." Dates are EXACT — no ±X-day filter.
3. **Read details before committing.** Open any space: photos, exact map pin, price per
   day/month/custom span, provider calendar, declared physical specs, declared media-delivery
   specs, recent proof history, and a provider star rating (1–5 + count). Rating = average of
   per-campaign ratings; only clients rate, only after a campaign ended OR ran ≥30 days; one per
   client per campaign; optional comment; abusive comments flagged for Support.
4. **Add to a campaign.** Multi-select spaces → "Add to a new campaign" or "Add to an existing
   campaign." A 10-second toast offers "Go to my new campaign." Selected spaces sit in the
   backlog ("add those to an adset to start").
5. **Organize the backlog into adsets.** "Send all selected to a new adset" → "Adset 1." Adsets
   are pure grouping labels; each ad keeps its own price, dates, provider. The campaign view
   shows an Orphan-spaces list (added but not yet placed in an adset) with a one-click "Move all
   to new adset"; orphans can't reach checkout while orphan.
6. **Attach media per ad.** Upload media per ad — image for billboard, MP3 for radio, video for
   big screens. The system warns on type mismatch before upload completes. Next to the upload
   control the client sees the provider's upload instructions inline (a human-readable summary
   of the space's media-delivery specs + any free-text rule), kept visible during/after upload.
7. **Upload an ad that conforms to space media specs.** On upload, the system validates the file
   against the provider-declared specs (format, resolution, color profile, bleed, duration,
   bitrate, size). On failure, upload is rejected with a clear message (e.g. "your file must be
   PDF/X-1a, CMYK, 150 DPI at trim size, with 1 cm bleed").
8. **Set the time window and pay.** Set a per-ad time window. Conflicting calendars surface "Book
   it for later." One checkout per campaign covers all ads regardless of provider. For not-yet-
   free windows the client MAY mark the booking **Pre-pay** — funds captured, booking enters the
   provider's pre-pay waiting list; the provider picks which offer to confirm when the slot opens
   (highest total, longest window, repeat client). Pre-pay funds stay in escrow until confirm; if
   the slot never opens within the expiry window, the client is refunded to wallet. Non-pre-pay
   entries stay queued without funds, resolved FCFS when the calendar frees. (Pre-pay is OPTIONAL.)
9. **Wait for approvals.** Each ad shows one of three states: waiting for provider approval,
   waiting for booking confirmation, or live.
10. **Watch for proofs.** Providers have 5 days from display start to upload proof (admin-
    configurable). The client is notified when a proof lands. Missing the deadline auto-cancels
    the ad and refunds the client to wallet.
11. **Invite collaborators.** Invite teammates by email from an account-scoped Collaborators menu
    (under Configurations) — a collaborator belongs to the whole ACCOUNT, not a single campaign
    ([owner 2026-06-20]). Per their role they review proofs, chat with providers, open tickets,
    and act across ALL the account's campaigns, but cannot see billing or initiate payment.
12. **Chat with the provider.** A chat button on every ad/adset/campaign and a global Help menu.
    PII (phones, emails, URLs) is masked in transit.
13. **Open a support ticket.** "Talk to support" on any campaign/adset/ad opens a thread that
    auto-attaches object context (provider, dates, payment status, proof state).
14. **Cancel or pause.** [owner 2026-06-27] Refunds are 100% by default unless the booking was
    already in-process (work started), reduced per the Terms & Conditions. The refund policy,
    terms & conditions, and privacy policy are linked in the footer and on the checkout screen
    before paying.
15. **Flag a mismatch.** A client or collaborator can flag a proof as not matching what they paid
    for. The flag immediately holds the provider payout and opens a support ticket with the ad and
    proof attached.

= PROVIDER scenarios =
1. **List a space with two spec sets.** Create a space with type, name, text address, lat/long,
   photos, pricing per day/month/custom span. Declare (a) PHYSICAL specs (size cm, resolution,
   wattage, station frequency, loop length, daily impressions) and (b) MEDIA-DELIVERY specs the
   client must conform to. Media specs auto-populate from a per-type template (the provider can
   tighten them) plus free-text rules. The provider may reject any ad; on rejection the client
   isn't charged.
2. **Set availability.** Edit the calendar by hand, import .ical, or paste a public
   Google/Outlook URL; a "?" explains how to find the URL. The UI strongly recommends a live URL.
   If a calendar was last set up >7 days ago, an alert appears next to that space.
3. **Manage the listing.** Pause, unpublish, or delete. Delete is blocked with active/future
   confirmed bookings or any open support tickets on the space.
4. **Review booking requests.** Queue shows client, dates, total, attached media. Actions are
   **Approve** or **Reject** only (reason optional, helps the client re-submit). No counter-offers.
5. **See confirmed bookings.** Confirmed bookings move to an installation queue with install date
   and the client's file; the provider can add Installator collaborators to view this list.
6. **Install and upload proof.** Within 5 days of display start, upload a photo (printed/screen)
   or short video (radio/audio). Reminders day 3 and day 4. Missing day 5 auto-cancels, refunds
   the client, accrues a strike.
7. **Re-upload after a proof rejection.** If the CLIENT rejects (or a collaborator flags), the
   provider has 48 h to re-upload, and a ticket with the payment attached opens for BOTH Support
   and Payments while the payout stays held ([owner 2026-06-20] — Payments doesn't review content).
8. **Chat with clients.** From any booking/ad; PII masked.
9. **Revenue dashboard.** Upcoming, held, paid, refunded amounts + trailing-90-day strike counter.
10. **Dispute a rejection/flag.** Open a support thread tied to the ad; Support can join and flag
    a payout-hold or refund (Payments executes).

= SUPPORT scenarios =
1. **Ticket queue with full object context** (campaign/adset/ad ref, provider, payment status,
   proof state). 2. **Join existing client↔provider chats** (announced; full history readable).
   3. **Resolve disputes** — valid → request refund + re-open cancel path; invalid → close with
   reason. 4. **Ad-hoc tickets on behalf of a user** — visible only to the provider involved.
   5. **No money powers** — can only FLAG a refund/payout-hold for Payments to execute.

= PAYMENTS scenarios =
1. **Release payouts (money only — NOT proof content; [owner 2026-06-20]).** When the CLIENT
   accepts a proof the payment becomes releasable and Payments manually releases (auto only under
   the admin threshold). 2. **Held payments queue** — on client reject / mismatch flag, the
   payment auto-HOLDS and a ticket with the payment attached lands for both Payments and Support;
   release only on agreement. 3. **Process refunds** — automatic on deadline miss, manual on
   support-validated disputes; mocked MP/PayPal; refunds land in the client wallet with a withdraw
   option. Payments+Support can open a private thread the client never sees. 4. **Strike accrual
   is not a Payments power** — strikes route to Support then Admin; 3 in 90 days flags Admin
   review. 5. **No content powers.**

= ADMIN scenarios =
1. **CRUD users** — create providers via a dedicated form (company_name, address, phone
   required); freeze and delete with guardrails (no delete with active bookings/unsettled funds).
   2. **Moderate spaces** — take down/restore. 3. **Edit the RBAC matrix** — resource×action grid
   per role; admin's permission routes can't be revoked. 4. **Audit anything** — read-only
   immutable audit log. 5. **System health dashboard** — queue depths, refund rate, strike rate,
   gateway health.

= Cross-cutting (PII, Audit, Rate limits, Currency) =
- PII masking: phone/email/URL/off-space address masked in client↔provider chat; relaxes on
  Support/Payments join (announced); never on internal staff threads.
- Audit: append-only; every state-changing action logged with actor/target/before-after/ts;
  per-object history.
- Rate limits: per-IP + per-account caps on login/chat/upload/search/booking + burst + retry-after.
- All amounts MXN.

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
| Proof rejected | CLIENT rejects / collab flags | Provider, Client, Support+Payments (ticket, payout held) | in-app+email | high | "Proof on {ad} rejected: {reason}. Re-upload 48h; payment held pending review." |
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

= Revised journeys =
1. First-time client, single billboard: map → detail → new campaign → adset → upload validated
   PDF → checkout → wait approval → live → proof received → done.
2. First-time provider: sign up → list a space → declare specs → set calendar via URL → first
   booking → approve → install → upload proof day 2 → payout released.
3. Radio campaign, 3 stations: filter radio in range → multi-select 3 → new campaign → upload
   three −14 LUFS 30 s MP3s validated per station → adset "Morning Drive" → checkout → approvals
   → proofs → payouts.
4. Mixed-media campaign: billboard + big screen + radio in one campaign; adsets by neighborhood;
   each file validated; some slots "book for later"; single checkout.
5. Missed deadline: provider misses day 5 → auto-cancel → client wallet credited in full → strike
   → critical alert to client/provider/Payments/Support.
6. Mismatch flag from a collaborator: collaborator sees stale art → flags → payout held → Support
   joins → resolved valid → refund, strike.
7. Provider freeze: 3 strikes in 90 days → admin alerted → admin freezes → upcoming bookings
   auto-cancel with full refund.
8. Disputed proof → support: client rejects for blur → payment auto-held + ticket to
   Support&Payments → provider disputes/re-uploads → Support+Payments agree → payout released.
9. Conversation that travels: inquiry → booking thread on approval → support ticket on a mismatch
   flag, all one chronological thread with system messages marking each transition + the masking
   change when Support joins.

== 20. Roadmap, Revisions & Known Gaps ==

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

Roadmap (build endpoints first, then UI, per feature group):
- PHASE 1 (now): owner-decision fixes + wiring — map landing/render, campaign spec edit + per-ad
  Book, conversation attach/start-chat, provider approve→installer / reject, client proof
  accept/reject + Payments hold-view, account-wide collaborators + Configurations tabs, nav/header,
  wire existing-but-unused endpoints (adset/ad/booking/invoice edit, green/red availability),
  per-ad availability text.
- PHASE 2: Support powers (edit-any-non-money + audit log) and Admin eagle-eye over all objects
  (per-object views + filters, RBAC for employees only + support collaborator-role editor).
- PHASE 3 (deferred, pick per need): strikes, ratings, notifications dispatcher (+ Twilio mock),
  escrow / pre-pay waiting list, media-delivery spec validator, admin moderation
  (takedown/restore/freeze) + deletion guardrails, overlapping-booking constraint, invoice
  per-ad/per-adset line items.

Known code-vs-design gaps (from the compliance audit — the granular spec→todo backlog lives in
`.claude/todos/mvp-sprint.json`):
- LIVE code still diverges: Payments proof-review endpoints wired (B9 not enforced);
  collaborators `campaign_id` not `account_id`; bookings/proofs enums not yet migrated; provider
  booking is bare confirm/cancel; conversations not polymorphic; hard-deletes lack guardrails.
- Backend-ahead-of-UI (wired backend, UI not calling): space-search green/red availability flag
  ignored; adset/ad/booking/invoice show+PUT routes unused; `POST /conversations` never called
  (clients can't yet start a chat); config apply-to-in-flight only patches `proof_deadline_days`;
  proof-reject ticket doesn't attach payment nor route to both Support+Payments.
- Not built at the schema level (designed): accounts, account_grants, strikes, ratings,
  audit_logs, notifications; escrow/pre-pay; overlapping-booking EXCLUDE constraint; media-spec
  validator; admin moderation.
