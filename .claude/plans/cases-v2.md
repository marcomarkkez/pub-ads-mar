# pub-ads-mar — v2 User Scenario Backlog

> Story-first backlog. No schemas, no endpoints, no controller names. This file
> is the source of truth for what users do; technical plans live elsewhere
> (`.claude/plans/*-technical-*.md`).
>
> Status: **draft v2** — supersedes the v1 narrative held in design.md.
> Changes vs v1 are summarized at the bottom (Diff summary section).

## CLIENT scenarios

1. **Open the portal and see nearby spaces.** The client lands on a map centered on their geolocation (Monterrey by default). The right rail lists nearby spaces sorted near-to-far, each tagged with type (billboard, big screen, little screen, radio station, kiosk, transit). Changing the map center re-sorts the list and re-queries radio stations in range.

2. **Filter the catalog.** The client filters by space type, date range, and budget per day. Spaces whose calendar does not match the chosen date range are not hidden; they appear with a "doesn't coincide with your timespan" tag and remain selectable for "book it for later."

3. **Read details before committing.** The client opens any space and sees photos, exact map pin, price per day/week/month, the provider calendar, declared physical specs, declared media-delivery specs the client will need to satisfy, and the provider's recent proof history.

4. **Add to a campaign.** The client multi-selects spaces and chooses "Add to a new campaign" or "Add to an existing campaign." A 10-second toast appears with "Go to my new campaign." Selected spaces sit in the campaign backlog with the legend "you should add those to an adset to start."

5. **Organize the backlog into adsets.** The client uses "Send all selected to a new adset," producing "Adset 1." Adsets are pure grouping labels; each ad keeps its own price, dates, and provider.

6. **Attach media per ad.** The client uploads media to each ad — image for billboard, MP3 for radio, video for big screens. The system warns on type mismatch (e.g., MP3 to a billboard) before upload completes.

7. **Upload an ad that conforms to space media specs.** When the client uploads, the system validates the file against the provider-declared media-delivery specs (format, resolution, color profile, bleed, duration, bitrate, file-size cap). If the file fails, upload is rejected with a clear message such as "your file must be PDF/X-1a, CMYK, 300 DPI at trim size, with 1 cm bleed."

8. **Set the time window and pay.** The client sets a per-ad time window. Conflicting calendars surface a "Book it for later" option. One checkout per campaign covers all ads regardless of provider.

9. **Wait for approvals.** Each ad shows one of three states: waiting for provider approval, waiting for booking confirmation, or live.

10. **Watch for proofs.** Providers have 5 days from display start to upload proof. The client is notified when a proof lands. If the deadline is missed, the ad auto-cancels and the client is refunded to wallet credit.

11. **Invite collaborators.** The client invites teammates by email. Collaborators can review proofs, chat with providers, and act on the campaign, but cannot see billing or initiate payment.

12. **Chat with the provider.** A chat button is present on every ad, adset, and campaign. PII (phone numbers, emails, URLs) is masked in transit.

13. **Open a support ticket.** A "Talk to support" button on any campaign, adset, or ad opens a thread that automatically attaches the object's context (provider, dates, payment status, proof state).

14. **Cancel or pause.** Pre-approval cancel is free. Post-approval, pre-display cancel returns a partial refund per policy. Post-display cancel returns nothing unless a proof fails. All cancellations trigger the alert schema below.

15. **Flag a mismatch.** A client or collaborator can flag a proof as not matching what they paid for. The flag holds the provider payout and opens a support thread.

## PROVIDER scenarios

1. **List a space with two spec sets.** The provider creates a space with type (billboard, big screen, little screen, radio station, kiosk, transit), name, text address, lat/long, photos, and pricing per day/week/month. The provider declares **(a) PHYSICAL SPACE specs** (size in cm, screen resolution, wattage, station frequency, loop length, average daily impressions) **and (b) MEDIA-DELIVERY specs** the client must conform to when uploading the ad file. The media-delivery specs auto-populate from a per-type template (see the media-specs section) and the provider can tighten them.

2. **Set availability.** The provider edits the calendar by hand, imports an .ical file, or pastes a public Google/Outlook URL. A help "?" explains how to find the URL.

3. **Manage the listing.** The provider can pause, unpublish, or delete a space. Delete is blocked if there are active or future confirmed bookings.

4. **Review booking requests.** The queue shows client, dates, total, attached media file. Actions are **Approve** or **Reject (with reason)** only. No counter-offers.

5. **See confirmed bookings.** Confirmed bookings move to an installation queue with the install date and the file the client uploaded.

6. **Install and upload proof.** Within 5 days of display start, the provider uploads a photo (for printed/screen ads) or a short video (for radio/audio ads). Reminders fire on day 3 and day 4. Missing day 5 auto-cancels the booking, refunds the client, and accrues a strike.

7. **Re-upload after a proof rejection.** If Payments rejects the proof, the provider has 48 hours to re-upload.

8. **Chat with clients.** From any booking or ad the provider can open the chat; PII is masked.

9. **Revenue dashboard.** The provider sees upcoming, held, paid, and refunded amounts, plus a strike counter for the trailing 90 days.

10. **Dispute a proof rejection or a mismatch flag.** The provider opens a support thread tied to the ad.

## SUPPORT scenarios

1. **Ticket queue with full object context.** Each ticket carries its campaign/adset/ad reference, provider, payment status, and proof state.
2. **Join existing client↔provider chats.** A system message announces the join; full history is readable.
3. **Resolve disputes.** Valid → request refund and re-open the cancel path. Invalid → close ticket with reason.
4. **Open ad-hoc tickets on behalf of a user.**
5. **No money powers.** Support can request refunds and hold payouts via Payments, but cannot execute either.

## PAYMENTS scenarios

1. **Review proofs queue.** Approve releases payout; reject with reason opens the 48-hour re-upload window.
2. **Hold payouts** when a secondary (collaborator/client) proof flag is raised.
3. **Process refunds.** Automatic on deadline miss; manual on support-validated disputes. Mocked Mercado Pago / PayPal. Refunds land in the client wallet with a withdraw option.
4. **Strike providers.** Three strikes in 90 days flags the account for Admin review.
5. **No content powers.** Payments cannot edit listings or media.

## ADMIN scenarios

1. **CRUD users.** Create providers via a dedicated provider form (company_name, address, phone required). Freeze and delete with guardrails (no delete if active bookings or unsettled funds).
2. **Moderate spaces.** Take down or restore listings.
3. **Edit the RBAC matrix.** A resource×action grid per role. The admin role's permission-management routes cannot be revoked (no lockout).
4. **Audit anything.** Read-only access to the immutable audit log.
5. **System health dashboard.** Queue depths, refund rate, strike rate, gateway health.

## Cross-cutting: PII, Audit, Rate limits

- **PII masking.** Phone numbers, email addresses, full URLs, and street addresses outside the booked space are masked in chat for client↔provider. Masking relaxes when Support or Payments joins the thread, with a system message announcing it.
- **Audit log.** Append-only. Every role action that changes state (booking, payout, refund, strike, permission edit, listing takedown, ticket resolution) is recorded with actor, target object, before/after, and timestamp.
- **Rate limits.** Per-IP and per-account caps on login, chat send, file upload, search, and booking submission. Burst allowance for known good accounts. Throttled responses include retry-after.
- **Single currency.** All amounts are MXN.

## Cross-cutting: NOTIFICATION & ALERT SCHEMA

Columns: **Event | Trigger condition | Recipients (roles) | Channel | Urgency | Message template (≤140 chars)**

| Event | Trigger condition | Recipients (roles) | Channel | Urgency | Message template |
|---|---|---|---|---|---|
| New booking request | Client checkout completes for a space | Provider (owner) | in-app + email | high | "New booking on {space}: {dates}, {total} MXN. Approve or reject within 48h." |
| Booking approved | Provider approves request | Client (owner + collaborators), Support (log only) | in-app + email | med | "Your booking on {space} is approved. Display starts {date}." |
| Booking rejected | Provider rejects with reason | Client (owner + collaborators) | in-app + email | high | "Your booking on {space} was rejected: {reason}. Funds returned to wallet." |
| Booking confirmed (held slot freed) | "Book for later" slot opens up and is auto-assigned | Client, Provider | in-app + email | med | "Slot for {space} confirmed on {dates}. Display begins {date}." |
| Day-3 proof reminder | Display started 3 days ago, no proof | Provider | in-app + email | med | "Upload proof for {ad} in 2 days or the booking auto-cancels." |
| Day-4 proof reminder | Display started 4 days ago, no proof | Provider | in-app + email + SMS | high | "Last day to upload proof for {ad}. Tomorrow it auto-cancels and you accrue a strike." |
| Proof uploaded | Provider uploads proof file | Client (owner + collaborators), Payments | in-app + email | low | "Proof uploaded for {ad}. Review under Campaigns." |
| Proof approved | Payments approves proof | Client, Provider | in-app + email | low | "Proof approved for {ad}. Provider payout released." |
| Proof rejected | Payments rejects proof | Provider, Client | in-app + email | high | "Proof on {ad} rejected: {reason}. Re-upload within 48h." |
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
| Strike accrued | Missed proof, cancel-by-provider, or upheld mismatch | Provider | in-app + email | high | "Strike accrued: {reason}. {n}/3 in trailing 90 days." |
| 3-strike admin alert | Provider hits 3 strikes in 90 days | Admin, Payments | in-app + email | critical | "Provider {name} hit 3 strikes. Review for freeze." |
| Support agent joined chat | Support enters a thread | Client, Provider | in-app | low | "Support joined this thread. PII masking relaxed." |
| PII attempted in masked thread | Pattern match on phone/email/URL | Sender | in-app | low | "We masked your message — sharing contact info isn't allowed before Support joins." |
| Collaborator invited | Client adds collaborator email | Invited collaborator | email | low | "You've been invited to collaborate on {campaign}. Accept to view proofs." |
| Listing taken down | Admin moderates a space | Provider | in-app + email | high | "Your listing {space} was taken down: {reason}." |
| Listing restored | Admin restores | Provider | in-app + email | low | "Your listing {space} is live again." |
| Provider frozen | Admin freezes account | Provider, Payments | in-app + email | critical | "Your provider account is frozen. New bookings paused. Contact support." |

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
8. **Disputed proof escalating to support.** Payments rejects a proof for blur → provider disputes → Support joins → reviews original photo → sides with provider → payout released.
9. **Conversation that travels.** Pre-booking inquiry → becomes booking thread on approval → escalates to a support ticket on a mismatch flag, all in the same chronological thread with system messages marking each transition and the PII-masking change when Support joins.

## Diff summary vs v1

- **Removed all negotiation and counter-offer language.** Client story #11 deleted; Provider story #5 deleted; provider booking-queue actions reduced to **Approve / Reject**; journey #5 (Negotiated booking) removed; total journeys now 9.
- **Expanded Provider "List a space"** to require **two spec sets**: **PHYSICAL SPACE specs** and **MEDIA-DELIVERY specs**, with a per-type template that auto-populates and that the provider can tighten.
- **Added Client story "Upload an ad that conforms to space media specs"** with hard-fail validation at upload time and a concrete error message.
- **Added a dedicated Cross-cutting NOTIFICATION & ALERT SCHEMA section** as the longest section, covering every refund-adjacent event (pre/post-approval cancels, provider cancel, auto-cancel on miss, refund issued, refund failed, dispute opened/resolved, payout held/released, strike accrued, 3-strike review) plus the non-refund events called out (new booking request, booking confirmed, day-3/day-4 reminders, proof approved/rejected, mismatch flagged, **Support agent joined chat**, **PII attempted in masked thread**).
- **Added Cross-cutting MEDIA-DELIVERY SPECS BY SPACE TYPE table** covering billboard, digital screen (big), little screen, radio station, transit, and kiosk, with footnotes for defensible defaults (150 DPI large-format print, −14 LUFS broadcast loudness).
- Added Client story **"Flag a mismatch"** as an explicit sibling of the cancel/pause path to anchor the secondary-proof flag flow referenced in the alert schema.
- Clarified that **adsets remain pure grouping labels** (no price/date effect) and that **checkout stays one-per-campaign**.
- Tightened Support and Payments boundaries: **Support has no money powers**, **Payments has no content powers**, both restated in their sections.
