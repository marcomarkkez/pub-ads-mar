This document is the main design reference for all the app (API and headless frontend)

Roles:
    Clients: People that want one or multiple spaces, and will pay per campaigns
    Providers: They own spaces so they can put price per day or month, or any time span
    Admins: The eagle-eye role — they can see EVERYTHING in the system: all messages between Providers and Clients, all Support threads, and the internal Support↔Payments private threads. They check problems reported by users, moderate listings (take down / restore), freeze providers, configure system settings (see System Configurations), read the immutable audit log, and manage users and the RBAC matrix.
    Support: Support agents role check tickets and problems reported by users in each adset, ad (space) or whole campaigns
    Payments: Payments personnel check and approve payments for campaigns with proofs of display (images or videos) and stop payments to providers that don't comply with the requisites or proofs. Support can only *flag* a refund or a payout-hold; Payments is the role that actually decides and executes the money movement at the end. Payments has no content powers (it cannot edit listings or media), and strike accrual is NOT a Payments concern (see Strike System). 

Campaign: Is a unit where clients can put a budget, ad one or more adsets with one or more ads, this is the top level of the whole system for the users, campaigns must be listed, with columns like value of the whole campaign, the invoice of that campaign and other relevant data.

Adset: Is the group of ads, in this middle level adsets can be groups of ads for one place in particular, let's say, multiple image ads, that are being displayed in displays in the streets or maybe in taxis, for example, so the adset is a group where multiple ads from multiple providers can be, is not other thing than a "tag", it has no other purpose than order and group ads.

Ad: Is the basic unit of the publicity and can be audios (for radio), can be images (for billboards), can be videos (for big scren billboards) and other types of advertisment, the provider is responsible for approving the files uploaded (manual approval, which coexists with an automated upload-time validation against the space's media-delivery specs — BOTH gates apply, and a file may pass automated validation yet still be manually rejected by the provider), in order to be displayed in different places or printed or maybe played in radio stations, every ad has a cost and can be from different providers, but the invoice is printed in campaign level, each ad has its own price and time span for the place, and even diferent provider. When a booked window straddles two of a provider's pricing tiers (e.g. a per-day and a per-month rate), the price is the CHEAPEST valid decomposition — the system fits the largest cheaper blocks first (e.g. 40 days = 1 month + 10 days if that beats 40 × daily) so a longer booking is never penalized.

The backend should be Laravel and it will be a service for a headless website, and possible an app in the near future. Create fake endpoint requests, to test every endpoint with fake data, and create a script to send the fake tests or erase everything, this script should be called by terminal like "python insert_data.py", with options like "messaging", or "support ticket", and an option like "erase-all-data" to clear the whole database.

Ticket system / Support: The chat with support can be called from any ad or adset or campaign, with a button and it will save in the chat the reference for that specific item, with provider information, budget, payments and everything an agent could need.

Providers UI: The providers can insert, update, pause/unpublish, and delete their spaces — but a space can only be DELETED if it has no open support tickets and no booking in progress; otherwise the provider unpublishes it and waits for the last clients to finish their booked time. Those spaces can be audio, video, gif's, images, in the basic format of that kind of media for publicity, because they own the publicity spaces, they have to insert the name, the location in text, and the location in lat-long format, the google maps api should be available to use that, and one or several photos of the space. They should set the price by day, month or any time span, for the availability they can setup any calendar like Outlook or Google Calendar, import a cal file or simply connect to a one public calendar, for this porpuse should be a little question mark button to help with this topic, they should take photos as proof within the proof deadline (a number of days configured by Admin in System Configurations, default 5) to prove that the ad is being displayed, this is per ad. Te providers can see a list of all the ads they need to add proofs with photos of the ad of the client (video or image) being displayed, in the case of audios for radio stations or any other possible media source the proof should be a video.

Clients UI: They want pubicity spaces to put their publicity campaigns, with images, audios, videos or any normal media for ads, and they will pay per day or month or any time span they use, they need to see the proof that the ad is being displayed in the place they choose (the ad), if the proof is not added, the ad can be cancelled and the money will not be send to the provider. They should be able to search spaces in a map (Google maps api) select it from the point in the map and check the information of the space in the right bar (50% wide) then fill the fields with information of the time span requested, it should give information of availability by turning green or red depending if the space is available in that date range, if it's available it should be sended to approval from the provider and once approved the provider has until the proof deadline (admin-configured, default 5 days) to upload the proof. Clients can add collaborators by email in a collaborators menu. The account owner (together with the team) assigns each collaborator a ROLE with a configurable set of permissions — e.g. review proofs, upload secondary proofs, chat with providers, open support tickets, act on campaigns — but collaborators NEVER get billing or payment powers. The provider owns the PRIMARY proof of display; collaborators may upload SECONDARY proofs and flag mismatches, and can see the list of proofs relevant to them within the configured proof deadline (see Collaborator Roles and Proof of Display).

Payment system: The payments should be through platforms like Mercado Pago and Paypal, prepare the API's, but fake it until the keys are setted up.

Clientside / Frontend for web: The front end for clients, should be a map where the location of the person (if we can acces to it from web) or the default location (currently will be Monterrey, Nuevo León, México) Above the map should be a search for location that change immediatly the map (this could be the Google Maps API and UI) in the right side should be the locations of spaces we currently have in our database from the nearest to the farest using the Google API to compare distances or maybe with a simple triangulation from geolocation data (lat, long), the list in the right could be multi select and shows information about the type of location (big screen, billboard, radio station, little screens), and the description of the place, when user select one or more, there should be in the below part of the list two buttons: "Add to an existing campaign" or "Add to a new campaign", that buttons will add the locations to a backlog in the campaigns waiting to place them in an adset, notice that adsets are only a grouping function and doesn't have effect directly over price or location, each ad / place have its own price and location, an adset is just for order purposes and it has a name, that's all, when user push the add to new campaign button a window appears (only 10 seconds) with a "Go to my new caompaign button" so the user may choose stay and select more places or go to the new campaign created with a generic name like "Campaign 1", with the filters in the top part of the sidebar for locations details should be a filter of dates, because all places have different time rangs of time span wehere are free, if a location doesn't coincide with the time selected it will have a legend "this place doesn't coincide with the time span" but can be choosed to be booked.

Clientside App: This is pending.

Tests: Create fake data script for test the API endpoints and create tests for the UI with a solution for QA like Selenium that should be called from terminal like "python selenium_front_tests.py" with the logic options. The fake data script (insert_data.py) is complete with --scale small|medium|large options. The endpoint test script (test_all_endpoints.py) covers 96 endpoints.

== RBAC Permissions System ==

The system now has granular role-based access control (RBAC) at the screen + action level. A `role_permissions` table stores which actions (create, read, update, delete) each role has on each resource/screen (users, campaigns, adsets, ads, spaces, space_photos, space_availabilities, bookings, payments, proofs, tickets, conversations, invoices, collaborators, dashboard).

Key features:
- PermissionMiddleware checks permissions on every route: middleware('permission:resource,action')
- Permissions are cached per-role for 60 minutes (auto-invalidated on admin edit)
- Admin can edit permissions via API: GET/PUT /admin/permissions/{role}, PATCH /admin/permissions/{role}/{resource}
- Login and /me responses include the user's permissions for frontend use
- Admin permission routes have NO permission middleware (only role:admin) to prevent lockout
- Default permissions seeded to match the original hardcoded role access

Admin API endpoints for permissions:
- GET /api/admin/permissions — list all permissions (grouped by role)
- GET /api/admin/permissions/{role} — get permission matrix for one role
- PUT /api/admin/permissions/{role} — bulk-replace all permissions for a role
- PATCH /api/admin/permissions/{role}/{resource} — update actions for one resource

== Provider Form (Admin-only) ==

The admin can create provider accounts via POST /api/admin/users with role: "provider". The frontend should have a dedicated form in the admin panel for this, with provider-specific fields (company_name, address, phone required).

== Roles Editor (Admin Panel) ==

The admin panel frontend should include a roles/permissions editor screen that:
- Shows a matrix of resources (rows) x actions (columns) for each role
- Allows toggling individual permissions with checkboxes
- Uses the admin permissions API endpoints above
- Cannot lock out the admin role from permission management

== Use Cases ==

= Case 1 =
A user wants several places / locations in an avenue, so he is nearby and with his computer (app is pending) he will open the portal (this app) and will immideatly prompted ti the same location where he is, so he chooses from the map the locations he like and read details in the righ sidebar where the details of each location are, he search for radio stations for audio ads too and the system shows the stations that are in range (notice that if user change the location places and radio stations should change too), he select billboards, little screens, big screens and radio stations and create a new campaign with all these items, the campaign is created and a window appears (only last 10 seconds) with a button "go to my new caimpaign", he clicks that button and go to the campaign, then he see the bcklog of the campaign with the places selected, there is a legend that states "you should add those to an adset to start" and a button "send all selected to a new adset", the client selects that button so a new generic adset "Adset 1" is created, each location have a legend "you have not selected a time span for the campaign" or a legend "your timespan doesn't coincide with the availability of this place or radio station" and a button "Book it for later" so the client can pay and wait to separate the time span is free, when the client pays all the places and radio stations, the ones that immediatly are available will show a "waiting for approval" legend and those that are booked will show "Waiting for booking confirmation", notice that the client should be aware of the availability times of each location based on the calendar of each location provided by the provider of each location.

== CANONICAL SUBSYSTEMS (folded in from cases-v2) ==

The sections below are now canonical design. They were promoted from the
use-case backlog at `.claude/plans/cases-v2.md`, which remains the story-level
source of truth (what users do). When the two ever disagree, design.md wins and
the difference is reconciled here. Open policy values still pending an owner
decision are tracked in `.claude/plans/design-md-reconciliation-questions.md`.

== Collaborator Roles ==

An account (client OR provider) is an organization that the owner can staff with
collaborators invited by email. Each collaborator is assigned one of three
organization roles:
- **Installator** — can ONLY upload proofs. Built for provider-side crews who
  install physical ads in the street and photograph them the same day. Sees
  nothing about money, campaigns, or other topics.
- **Publicist** — does the operational work: creates and edits campaigns,
  adsets, and ads (client side) / manages listings (provider side), and can
  raise support tickets. CANNOT see anything about money or payments.
- **Manager** — manages everything in the organization, INCLUDING payments and
  money (the only collaborator role that sees money).

The account owner ("admin" of that organization) and Publicists can raise
support tickets; Installators cannot (they only upload proof). Money/billing is
visible only to the owner and Managers — never to Publicists or Installators.
On the client side, the organization Manager can see any conversation a
collaborator has. These organization roles are enforced by a per-account RBAC
overlay on top of the global role matrix.

== Booking Lifecycle and Ad States ==

Each ad/booking moves through an explicit state machine:
draft → waiting for provider approval → waiting for booking confirmation
(for "book for later" / pre-pay) → live → proof-pending → completed | cancelled.
The provider acts on a booking request with **Approve** or **Reject (with
reason)** only — no counter-offers. The rejection reason is meant to help the
client re-submit a corrected file. The booking-request queue shows client,
dates, total, and the attached media file. Confirmed bookings move to an
installation queue (install date + the client's file); the provider staffs this
with Installator-role collaborators (see Collaborator Roles) who view the
installation list and upload proof for physical-ad install crews. "live" is a
first-class state.

Go-live is PER-AD, not all-or-nothing: each ad in a campaign is approved and
goes live independently, and bills on its own approval. If 9 of 12 ads are
approved, those 9 run as soon as their window opens while the other 3 stay
pending/rejected — a slow or rejected provider never blocks the rest of the
campaign. (Invoicing is already per-ad at campaign level.) An inquiry chat
becomes a confirmed booking thread only when BOTH the provider approval AND the
client payment are complete; either one alone leaves it an inquiry.

== Proof of Display ==

The PROVIDER owns the PRIMARY proof of display — only the primary proof makes a
booking eligible for payout. Within the proof deadline (admin-configured,
default 5 days, counted from display start) the provider uploads a photo
(printed/screen ads) or a short video (radio/audio ads). Reminders fire on day
3 and day 4 (day-4 includes SMS). Missing the deadline auto-cancels the
booking, refunds the client to wallet credit, and accrues a strike on the
provider. Collaborators (per their role) may upload SECONDARY proofs
(verifications) and flag mismatches; a mismatch flag holds the payout and
immediately opens a support ticket with the ad and proof attached.

Payout is NOT automatic on upload: once the primary proof is uploaded, **Payments
reviews it** — approval releases the payout, rejection (with reason) opens a
re-upload window (admin-configured, default 48 h) for the provider. A provider
may dispute a rejection or a mismatch flag by opening a support thread tied to
the ad. The re-upload clock STOPS as soon as a chat/dispute about the proof
starts; Support receives an alert to review and may enter the chat, and the
clock resumes once the dispute closes. Proof lifecycle: pending → uploaded → (approved → payout
released) | (rejected → re-upload) | (deadline missed → auto-cancel + refund +
strike). Secondary proofs and mismatch flags may be filed up until payout
release.

The reject→re-upload loop is not unbounded: if a proof is still unresolved after
the re-upload window (default 48 h) elapses, Support intervenes to adjudicate
rather than letting the cycle repeat indefinitely. Proof photos are NOT
automatically geotag-checked against the space lat/long — the space belongs to
the provider, who knows exactly where it is, so there is no "wrong space"
mismatch to detect automatically; a proof that does not match the booked ad is
caught by the client/collaborator mismatch-flag → Support path instead.

== Strike System ==

Strikes are accrued by the system on: missed proof deadline, provider cancel of
a confirmed booking, or an upheld mismatch. Strike accrual is NOT a Payments
power — it routes to Support first, then Admin, because a strike is not a
payment situation. Three strikes within a trailing 90-day window flag the
account for Admin review (which may lead to a freeze). Strike count over the
trailing 90 days is shown on the provider revenue dashboard.

A provider may appeal a strike by opening a support ticket; SUPPORT can remove
the strike from the 90-day counter (a reversible, audited action). Strike removal
is a Support power, not a Payments one.

== Client Wallet and Refunds ==

Refunds land in an in-app client wallet (MXN) with a withdraw option, rather
than silently "not being sent to the provider." Refund situations: free
pre-approval cancel; partial refund per policy for post-approval/pre-display
cancel; nothing for post-display cancel unless a proof fails; full refund on
auto-cancel (proof miss) or provider cancel. Payments executes all refunds
(Mercado Pago / PayPal, mocked until keys exist). Support can only flag a
refund/hold; Payments decides and executes.

Default cancellation refund split (all percentages admin-configurable): the
client is refunded 90%; the platform keeps 10%, of which 5% is paid to the
provider and 5% retained by the platform.

Payouts to providers happen as soon as possible but must be approved by Payments
first. Once a payout is approved and nothing is holding it, Admin has a
configurable window (default 24 h) to stop it; if Admin does not stop it within
the window, the funds are released to the provider's Mercado Pago / PayPal
account automatically.

Gateway fees are paid by the CLIENT (gross): the processing fee is added on top
at checkout, not absorbed by the platform or netted from the provider's payout.

Payouts are PER-PROVIDER, even within a single multi-provider campaign invoice:
each provider sets their own pay day, and the platform pays each provider on
their schedule rather than on one unified campaign date.

A payout held by an open dispute has no hard auto-resolve cap — it simply stays
held until the dispute is resolved, then pays in full if resolved in the
provider's favor (or is refunded per the outcome). Disputes resolve by human
adjudication, not by a timer.

Clawback (a refunded client later suspended for fraud): the platform FREEZES the
client's wallet and alerts Payments; Payments cannot pull money out — the frozen
balance waits for an Admin decision (claw back via a negative ledger entry, keep
frozen, or release).

== Book-for-later, Pre-pay Waiting List and Escrow ==

When a requested window is not yet free, the client sees "Book it for later".
Two queues exist per space: (1) PRE-PAY — funds are captured into escrow and the
booking joins the provider's per-space pre-pay waiting list; the provider picks
which booking to confirm when the slot opens, by their own criteria (longer
window, repeat client, etc.). The provider sees this waiting list as a SIMPLE
list with NO payment/amount information, and has a trash action to reject
bookings they don't want (e.g. another client is a better fit for the business).
Escrow funds are held until the provider confirms; if the slot never opens
within the offer's expiry window (admin-configurable) the client is refunded to
wallet credit, gets a notification, and the refund is recorded in the logs for
Payments/Admin. (2) NON-PRE-PAY — queued with no funds captured, resolved
first-come-first-served when the calendar frees. One checkout per campaign
covers all ads regardless of provider.

Cross-queue precedence is the PROVIDER's choice — there is no automatic rule
that a funded pre-pay offer beats an older unfunded FCFS entry. When the provider
confirms one booking for a freed slot, every other pre-pay offer for that slot is
cancelled and refunded to wallet credit.

== Provider Ratings ==

Each space detail shows a provider star rating (1–5 stars + total count): the
average of per-campaign ratings left by past clients, plus a simple text
comment. Only clients can rate, and only after one of their campaigns with that
provider has ended OR has been live ≥30 days. One rating per client per
campaign. Abusive comments are flagged for Support review.

== Media-Delivery Specs ==

A provider declares TWO spec sets per space: (a) PHYSICAL specs (size in cm,
screen resolution, wattage, station frequency, loop length, average daily
impressions) and (b) MEDIA-DELIVERY specs the client must satisfy when uploading
the ad file (format, resolution, color profile, bleed, duration, bitrate,
file-size cap). Media-delivery specs auto-populate from a per-type template and
the provider can tighten them, plus add FREE-TEXT rules (brightness, margins,
"this screen plays audio", etc.). An ad may carry MORE THAN ONE file (model the
ad → files relationship as one-to-many in the schema). At upload, each file is
validated against the media-delivery specs with hard-fail and a concrete error
message (automated metrics gate) — but passing validation does NOT guarantee
acceptance: the provider may still manually reject the ad for their own reasons.
On a manual rejection the provider either asks the client to re-upload a
corrected file, or rejects it permanently; either way, when rejected the client
is not charged. The full per-type spec table lives in cases-v2.md.

== Catalog, Search and Campaign Organization ==

Map-centered catalog with a nearest-first sidebar; changing the map center
re-sorts and re-queries radio stations in range. "In range" for a radio station
means inside the current MAP view range (typically a whole city) — not listener
location or broadcast footprint; pan/zoom the map and the station list updates.
Filters: space type, date range, and budget per day. There is NO date-flexibility
(±X days) filter — dates are exact; clients and providers add their own slack by
choosing wider windows or "book for later". Spaces whose calendar doesn't
coincide are tagged, not hidden, and remain bookable "for later". Adsets are pure
grouping labels (no price/date effect). The campaign view shows an Orphan-spaces
list (backlog spaces not yet placed in any adset); a client MAY check out a
partial campaign, but any ad still orphaned (not in an adset) cannot go live and
its payment cannot be processed — orphans can be bulk-moved into an adset to
become bookable. The upload control shows the provider's media-delivery summary +
free-text instructions inline, kept visible during/after upload.

== Chat Surfaces and PII Masking ==

Three chat surfaces: (1) contextual client↔provider chat on every ad/adset/
campaign; (2) a global Help-menu chat available to every user on an account;
(3) the support-ticket chat (auto-attaches object context: provider, dates,
payment status, proof state). PII (phone, email, full URL, off-space street
address) is masked in client↔provider chat; masking relaxes when Support or
Payments joins (announced by a system message) and NEVER applies on internal
Support/Payments/Admin threads. When Support joins a masked thread it sees the
FULL prior history unmasked (Support is effectively near-admin level), not only
messages posted after joining. Admin is the exception to the announcement rule:
Admin can read (and post into) any thread SILENTLY/incognito — no system message
announces that Admin entered. A conversation can travel (inquiry → booking →
support ticket) as one chronological thread with system-message transitions.

Street-address whitelisting: the booked space has an OWNER who registered its
exact street address as a stored field. In a thread tied to that space's booking,
that one stored address (and close variants) is whitelisted and shown normally —
the client legitimately needs it to install/photograph the ad — while every OTHER
address-like string in chat is still masked. So the space's own address passes;
off-platform addresses do not.

== Support, Payments and Internal Threads ==

Separation of powers: Support investigates and can only FLAG a refund or
payout-hold; Payments DECIDES and EXECUTES money movement. Support and Payments
can hold a PRIVATE internal thread the client never sees, even when it
references the same object as a client-facing conversation (the internal thread
is itself an object). Support-initiated ad-hoc tickets are visible only to the
provider involved. Admin can observe all internal threads AND post messages into
them (e.g. to Support and Payments about that thread) — eagle-eye with write.

Support can CLOSE a ticket on the support side without the user's consent (the
operational queue is Support's to manage), but closing only resolves the ticket
state — the conversation itself stays LIVE and visible historically to the user,
who can keep reading it and can re-open/continue the thread. Closing is not
deletion and not a gag.

== Notifications and Alerts ==

A notification/alert dispatcher delivers events. Most notifications are SIMPLE
TEXT shown at the top of the UI (in-app); some events are instead surfaced as a
ticket/chat with Support or Payments (those are explicitly chats, not toasts).
SMS is reserved for Payments alerts and cancellations only — not every critical
event. SMS provider: Twilio is the chosen default (best Mexico deliverability,
clean Laravel SDK); Vonage is the documented fallback. (MSG91 is a cheaper
MX-local option if cost matters later.) SMS is mocked until keys are configured. The full event schema (≈28+ rows: new booking, approve/reject, day-3/4
proof reminders, proof uploaded/approved/rejected, mismatch flagged, cancels,
auto-cancel, refunds issued/failed, disputes, payout held/released, strike
accrued, 3-strike admin alert, collaborator invited, listing takedown/restore,
provider frozen, etc.) is maintained in cases-v2.md and is canonical.
Strike-related alerts go to Provider + Support (and Admin on the 3rd) — never
Payments.

== Audit Log ==

Append-only and immutable. A log entry is saved for EVERY action of ANY role,
recorded with actor, target object, before/after, and timestamp. The log is
queryable as a per-object history for each campaign, adset, ad, client, support
ticket, flag, space, provider account, booking, and payout. Admin has read-only
access, exposed as part of the eagle-eye admin features. The number of logs
retained per role is admin-configurable.

== Rate Limits ==

Per-IP and per-account caps on login, chat send, file upload, search, and
booking submission, with a burst allowance for known-good accounts. Throttled
responses include retry-after. Concrete thresholds pending (see reconciliation
questions).

== System Configurations ==

An Admin-only Configurations menu holds tunable values. Summary of what is
admin-configurable (defaults in parentheses):
- proof deadline (5 days)
- proof re-upload window (48 h)
- strike window (90 days) and 3-strike threshold
- pre-pay offer expiry
- calendar-staleness alert threshold (7 days)
- payout-stop window for Admin (24 h)
- cancellation refund split (client 90% / platform 5% / provider 5%)
- currency (MXN) and amount unit (centavos)
- rate-limit thresholds (per-action caps, burst, retry-after)
- audit-log retention: number of logs kept per role
When an Admin changes a configurable value, the Configurations screen offers a
CHECKBOX: "apply to existing/in-flight bookings too" vs "apply to new bookings
only". Unchecked (the default and recommended path) snapshots config onto each
booking at creation, so a live deal is not changed mid-flight; checked re-applies
the new value to currently-running bookings as well.

== Admin Moderation: Takedown, Restore, Freeze ==

There are three distinct levels of taking a space/provider "off":
- **Pause / unpublish** (provider's own action): the provider hides their own
  listing temporarily; their choice, reversible by them.
- **Takedown / restore** (admin moderation): Admin forcibly hides or restores a
  listing (e.g. policy violation); a state the provider cannot self-reverse.
- **Freeze** (admin, account-level): Admin freezes the whole provider account.
  A freeze pauses new bookings AND auto-cancels all upcoming bookings on that
  provider with a full refund to each client's wallet (per cases-v2 journey #7).

Deletion guardrails: User delete is guarded (no delete with active bookings or
unsettled funds). A space cannot be deleted while it has open tickets or a
booking in progress. Instead, deleting is a "programmed for deletion" state: the
space is unpublished, stops receiving new bookings and payments, and on the
provider side stays visible with a legend "this is programmed for deletion"
until the last current bookings finish, after which it is removed.

Admin operational/eagle-eye dashboard: shows key indicators — ads rejected by
providers, proof re-uploads, support tickets, payments stopped — plus queue
depths, refund rate, strike rate, and gateway health. Each indicator links to a
list, and each list item opens its full detail in eagle-eye mode: for tickets/
chats Admin opens the conversation SILENTLY/incognito (no announcement of entry);
for everything else the link opens the complete object information in eagle-eye
read (and, for threads, write) mode.

== Legal Documents ==

Refund policy, terms & conditions, and privacy policy are linked in the site
footer and surfaced as a link on the checkout screen before the client pays.

== Calendar Availability Alerts ==

The provider availability UI strongly recommends connecting a live online
calendar URL (.ical or public Google/Outlook) because it is easier to keep
current. If a space's calendar setup is older than the staleness threshold
(default 7 days, configurable), an alert is raised next to that space in the
provider's spaces list, prompting a refresh.

The staleness clock is measured from the LAST SUCCESSFUL SYNC for URL/iCal-
imported calendars, or from the LAST MANUAL EDIT for hand-managed availability —
never from space creation. A healthy auto-syncing URL therefore never fires the
alert (its last-sync timestamp keeps refreshing); the alert only fires when a
manual calendar sits untouched past the threshold, or an imported URL has failed
to sync for that long. If an imported calendar goes OFFLINE (URL unreachable),
the space FALLS BACK to its last-known/manual availability range rather than
having its availability vanish — and the staleness alert prompts the provider to
fix or re-enter it.

== ARCHITECTURE NOTES (data model & integrity) ==

These are load-bearing implementation rules for the subsystems above. They are
canonical: the current 11-table schema (see ARCHITECTURE.md) predates them and
must be reconciled before building these features. Source: 2026-06-05 architecture
review. All prior OWNER-DECISION items have been resolved by the owner (Round 4,
2026-06-12) and applied above; see `.claude/plans/design-md-reconciliation-questions.md`
for the decision history.

Launch scope
- First launch target is MONTERREY, Nuevo León, Mexico — Mexico-first, single
  city to start. Single currency MXN (centavos), all deadline math in
  America/Monterrey timezone. Multi-country, multi-currency, and non-Spanish UI
  are out of scope for the initial launch (the currency unit is admin-
  configurable to leave the door open later).

Money & wallet
- The wallet is an append-only, double-entry ledger (`wallet_entries`: signed
  amount, type {refund|withdrawal|escrow_capture|escrow_release|adjustment},
  ref_type, ref_id, idempotency_key, created_at). Balance is `SUM(amount)`,
  never a writable column. A cached balance, if any, is reconciled from the
  ledger inside the same transaction.
- All money is stored in integer MXN centavos. Single currency MXN. Gateway
  callbacks that settle in another currency (e.g. PayPal USD) are normalized at
  capture: store gateway currency + amount + FX rate + the MXN-converted amount;
  never treat the raw gateway number as MXN.
- Every refund/payout carries an idempotency key with a UNIQUE constraint so a
  retried gateway call or a re-fired cron cannot double-pay. Refund base = the
  per-ad captured amount; enforce `SUM(refunds) ≤ captured`.
- Escrow capture/release and the booking state change happen in ONE DB
  transaction with a row lock on the booking. Payout is a state machine
  (held → released → settled) with a single allowed transition, re-checking
  open holds inside the locked transaction.
- Gateway webhooks: signature-verified, persisted raw, idempotent by gateway
  event id, with a nightly reconciliation job (gateway ledger vs local ledger).
- Wallet has states {active|frozen}; withdraw is blocked while any dispute/hold
  is open. Clawback is a negative ledger entry. RESOLVED: when a client is
  suspended for fraud after a refund, the wallet is FROZEN and Payments is
  alerted; Payments cannot pull funds — the frozen balance waits for an Admin
  decision (claw back / keep frozen / release).

State machines & time
- ONE canonical booking/ad state enum: draft → waiting-approval → waiting-
  booking-confirmation → live → proof-pending → proof-uploaded → (completed |
  payout-held | rejected | cancelled). The frontend's three visible legends map
  onto this set.
- `display_start` is a concrete stored timestamp = the booking's calendar
  start_date, set at confirmation. The proof deadline, reminders, and auto-cancel
  cron key off this column only.
- All deadline math uses a single platform timezone (America/Monterrey), stored
  explicitly — never the requester's local timezone.
- Strike accrual is decoupled from notification delivery (an SMS failure must
  not change whether a strike accrues) and is reversible by Support/Admin
  (reversal is an audited action).
- Media-delivery specs are SNAPSHOTTED onto the booking/ad at checkout; later
  provider edits to the live space specs do not retroactively fail a paid file.
  Likewise, snapshot relevant config values (proof deadline, etc.) onto the
  booking at creation so admin config edits don't change a live deal.
  RESOLVED: snapshot-at-creation is the default; the Configurations screen has an
  "apply to in-flight bookings too" checkbox to opt into re-applying a changed
  value to currently-running bookings.

Concurrency
- A Postgres exclusion constraint prevents overlapping CONFIRMED bookings on the
  same space: `EXCLUDE USING gist (space_id WITH =, daterange(start,end) WITH &&)`.
- Slot allocation, pre-pay confirmation, payout release, and offer expiry all
  take `SELECT … FOR UPDATE` on the relevant row. Confirming one pre-pay offer
  atomically transitions all sibling offers for that freed slot to lost →
  refund-to-wallet in the same transaction.
- The day-5 auto-cancel cron locks the booking and re-checks `proof IS NULL`
  inside the transaction (avoids racing a last-minute upload).
- RESOLVED: cross-queue precedence is the PROVIDER's choice — no automatic rule
  that a funded pre-pay offer beats an older unfunded FCFS entry. Confirming one
  booking atomically cancels + wallet-refunds all other pre-pay offers for that
  slot.

RBAC & collaborators
- The global `role_permissions` matrix governs system roles only. Collaborators
  use a SEPARATE per-account, per-campaign grant overlay (e.g. `account_grants`:
  account_id, collaborator_user_id, scope_type, scope_id, capability), evaluated
  after the global matrix and scoped so grants never leak across accounts.
- Extend the action vocabulary beyond CRUD with named capabilities:
  `refund.flag` vs `refund.execute`, `payout.hold` vs `payout.release`,
  `strike.accrue` vs `strike.reverse`, `internal_thread.read` vs `.post`. Support
  gets flag-only; Payments gets execute-only; Admin gets read on internal
  threads.
- A provider freeze busts that user's permission/session cache immediately and
  revokes active Sanctum tokens (do not wait for the 60-min RBAC cache).

Conversations & PII
- Conversations are polymorphic: a thread anchor (type, subject_type, subject_id)
  + a `conversation_links` history of contexts it has traveled (inquiry →
  booking → ticket) + `messages.kind` for system-transition messages.
- Internal Support↔Payments threads are SEPARATE conversation rows
  (type=internal) with a membership-based ACL — never a visibility boolean on a
  shared client thread. Client message queries are scoped by participant
  membership. (Add a test asserting a client can never fetch an internal-thread
  message.)
- PII masking is one server-side service evaluated per-message at render against
  the current viewer's role and the thread's join state; never store only-masked
  content if Admin/audit needs the raw.

Audit log
- Immutability is enforced at the DB level: INSERT-only grant for the app role
  plus a trigger that raises on UPDATE/DELETE (optionally a hash-chain per row).
  Convention alone is not immutability.
- The audit row is written in the SAME transaction as the state change (atomic),
  with no FKs/triggers that can fail; external/async events use an outbox.
- `insert_data.py --erase-all-data` is environment-guarded (refuses unless
  APP_ENV is local/testing) and never silently truncates the audit log in a real
  environment.

Notifications
- Emit domain events (BookingApproved, ProofRejected, StrikeAccrued, …) and let
  queued listeners fan out over channels via a channel/urgency lookup; do not
  scatter Notification::send through controllers. SMS uses Twilio (default) with
  Vonage as fallback, mocked until keys exist; SMS fires only for Payments alerts
  and cancellations.