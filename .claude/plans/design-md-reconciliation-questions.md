# design.md ↔ cases-v2.md — Reconciliation Questions

> Status: **draft**, 2026-06-05. Produced by a 4-agent review team (CLIENT,
> PROVIDER, SUPPORT/PAYMENTS/ADMIN, CROSS-CUTTING) after the bracketed `{...}`
> comments in `cases-v2.md` were folded into the prose.
>
> Purpose: list everything that must be decided so `design.md` (canonical) and
> `.claude/plans/cases-v2.md` (use-case backlog) **complement each other** —
> i.e. design.md can support every story and neither doc contradicts the other.
> design.md is the source of truth; where it is silent or narrower, it must be
> amended. Nothing here edits design.md — answer the questions first.
>
> Companion files: `design.md` (canonical) and `cases-v2.md` (use cases). The
> earlier `design-md-gap-analysis.md` and `design-md-proposals.md` were folded
> into this file (see ROUND 3) and deleted to avoid file bloat.

## How to read this

design.md is only ~64 lines and ends mid-sentence inside "Use Case 1." It is
**thin**: of ~45 backlog stories, almost none are fully covered. The findings
below are grouped by what kind of action they force, hardest first.

---

## A. HARD CONFLICTS — design.md actively contradicts cases-v2 (must edit design.md)

These are the only places the two docs *disagree* (vs. merely design.md being silent).

1. **Collaborator scope & proof ownership.**
   design.md (L22): "Clients can add employees to upload proofs **only**… the
   collaborators should be able to see the list of proofs they need to fill." {Please expand to collaborators having roles inside the organization, the roles should be installator, manager and publicist, where installator just uploads proofs, manager manages all, publisher createsads but can't see any topic about money, admin and publisher can raise tickets of support}
   cases-v2: collaborators *review* proofs, open chats, open support tickets,
   and act on the campaign (no billing) — and the **provider owns the PRIMARY
   proof**, collaborators only file secondaries/flags.
   → **Q1.** Who owns the primary proof of display — provider or client
   collaborator? If both can upload, what is precedence and what is the
   collaborator's "secondary proof" for? {Maybe I didn't explain well, the provider is a company that can have many collaborators many of them are installators of physical ads in the streets so they can take photos easily and upload them as proof in the same day, but they shouldn't see anything about monet, make sense ?}
   → **Q2.** Confirm collaborators gain chat + ticket + review powers (no
   billing). design.md's "upload proofs only" must be rewritten. {This is true for collaborators with installator role, only can upload proof, the collaborators with role publisher can create campaigns and everything and just maager can see payments}

2. **Admin visibility scope.**
   design.md (L6): "Admins check messages **between Providers and Clients**."
   cases-v2: Admin is the **eagle-eye** role — sees everything, incl. internal
   Support threads and the private Support↔Payments chats. {Admin should have eagle eye, I think is obvious}
   → **Q3.** Does canonical Admin expand to *all* threads (provider↔client +
   support + internal payments)? Rewrite L6 accordingly. {Yes all threads and some key indicators in the dashboard, but basically admin can see anything but silent without anouncing that enters the chat or anything like that}

3. **Proof deadline: fixed vs configurable.**
   design.md (L20, L22): hard-states "**5 days**" / "less than 5 days."
   cases-v2 (Client #10): 5 days is a **default**, Admin-configurable in a
   Configurations menu. {cases-v2 was written after so most things are better in that document, please remember that there is not a reason for both documents be agree all the time and apply some logic to it, is better to have a configurable days than a fixed 5 days, because it may change quickly in the future}
   → **Q4.** Confirm 5 days is only a default. Who sets it, and does a change
   apply to in-flight bookings or only new ones? {yes only a default, admin should have a configurations screen to change that number}

4. **Provider delete freedom vs guardrails.**
   design.md (L20): providers "insert, update, delete and pause **all the
   spaces they want**." {deletion can't mess anything like ads in use or open tickets, that's the logic}
   cases-v2 (Provider #3): delete is **blocked** by active/future confirmed
   bookings *or* open support tickets referencing the space. {Yes}
   → **Q5.** Confirm the guardrail overrides design.md's wording. What counts
   as "active/future confirmed" vs merely requested, and which tickets block
   (client-vs-provider, support-initiated)? {all tickets may block deletion, but remember that I say when an ad is programmed for deletion it should go unpublish and stop receiving payments or any bookings request, but the ad in the providers side should be visible with a legend "this is programmed for deletion"}

5. **File acceptance: manual approval vs automated hard-fail.**
   design.md (L14): "the provider is responsible for **approve** the files
   uploaded" (manual judgment). {The ads are files so those should have some automated metrics for acceptance, if those metrics aren't comply the files will be rejected on upload, if that metrics are ok but the provider need other specs for final approval, provider may reject the ad and ask the client to upload again or even reject forever, please think about it}
   cases-v2 (Client #7): non-conforming files are **rejected at upload** by
   automated spec validation; {yes} (Provider #1): provider may *also* reject any ad
   at review. {yes} So a file can pass validation yet still be provider-rejected. {yes}
   → **Q6.** Confirm both rejection paths coexist (machine-at-upload +
   human-at-review). Passing the spec table must NOT imply guaranteed
   acceptance — design.md should say so. {I confirm}

---

## B. SILENT SUBSYSTEMS — referenced by cases-v2, absent from design.md (must add to design.md)

design.md says nothing about any of these; each underpins multiple stories and
must be made canonical (or the stories cut). {Yes as I say cases-v2 is a complement for design.md but it was writed after so it has a lot of improvements to the whole system, in a few words cases-v2 has more weight than design.md in new features, but design.md is a good starting point so in other words cases-v2 is way more granular than design.md, make sense?}

- **Strike system** — accrual taxonomy (missed proof, provider cancel, upheld
  mismatch), 90-day trailing window, 3-strike → freeze. Underpins Provider #6/#9,
  Payments #4, Admin #1/#5. {Yes add it to design.md}
- **Client wallet + withdraw** — refunds land in an in-app MXN wallet, not the
  original method. design.md only says money "will not be sent to the provider." {I think is the same, please think about it is pretty straightforward for me, what do you think?}
- **Audit log** — append-only, immutable, per-object history (campaign, adset,
  ad, client, ticket, flag), actor + before/after + timestamp; Admin read-only. {Yes, add it to the eagle eyes features of the admin}
- **Rate limits** — per-IP/per-account caps + burst + retry-after.
- **Internal Support↔Payments private threads** — client never sees them; can
  reference the same object as a client-facing conversation; Admin can observe. {Yes admin can observe and send messages to support and payments related to that thread, so tha thread is an object too}
- **Configurations menu** — Admin-set values (proof deadline, etc.). {Yes}
- **Notification/alert delivery machinery** — channels (in-app/email/SMS),
  urgency mapping; the 28-row schema as a whole. {Yes alerts should be added}
- **Pre-pay waiting list + escrow** — funds captured before approval, provider
  picks from per-space list, wallet-credit refund on expiry; plus the separate
  non-pre-pay FCFS "book for later" queue. {payments will be made to provider in the less time possible but should be approved by payments first, if there's nothing stopping a payment the admin will have a 24 hours window (configurable) to stop the payment, if admin don't stop it it will be payed to the provider account (mercado pago or paypal)}
- **Provider star ratings** — 1–5 + count, per-campaign, 30-day gate,
  abuse-flagging. {yes}
- **Media-delivery spec sets** — physical specs + client-file specs + provider
  free-text rules + per-type auto-populating templates ("tighten only"). {yes}
- **Provider freeze state** and **admin listing takedown/restore** states
  (distinct from provider pause/unpublish). {Please elaborate this whenever you can}
- **UI/UX states named only in cases-v2** — date-flexibility ±X filter;
  orphan-spaces list that blocks checkout; the "**live**" ad state (design.md
  names only "waiting for approval" / "waiting for booking confirmation"). {yes live state should exist}
- **Legal documents** — refund policy, T&C, privacy; footer + checkout links. {yes}
- **Two chat surfaces** — contextual client↔provider chat on every
  ad/adset/campaign, plus a global Help-menu chat for all account users (on top
  of the support-ticket chat design.md already describes). {yes}
- **PII masking** — entire mechanism (patterns, relax-on-join, never on
  internal Support/Payments/Admin threads). {yes}

→ **Q7.** For each subsystem above: promote to canonical design.md, or drop the
dependent stories? (Recommend promote — they are core to the product.) {Are we talking about logs? if you see something that is core product please keep it}

---

## C. INTERNAL INCONSISTENCIES inside cases-v2 (already FIXED this session)

Surfaced by the cross-cutting + S/P/A agents; corrected in `cases-v2.md`:

- **Strike alerts vs "strikes never Payments."** Payments #4 now says strikes
  route Support→Admin, but two notification rows still pointed at Payments and
  omitted Support. **Fixed:** "Strike accrued" → recipients `Provider, Support`;
  "3-strike admin alert" → recipients `Support, Admin` (Payments removed).
- Remaining channel nit (NOT yet changed — see Q15): "Auto-cancel on proof
  miss" is `critical` but in-app+email only, while lower-severity rows escalate
  to SMS.

---

## D. NOTIFICATION SCHEMA — missing rows (decide whether to add)

Events implied by the folded-in requirements but absent from the table:

- **Provider rejects an ad** (Provider #1, "client is not charged") — distinct
  from "Booking rejected"; client currently gets no alert. {if provider rejects an ad it should be because a missing thing in the file or maybe they have a bad experience with that client before, so there's different reasons an add can be rejected, if it is because a file was rejected the user should know to change the file and follow instructions, at the same time the provider can send messages about that, but masking any contact data}
- **Pre-pay funds captured / joined waiting list** (Client #8) — no provider
  alert ("new pre-pay offer") nor client confirmation. {Waiting list can be seen by provider but just a simple list, no info about the payments, because the provider may reject (add an UI to trash bookings in the provider side) ads bookings because some client is better than other or is more time and is better for the business}
- **Pre-pay offer expired → wallet refund** (Client #8) — escrow-expiry refund
  uncovered. {Client should get the refund and there should be a notification for the client, it should be saved in the logs in case payments or admin should check it}
- **Calendar-staleness >7 days** (Provider #2) — in-app provider alert not in
  schema. {add notifications if needed}
- **Collaborator-initiated ticket/chat** (Client #11/#13) — owner not notified;
  schema assumes the client is the actor. {Manager of the account on client side should see any conversation of collaborators, that make sense ?}
- **Mismatch-flag auto-ticket created** (Client #15) — flagger gets no
  confirmation; only the flag (not the ticket creation) is notified. {Please explain}

→ **Q8.** Which of these get notification rows, and on which channels? {notifications should be simple text on top of the UI, some notifications are tickets in chat with support or payments, but those are explicitly chats}

---

## E. UNDEFINED POLICY VALUES — only the project owner can set these

Referenced by both docs but never given concrete values:

- **Q9. Refund-tier percentages** — post-approval/pre-display partial refund %
  (flat or per space-type)? And what does CLIENT "pause" mean vs cancel? {if client cancel we refund 90% and we keep with 10%, only 5% goes to provider, make this configurable by admin}
- **Q10. Pre-pay escrow expiry window** — length; client/provider/system-set;
  Admin-configurable? Priority between a funded pre-pay offer and an older
  unfunded FCFS "book for later" entry when a slot frees. {yes make admin configurable}
- **Q11. Rate-limit thresholds** — per-action caps, burst size, retry-after. {admin configurable ?}
- **Q12. 48-hour re-upload window** — fixed or Admin-configurable? Does the
  clock pause during a provider dispute (Provider #7 ↔ #10)? {admin configurable, yes the clock should stop if any chat starts about this, support gets an alert to see the chat and can enter to chat}
- **Q13. Full Admin-configurable set** — proof deadline, 48h re-upload, 90-day
  strike window, 3-strike threshold, pre-pay expiry, 7-day calendar-staleness:
  which are config vs constants? {most of them should be configurable, please make a summary of this in design.md}
- **Q14. Audit-log retention** — how long; archived/purged; immutability
  enforced at DB level (write-once) or by policy? Does per-object history also
  cover space/listing, provider account, booking, payout (currently omitted)? {save a log per action of any role, make configurable the quantity of logs per role in the admin side}
- **Q15. SMS rule** — should every `critical` row carry SMS (so auto-cancel-on-
  miss does too)? Confirm the urgency→channel mapping. {only payments alerts and cancels}
- **Q16. Currency** — confirm MXN-only is canonical; integer centavos or
  decimal? {yes centavos, yes MXN but make it configurable by admin}
- **Q17. Provider star ratings** — are comments public on the space detail or
  only the average+count? Who reviews abuse-flagged comments (Support vs Admin)
  and at what threshold? {provider star ratings should be 5 stars and with simple text comment}
- **Q18. Date-flexibility filter** — does ±X shift the whole window or widen the
  acceptable band? Free numeric input or presets? Max value?
- **Q19. Orphan-spaces gating** — confirm orphan (un-adsetted) spaces block
  checkout; can a client check out a partial campaign and leave orphans?
- **Q20. Mismatch-flag authority** — can a single client/collaborator flag
  unilaterally freeze a payout? What un-holds it, and is there a flag-abuse
  guard?
- **Q21. "Employees" mechanism** — does provider-employees (Provider #5, view
  installation list) reuse the client collaborator invite/RBAC mechanism or is
  it a new concept? What else can a provider employee see/do?
- **Q22. Calendar-staleness clock** — measured from last manual edit, last
  successful URL/.ical sync, or space creation? Does it ever fire for a live
  auto-syncing URL?
- **Q23. RBAC encoding of "flag vs execute"** — can the resource×action matrix
  express Support "flag/request" as distinct from Payments "execute"? What are
  the default cells for Support×payments and Support×proofs (note Payments still
  needs proof *read* to approve, so "payments data only" must carve that out)?
- **Q24. Legal docs ownership** — who authors refund/T&C/privacy, and is
  checkout an explicit click-to-agree gate?
- **Q25. Street-address masking** — how is the booked space's own address
  whitelisted while off-platform addresses are masked?

---

## Recommended next step

Resolve **Section A (Q1–Q6)** first — they are true contradictions and block
everything else. Then take a Section-B decision (Q7: promote-or-drop) per
subsystem. Sections D/E can be answered in batches. (Note: this "Recommended next step"
is from Round 1 and is now historical — see ROUND 2 and ROUND 3 below for the
current state.)

---

# ROUND 2 — 2026-06-05 (after the 6 conflicts were resolved & cases-v2 folded into design.md)

The 6 hard conflicts (A1–A6 above) were resolved by the owner and applied to
`design.md`: collaborators get owner-configurable roles (provider owns primary
proof); Admin = eagle-eye; proof deadline admin-configurable; provider delete
guarded by tickets/bookings (else unpublish); manual+automated file acceptance
coexist; Support flags / Payments executes. A ~17-section "CANONICAL SUBSYSTEMS"
block + an "ARCHITECTURE NOTES" block were appended to design.md. A 3-agent team
(congruency, architecture critique, risk review) then re-checked everything.

## Fixes applied this round (no longer open)
- **Payout trigger** clarified: primary proof makes a booking *eligible*;
  Payments reviews → approval releases payout / rejection opens 48 h re-upload.
- **Provider freeze** aligned to cases-v2 journey #7: pauses new bookings AND
  auto-cancels upcoming bookings with full wallet refund. (Alert template updated.)
- **Notification table completed**: added 6 missing rows (ad rejected by
  provider, pre-pay offer placed, pre-pay offer expired, calendar stale,
  collaborator opened ticket, mismatch-flag flagger ack).
- **Strike alert recipients** fixed earlier (Provider+Support / Support+Admin).
- **Admin operational/health dashboard** added to design.md.
- **Architecture defaults adopted** in design.md ARCHITECTURE NOTES: append-only
  wallet ledger, MXN centavos, refund/payout idempotency, transactional escrow,
  `display_start` = booking calendar start, America/Monterrey tz for deadlines,
  strikes decoupled from notification delivery + reversible, media-spec snapshot
  at checkout, Postgres gist exclusion for overlapping bookings, per-account
  collaborator grant overlay, extended capability vocabulary (flag vs execute),
  polymorphic + separate-row internal conversations, DB-level audit immutability.

## Still OPEN — owner decisions (new or still unresolved)
- **Q26. Clawback policy** — client suspended/fraudulent after a refund landed in
  (and possibly withdrawn from) their wallet: claw back, freeze wallet, or write off?
- **Q27. Config change effect on in-flight bookings** — when Admin changes proof
  deadline / re-upload window / strike threshold, apply to live bookings or only
  new ones? (design.md recommends snapshot-at-creation; confirm.)
- **Q28. Pre-pay cross-queue precedence** — does a funded pre-pay offer beat an
  older unfunded FCFS "book for later" entry when a slot frees?
- **Q29. Re-upload clock during dispute** — does the 48 h proof re-upload window
  pause while a provider's dispute is open?
- **Q30. Proof re-upload loop bound** — max number of reject→re-upload cycles
  before escalation / hard final deadline?
- **Q31. SMS provider** — three+ events require SMS; pick a provider (Twilio?) or
  formally defer SMS to a later phase.
- Plus still-open value questions from Round 1: refund-tier % (Q9), pre-pay
  escrow expiry length (Q10), rate-limit thresholds (Q11), audit retention (Q14),
  currency unit confirmed centavos (Q16, now defaulted), ratings comment
  visibility + abuse reviewer (Q17), date-flexibility semantics (Q18).

## Architecture verdict (team opinion, condensed)
Sound and buildable on Laravel+Angular+Postgres; the separation-of-powers model
(Support flags / Payments executes / Admin observes / strikes→Support) is well
designed. **Biggest gap is not feasibility but that the schema lags the design**:
ARCHITECTURE.md still has a 3-role user enum, a 4-state booking enum, and no
proofs/wallet/escrow/strike/audit/notification/ratings/collaborator-overlay
tables. The money subsystem in particular must be built on an append-only ledger
with idempotency + transactional escrow before any real payments ship. Next
concrete step: reconcile ARCHITECTURE.md (schema) to design.md, starting with
the money/ledger tables and the canonical booking/proof state enums.

---

# ROUND 3 — 2026-06-09 (owner bracket-answers applied; proposals + gap-analysis files merged here and deleted)

The owner answered the bracketed questions across the three planning files. The
answers were applied to `design.md`, and `design-md-proposals.md` +
`design-md-gap-analysis.md` were folded in and deleted (only design.md,
cases-v2.md, and this file remain). The earlier per-question lists above are
historical; this Round-3 list is the current authority on what is still open.

## Decisions applied to design.md this round
- **Collaborator org-roles**: Installator (uploads proof only, no money),
  Publicist (creates/manages campaigns/ads + raises tickets, no money), Manager
  (manages all incl. money). Owner + Publicist can raise tickets; Manager sees
  any collaborator's conversations.
- **Admin eagle-eye**: reads AND posts in any thread, SILENTLY/incognito (no
  announcement); internal Support↔Payments thread is itself an object.
- **Proof deadline / 48 h re-upload / strike window / pre-pay expiry / calendar
  staleness / payout-stop window / refund split / currency / rate limits / per-
  role log retention** are all admin-configurable (summarized in design.md
  System Configurations).
- **Provider delete** → "programmed for deletion": unpublish, stop new
  bookings/payments, show legend until current bookings finish.
- **File acceptance**: automated metrics gate at upload + manual provider
  approval; provider may ask re-upload or reject permanently; not charged if
  rejected. An ad may have multiple files.
- **Cancellation refund split**: client 90% / platform 5% / provider 5%
  (configurable).
- **Payouts**: ASAP after Payments approval; Admin has 24 h (configurable) to
  stop; else auto-released to provider MP/PayPal.
- **Pre-pay waiting list**: provider sees a simple list with NO payment info +
  a trash action; expiry refund → wallet + notification + logged.
- **Re-upload clock STOPS** when a proof dispute chat starts (Support alerted).
- **SMS** only for Payments alerts + cancellations; other notifications are
  simple in-app text or explicit ticket/chats.
- **Audit log**: one entry per action of any role; admin read-only eagle-eye.
- **Ratings**: 5 stars + simple text comment.
- **Admin dashboard**: ads rejected by providers, re-uploads, support tickets,
  payments stopped (each links to a list → item opens full detail; tickets open
  incognito).
- **live** ad state confirmed; **provider freeze vs takedown/restore vs
  pause/unpublish** elaborated as three distinct levels.

## My clarifications / opinions on your prompts
- **Wallet vs "same as not paying provider" (you asked what I think)**: keep the
  wallet. It is NOT quite the same — a wallet lets refunds settle instantly,
  encourages re-spend, and avoids per-refund gateway fees/delays. The cost is
  added complexity (clawback, withdraw, frozen-wallet states), already noted in
  design.md ARCHITECTURE NOTES. Recommendation: wallet for refunds + optional
  withdraw to original method.
- **Mismatch-flag auto-ticket "please explain"**: when a client/collaborator
  flags a proof as not matching, the system (a) holds the provider payout and
  (b) auto-creates a Support ticket with the ad + the proof attached. The
  "flagger ack" notification just confirms to the person who flagged that their
  flag was filed and the payout is held — so they aren't left wondering.
- **Wallet=wallet-credit, Admin(platform)≠Manager(org role)**: treated as
  resolved (same concept / distinct roles).

## STILL OPEN — genuine owner decisions
Money & payout
- **O1. Gateway fees** — gross (client pays the fee) or net (platform absorbs)?
- **O2. Multi-provider campaign payout** — one unified payout date or a separate
  schedule per provider from the single invoice?
- **O3. Max payout-hold duration** on an open dispute before it auto-resolves?
- **O4. Clawback specifics** — if a refunded client is later suspended for
  fraud, claw back from wallet, freeze, or write off?
- **O5. Pricing across tiers** — how is a window that straddles day vs month
  pricing computed?

Booking / proof / pre-pay
- **O6. Pre-pay cross-queue precedence** — funded pre-pay offer vs older
  unfunded FCFS "book for later" when a slot frees?
- **O7. Proof re-upload loop bound** — max reject→re-upload cycles / hard final
  deadline?
- **O8. Partial campaign go-live** — if 9 of 12 ads are approved, do those go
  live or is it all-or-nothing?
- **O9. Proof geotag** — should a proof photo's location be checked against the
  space lat/long, and who adjudicates a mismatch?
- **O10. Config change effect** — apply to in-flight bookings or only new ones?
  (design recommends snapshot-at-creation; confirm.)

Catalog / availability
- **O11. Date-flexibility filter** — does ±X shift the whole window or widen the
  band? numeric input or presets? max?
- **O12. Orphan-spaces** — confirm orphans block checkout; can a client check
  out a partial campaign and leave orphans?
- **O13. Radio "in range"** — range of what: listener location, station
  footprint, or target market?
- **O14. Imported calendar offline** — fallback to manual, or does availability
  vanish?

Conversations / support
- **O15. Inquiry→booking thread transition** — on provider approval or on client
  payment?
- **O16. Support joining a masked thread** — sees full pre-mask history or only
  post-join?
- **O17. Support closing a ticket** — without the user's consent? appeal path?
- **O18. Mismatch-flag authority** — can a single flag unilaterally freeze a
  payout? what un-holds it? flag-abuse guard?
- **O19. Street-address masking** — how is the booked space's own address
  whitelisted while off-platform addresses are masked?

Policy / scope
- **O20. Strike appeal arbiter** — who can remove a strike from the 90-day
  counter?
- **O21. SMS provider** — pick one (e.g. Twilio) or formally defer SMS.
- **O22. Legal docs** — who authors refund/T&C/privacy, and is checkout an
  explicit click-to-agree gate?
- **O23. Launch scope** — Mexico-only or multi-country? UI Spanish or English?
  GDPR / LFPDPPP obligations in scope?
- **O24. Calendar-staleness clock** — measured from last manual edit, last
  successful sync, or space creation? does it fire for a live auto-syncing URL?
