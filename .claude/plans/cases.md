# pub-ads-mar — v2 User Scenario Backlog

> Story-first backlog. No schemas, no endpoints, no controller names. This file
> is the source of truth for what users do; technical plans live elsewhere
> (`.claude/plans/*-technical-*.md`).
>
> Status: **draft v2** — does NOT supersede design.md. design.md remains the
> canonical design (modules, roles, interactions); this file is an isolated and
> expanded scenario backlog used as a **compliance lens** — i.e. design.md must
> be able to support every story below. Gaps surfaced by that comparison are
> tracked separately.
> Changes vs v1 are summarized at the bottom (Diff summary section).

## CLIENT scenarios

1. **Open the portal and see nearby spaces.** The client lands on a map centered on their geolocation (Monterrey by default). The right rail lists nearby spaces sorted near-to-far, each tagged with type (billboard, big screen, little screen, radio station, kiosk, transit). Changing the map center re-sorts the list and re-queries radio stations in range.

2. **Filter the catalog.** The client filters by space type, date range, and budget per day. Spaces whose calendar does not match the chosen date range are not hidden; they appear with a "doesn't coincide with your timespan" tag and remain selectable for "book it for later." (Dates are EXACT — there is NO date-flexibility ±X days filter; design.md supersedes the earlier v2 idea. Owner decision 2026-06-20.)

3. **Read details before committing.** The client opens any space and sees photos, exact map pin, price per day/month/custom span, the provider calendar, declared physical specs, declared media-delivery specs the client will need to satisfy, the provider's recent proof history, and a **provider star rating** (1–5 stars + total number of ratings). The rating is the average of all per-campaign ratings left by past clients of that provider; only clients can rate, and only after one of their campaigns with that provider has either ended OR has been live for at least 30 days. Each client can rate a given provider once per campaign. Ratings include an optional short comment and the system flags abusive comments for Support review.

4. **Add to a campaign.** The client multi-selects spaces and chooses "Add to a new campaign" or "Add to an existing campaign." A 10-second toast appears with "Go to my new campaign." Selected spaces sit in the campaign backlog with the legend "you should add those to an adset to start."

5. **Organize the backlog into adsets.** The client uses "Send all selected to a new adset," producing "Adset 1." Adsets are pure grouping labels; each ad keeps its own price, dates, and provider. The campaign view also shows an **Orphan spaces** list — spaces the client added to the backlog but has not yet placed inside any adset. Orphan spaces are still selectable for further bulk-move into an existing or new adset; they cannot reach checkout while still orphan. The list shows how many spaces are orphan with a one-click "Move all to new adset" affordance.

6. **Attach media per ad.** The client uploads media to each ad — image for billboard, MP3 for radio, video for big screens. The system warns on type mismatch (e.g., MP3 to a billboard) before upload completes. Right next to the upload control the client sees the **provider's upload instructions** inline (not buried in a tooltip): a short, human-readable summary derived from the space's declared media-delivery specs, plus any free-text instruction the provider added (e.g., "Add a video with audio, because this screen plays audio" or "Submit the print file with 1 cm bleed and CMYK colour"). The instructions remain visible while the upload progresses and after success so the client can compare against the file they sent.

7. **Upload an ad that conforms to space media specs.** When the client uploads, the system validates the file against the provider-declared media-delivery specs (format, resolution, color profile, bleed, duration, bitrate, file-size cap). If the file fails, upload is rejected with a clear message such as "your file must be PDF/X-1a, CMYK, 150 DPI at trim size, with 1 cm bleed."

8. **Set the time window and pay.** The client sets a per-ad time window. Conflicting calendars surface a "Book it for later" option. One checkout per campaign covers all ads regardless of provider. For ads whose requested window is not yet free (i.e. "Book it for later"), the client can mark the booking as **Pre-pay** — funds are captured and the booking enters the provider's **pre-pay waiting list** for that space. The provider sees the waiting list per space and can pick which pre-pay offer to confirm when the slot opens, using whatever criteria they prefer (highest total, longest window, repeat client). Pre-pay funds remain in escrow until the provider confirms; if the slot never opens within the offer's expiry window the client is refunded to wallet credit. Non-pre-pay "book it for later" entries remain queued without funds captured and are resolved first-come-first-served when the calendar frees.

9. **Wait for approvals.** Each ad shows one of three states: waiting for provider approval, waiting for booking confirmation, or live.

10. **Watch for proofs.** Providers have 5 days from display start to upload proof. The client is notified when a proof lands. If the deadline is missed, the ad auto-cancels and the client is refunded to wallet credit. The proof-deadline length (default 5 days) is not hard-coded — it is set by Admin in a system **Configurations** menu.

11. **Invite collaborators.** The client invites teammates by email from an **account-scoped Collaborators menu** (under Configurations) — a collaborator belongs to the **whole account, not a single campaign** (owner decision 2026-06-20, reaffirmed 2026-06-24). Per their assigned role they can review proofs, open chats with providers, open support tickets, and act across **all** of the account's campaigns, but cannot see billing or initiate payment.

12. **Chat with the provider.** A chat button is present on every ad, adset, and campaign, and also in a global **Help** menu available to every user on the account. PII (phone numbers, emails, URLs) is masked in transit.

13. **Open a support ticket.** A "Talk to support" button on any campaign, adset, or ad opens a thread that automatically attaches the object's context (provider, dates, payment status, proof state).

14. **Cancel or pause.** Pre-approval cancel is free. Post-approval, pre-display cancel returns a partial refund per policy. Post-display cancel returns nothing unless a proof fails. All cancellations trigger the alert schema below. The **refund policy, terms & conditions, and privacy policy** are linked in the site footer and surfaced as a link on the checkout screen before the client pays.

15. **Flag a mismatch.** A client or collaborator can flag a proof as not matching what they paid for. The flag immediately holds the provider payout and opens a support ticket with the ad and the proof automatically attached to it.

## PROVIDER scenarios

1. **List a space with two spec sets.** The provider creates a space with type (billboard, big screen, little screen, radio station, kiosk, transit), name, text address, lat/long, photos, and pricing per day/month/custom span. The provider declares **(a) PHYSICAL SPACE specs** (size in cm, screen resolution, wattage, station frequency, loop length, average daily impressions) **and (b) MEDIA-DELIVERY specs** the client must conform to when uploading the ad file. The media-delivery specs auto-populate from a per-type template (see the media-specs section) and the provider can tighten them. The provider can also add **free-text rules** describing extra file requirements (brightness, margins, and other specs). Because the provider is the one who ultimately publishes the ad, the provider may reject any ad they choose; when an ad is rejected, the client is not charged.

2. **Set availability.** The provider edits the calendar by hand, imports an .ical file, or pastes a public Google/Outlook URL. A help "?" explains how to find the URL. The UI **strongly recommends** connecting a live online calendar URL because it is easier to keep current. If a space's calendar was last set up more than 7 days ago, an alert is raised next to that space in the provider's spaces list, prompting them to refresh it.

3. **Manage the listing.** The provider can pause, unpublish, or delete a space. Delete is blocked if there are active or future confirmed bookings, or if there are any open support tickets referencing the space.

4. **Review booking requests.** The queue shows client, dates, total, attached media file. Actions are **Approve** or **Reject (with reason)** only — the rejection reason is meant to help the client try again with modifications to their file. No counter-offers.

5. **See confirmed bookings.** Confirmed bookings move to an installation queue with the install date and the file the client uploaded. The provider can add employees who are allowed to view this installation list — useful for install crews handling physical ads.

6. **Install and upload proof.** Within 5 days of display start, the provider uploads a photo (for printed/screen ads) or a short video (for radio/audio ads). Reminders fire on day 3 and day 4. Missing day 5 auto-cancels the booking, refunds the client, and accrues a strike.

7. **Re-upload after a proof rejection.** If the CLIENT rejects the proof (or a collaborator flags a mismatch), the provider has 48 hours to re-upload, and a ticket with the payment attached is opened for BOTH Support and Payments while the payout stays held (owner decision 2026-06-20 — Payments does NOT review proof content).

8. **Chat with clients.** From any booking or ad the provider can open the chat; PII is masked.

9. **Revenue dashboard.** The provider sees upcoming, held, paid, and refunded amounts, plus a strike counter for the trailing 90 days.

10. **Dispute a proof rejection or a mismatch flag.** The provider opens a support thread tied to the ad. At this point Support can see and participate in the chat, and can flag the payout to be held or the total to be refunded (Payments executes the money movement).

## SUPPORT scenarios

1. **Ticket queue with full object context.** Each ticket carries its campaign/adset/ad reference, provider, payment status, and proof state.
2. **Join existing client↔provider chats.** A system message announces the join; full history is readable.
3. **Resolve disputes.** Valid → request refund and re-open the cancel path. Invalid → close ticket with reason.
4. **Open ad-hoc tickets on behalf of a user.** Such a support-initiated ticket is visible only to the provider involved.
5. **No money powers.** Support can only **flag** a refund or a payout hold for Payments to execute; it cannot execute either itself.

## PAYMENTS scenarios

1. **Release payouts (money only — NOT proof content review; owner decision 2026-06-20).** Payments does not approve/reject proofs. When the CLIENT accepts a proof the payment becomes releasable and a Payments person manually releases it (auto-released only under the admin auto-approve threshold).
2. **Held payments queue.** When the client rejects a proof / a collaborator flags a mismatch, the related payment is auto-HELD and a ticket with the payment attached lands for both Payments and Support; Payments coordinates with Support on that thread and releases only on agreement.
3. **Process refunds.** Automatic on deadline miss; manual on support-validated disputes. Mocked Mercado Pago / PayPal. Refunds land in the client wallet with a withdraw option. Payments and Support can open a private thread between themselves that the client never sees, even when it references the same object as a client-facing conversation.
4. **Strike accrual is not a Payments power.** Strikes (missed proof, provider cancel, upheld mismatch) are routed to **Support first, then Admin** for review — never Payments — because a strike is not a payment situation. Three strikes in 90 days flags the account for Admin review.
5. **No content powers.** Payments cannot edit listings or media — Payments can only see payment-related data.

## ADMIN scenarios

1. **CRUD users.** Create providers via a dedicated provider form (company_name, address, phone required). Freeze and delete with guardrails (no delete if active bookings or unsettled funds).
2. **Moderate spaces.** Take down or restore listings.
3. **Edit the RBAC matrix.** A resource×action grid per role. The admin role's permission-management routes cannot be revoked (no lockout).
4. **Audit anything.** Read-only access to the immutable audit log.
5. **System health dashboard.** Queue depths, refund rate, strike rate, gateway health, and other operational stats.

## Cross-cutting: PII, Audit, Rate limits

- **PII masking.** Phone numbers, email addresses, full URLs, and street addresses outside the booked space are masked in chat for client↔provider. Masking relaxes when Support or Payments joins the thread, with a system message announcing it. Masking never applies on internal threads between Support, Payments, and Admin roles.
- **Audit log.** Append-only. Every role action that changes state (booking, payout, refund, strike, permission edit, listing takedown, ticket resolution) is recorded with actor, target object, before/after, and timestamp. The log is queryable as a per-object history — for each campaign, adset, ad, client, support ticket, and flag.
- **Rate limits.** Per-IP and per-account caps on login, chat send, file upload, search, and booking submission. Burst allowance for known good accounts. Throttled responses include retry-after.
- **Single currency.** All amounts are MXN.

## Cross-cutting: NOTIFICATION & ALERT SCHEMA

Columns: **Event | Trigger condition | Recipients (roles) | Channel | Urgency | Message template (≤140 chars)**

| Event | Trigger condition | Recipients (roles) | Channel | Urgency | Message template |
|---|---|---|---|---|---|
| New booking request | Client checkout completes for a space | Provider (owner) | in-app + email | high | "New booking on {space}: {dates}, {total} MXN. Approve or reject within 48h." |
| Booking approved | Provider approves request | Client (owner + collaborators), Support (log only) | in-app + email | med | "Your booking on {space} is approved. Display starts {date}." |
| Booking rejected | Provider rejects with reason | Client (owner + collaborators) | in-app + email | high | "Your booking on {space} was rejected: {reason}. Funds returned to wallet." |
| Booking confirmed (provider picks from queue) | Provider APPROVES one booking from the queue of pending requests for that space (NOT auto-assigned — provider chooses which offer wins, like a marketplace seller confirming stock). Owner decision 2026-06-20. | Client, Provider | in-app + email | med | "Your booking for {space} on {dates} was confirmed. Display begins {date}." |
| Day-3 proof reminder | Display started 3 days ago, no proof | Provider | in-app + email | med | "Upload proof for {ad} in 2 days or the booking auto-cancels." |
| Day-4 proof reminder | Display started 4 days ago, no proof | Provider | in-app + email + SMS | high | "Last day to upload proof for {ad}. Tomorrow it auto-cancels and you accrue a strike." |
| Proof uploaded | Provider uploads proof file | Client (owner + collaborators) | in-app + email | low | "Proof uploaded for {ad}. Review under Campaigns." |
| Proof accepted | CLIENT accepts proof (NOT Payments — owner decision 2026-06-20) | Client, Provider, Payments | in-app + email | low | "Proof accepted for {ad}. Payment is now releasable." |
| Proof rejected | CLIENT rejects proof / collaborator flags mismatch | Provider, Client, **Support + Payments** (ticket w/ payment attached, payout HELD) | in-app + email | high | "Proof on {ad} rejected: {reason}. Re-upload within 48h; payment held pending Support↔Payments review." |
| Secondary proof mismatch flagged | Client/collaborator flags mismatch | Provider, Payments, Support | in-app + email | high | "Mismatch flag on {ad}. Payout held pending review." |
| Cancel by client pre-approval | Client cancels before provider approval | Provider | in-app + email | low | "Booking on {space} cancelled by client before approval. No charge." |
| Cancel by client post-approval | Client cancels after approval, before display | Provider, Payments, Support | in-app + email | high | "Booking on {space} cancelled. Partial refund issued per policy." |
| Cancel by provider after confirm | Provider cancels a confirmed booking | Client, Payments, Support, Admin | in-app + email + SMS | critical | "{space} booking cancelled by provider. Full refund to your wallet." |
| Auto-cancel on proof miss | Day-5 elapsed without proof | Client, Provider, Payments, Support | in-app + email | critical | "{ad} auto-cancelled — proof not uploaded. Full refund issued. Strike on provider." |
| Refund issued by Payments | Refund executed at gateway | Client, Support | in-app + email | high | "{amount} MXN refunded to your wallet for {ad}. Withdrawable now." |
| Refund failed at gateway | Gateway returns error | Payments, Support, Admin | in-app + email + SMS | critical | "Refund for {ad} failed at {gateway}. Manual intervention required." |
| Dispute opened by client | Client opens dispute ticket | Support, Provider | in-app + email | high | "Dispute opened on {ad}. Support will review within 24h." |
| Dispute resolved valid | Support sides with client | Client, Provider, Payments | in-app + email | high | "Dispute on {ad} resolved in client's favor. Refund being processed." |
| Dispute resolved invalid | Support sides with provider | Client, Provider | in-app + email | med | "Dispute on {ad} closed. Provider payout will proceed." |
| Payout held | Mismatch flag or open dispute | Provider, Payments | in-app + email | high | "Payout for {ad} held pending review." |
| Payout released | Proof approved and no holds | Provider | in-app + email | low | "Payout of {amount} MXN released for {ad}." |
| Strike accrued | Missed proof, cancel-by-provider, or upheld mismatch | Provider, Support | in-app + email | high | "Strike accrued: {reason}. {n}/3 in trailing 90 days." |
| 3-strike admin alert | Provider hits 3 strikes in 90 days | Support, Admin | in-app + email | critical | "Provider {name} hit 3 strikes. Review for freeze." |
| Support agent joined chat | Support enters a thread | Client, Provider | in-app | low | "Support joined this thread. PII masking relaxed." |
| PII attempted in masked thread | Pattern match on phone/email/URL | Sender | in-app | low | "We masked your message — sharing contact info isn't allowed before Support joins." |
| Collaborator invited | Client adds collaborator email | Invited collaborator | email | low | "You've been invited to collaborate on {account}. Accept to view its campaigns and proofs." |
| Listing taken down | Admin moderates a space | Provider | in-app + email | high | "Your listing {space} was taken down: {reason}." |
| Listing restored | Admin restores | Provider | in-app + email | low | "Your listing {space} is live again." |
| Provider frozen | Admin freezes account | Provider, Payments | in-app + email | critical | "Your provider account is frozen. New bookings paused and upcoming bookings cancelled (clients refunded). Contact support." |
| Ad rejected by provider | Provider rejects an uploaded ad/file | Client (owner + collaborators) | in-app + email | high | "Your ad on {space} was rejected: {reason}. No charge applied. Re-upload a corrected file." |
| Pre-pay offer placed | Client pre-pays a "book for later" slot; funds escrowed | Provider, Client | in-app + email | low | "Pre-pay offer placed on {space} for {dates}. {total} MXN held in escrow." |
| Pre-pay offer expired | Slot didn't open within the offer's expiry window | Client | in-app + email | med | "Pre-pay offer on {space} expired. {amount} MXN returned to your wallet." |
| Calendar setup stale | A space's calendar setup is older than the staleness threshold (default 7 days) | Provider | in-app | low | "Calendar for {space} is {n} days old. Refresh it or connect a live URL." |
| Collaborator opened a ticket | A collaborator opens a support ticket/chat on a campaign | Client (owner), Support | in-app + email | med | "{collaborator} opened a support ticket on {campaign}." |
| Mismatch flag filed (flagger ack) | Client/collaborator flags a proof mismatch | Flagging client/collaborator | in-app | low | "Your mismatch flag on {ad} was filed. Payout held; Support will review." |

## Cross-cutting: MEDIA-DELIVERY SPECS BY SPACE TYPE

Columns: **Space type | Provider declares (physical) | Client must deliver (file)**

| Space type | Provider declares (physical) | Client must deliver (file) |
|---|---|---|
| Billboard (printed) | Width × height in cm; substrate (vinyl, paper, mesh); viewing distance; install method | PDF/X-1a or TIFF; CMYK; 150 DPI at trim size¹; 1 cm bleed on all sides; outlined fonts; max 200 MB |
| Digital screen (big, outdoor) | Pixel resolution (e.g., 1920×1080); orientation; loop slot length in seconds; brightness in nits; refresh rate | MP4 H.264 or JPG/PNG; exact pixel resolution match; sRGB; 8–15 s for video; ≤ 50 MB; 30 fps |
| Little screen (indoor) | Pixel resolution; orientation; loop slot length; ambient context (elevator, lobby, taxi) | MP4 H.264 or PNG/JPG; exact resolution; sRGB; 6–10 s; ≤ 25 MB; 30 fps |
| Radio station | Station frequency (MHz); coverage area; spot length sold (15 s / 30 s); daypart | MP3 or WAV; 44.1 kHz / 16-bit minimum; stereo; −14 LUFS²; exactly 15 s or 30 s; ≤ 10 MB |
| Transit (bus, taxi wrap, subway car card) | Vehicle type; surface dimensions in cm; substrate; route or fleet size | PDF/X-1a; CMYK; 150 DPI at trim; 1 cm bleed; outlined fonts; max 150 MB |
| Kiosk (mall, street furniture) | Panel dimensions in cm; backlit yes/no; double-sided yes/no | PDF/X-1a; CMYK; 200 DPI at trim; 1 cm bleed; outlined fonts; max 150 MB |

¹ 150 DPI at trim size is the standard for large-format outdoor print viewed from 3 m+; smaller panels viewed up close (kiosk) bump to 200 DPI.
² −14 LUFS aligns with common Mexican broadcast loudness targets; provider can override per station.

## Revised journeys

1. **First-time client, single billboard.** Map → space detail → add to new campaign → adset → upload print-ready PDF (validated against media specs) → checkout → wait approval → live → proof received → done.
2. **First-time provider, first listing.** Sign up → list a space → declare physical and media-delivery specs → set calendar via Google URL → first booking arrives → approve → install → upload proof on day 2 → payout released.
3. **Radio campaign across 3 stations.** Filter for radio in range → multi-select 3 stations → new campaign → upload three −14 LUFS 30 s MP3s, each validated against the station's declared spot length → adset "Morning Drive" → checkout → all three approve → proofs (short video of broadcast monitor) → payouts released.
4. **Mixed-media campaign.** One campaign holds billboard + big screen + radio. Adsets group by neighborhood. Each ad's file is validated against its space's media-delivery specs. Some slots are "book for later." Single checkout covers all.
5. **Missed deadline.** Provider misses day 5 proof → auto-cancel → client wallet credited in full → strike accrued → critical alert to client, provider, Payments, Support.
6. **Mismatch flag from a collaborator.** Collaborator sees the live billboard shows last month's art → flags mismatch → payout held → Support joins chat → dispute resolved valid → refund issued, strike accrued.
7. **Provider freeze.** Provider accrues 3 strikes in 90 days → admin alerted → admin freezes → all upcoming bookings on that provider auto-cancel with full refund.
8. **Disputed proof escalating to support.** Client rejects a proof for blur → payment auto-held + ticket to Support & Payments → provider disputes/re-uploads → Support reviews → Support + Payments agree → payout released.
9. **Conversation that travels.** Pre-booking inquiry → becomes booking thread on approval → escalates to a support ticket on a mismatch flag, all in the same chronological thread with system messages marking each transition and the PII-masking change when Support joins.

## Diff summary vs v1

> Note: "v1" here means the earlier version of *this* backlog — **not** design.md.
> design.md remains the canonical design file; this file is only the use-case
> backlog, so any difference between the two must be resolved against design.md.

- **Removed all negotiation and counter-offer language.** Client story #11 deleted; Provider story #5 deleted; provider booking-queue actions reduced to **Approve / Reject**; journey #5 (Negotiated booking) removed; total journeys now 9.
- **Expanded Provider "List a space"** to require **two spec sets**: **PHYSICAL SPACE specs** and **MEDIA-DELIVERY specs**, with a per-type template that auto-populates and that the provider can tighten, plus **free-text specs** the provider sets up.
- **Added Client story "Upload an ad that conforms to space media specs"** with hard-fail validation at upload time and a concrete error message.
- **Added a dedicated Cross-cutting NOTIFICATION & ALERT SCHEMA section** as the longest section, covering every refund-adjacent event (pre/post-approval cancels, provider cancel, auto-cancel on miss, refund issued, refund failed, dispute opened/resolved, payout held/released, strike accrued, 3-strike review) plus the non-refund events called out (new booking request, booking confirmed, day-3/day-4 reminders, proof approved/rejected, mismatch flagged, **Support agent joined chat**, **PII attempted in masked thread**).
- **Added Cross-cutting MEDIA-DELIVERY SPECS BY SPACE TYPE table** covering billboard, digital screen (big), little screen, radio station, transit, and kiosk, with footnotes for defensible defaults (150 DPI large-format print, −14 LUFS broadcast loudness).
- Added Client story **"Flag a mismatch"** as an explicit sibling of the cancel/pause path to anchor the secondary-proof flag flow referenced in the alert schema.
- Clarified that **adsets remain pure grouping labels** (no price/date effect) and that **checkout stays one-per-campaign**.
- Tightened Support and Payments boundaries: **Support has no money powers**, **Payments has no content powers**, both restated in their sections.
- **Admin is the eagle-eye role** above Support and Payments: it can see everything, check stats, and open/observe Support threads and the private Support↔Payments chats.
