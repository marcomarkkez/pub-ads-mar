# Publisher — Platform Object Graph & Role Capabilities

Source of truth for "what objects exist, how they connect, and which role can do
what to each." Drives the UI menus (esp. admin/support eagle-eye) and the endpoint list.

Capability legend (role → object):
  👁 inspect (read)   ✏ edit (create/update/delete)   🟢 own (CRUD on own)
  🤫 silent (observe a thread without announcing)   📣 announce (join is visible)
  $ money action      ·  = no access      📝 = action is audit-logged

ROLES: Client · Provider · Collaborator{subroles} · Support · Payments · Admin
{Collaborators are subroles that exist in TWO separate ecosystems — client-side and provider-side.
 Each ecosystem has its OWN subroles (client: publicist, manager, …; provider: installator, manager, …).
 A subrole exists ONLY within its ecosystem; the two collaborator sets are separated from each other
 and from the rest of the platform. "manager" on the client side ≠ "manager" on the provider side.}

================================================================================
1. OBJECT GRAPH (ownership + flow)
================================================================================

           ┌──────────────────────── ACCOUNTS (users table) ────────────────────────┐
           │                                                                          │
   ┌───────────────┐                                                       ┌───────────────────┐
   │  CLIENT acct  │                                                       │  PROVIDER acct     │
   │  (+collabs:   │                                                       │  (+collabs:        │
   │  publicist…)  │                                                       │  installator…)     │
   └───────┬───────┘                                                       └─────────┬─────────┘
           │ owns                                                                     │ owns
           ▼                                                                          ▼
     ┌───────────┐      ┌──────────┐       ┌────────┐       books        ┌────────────────────────┐
     │ CAMPAIGN  │──<───│  ADSET   │──<─────│   AD   │───────────────────▶│        SPACE           │
     │ (billing) │ 1:n  │ (group)  │  1:n   │(=order │   booking ties     │ type, lat/long, price  │
     └─────┬─────┘      └──────────┘        │ for a  │   ad↔space         └───┬───────────┬────────┘
           │ 1:1 summary               ▲    │ space) │                        │ 1:n       │ 1:n
           ▼                           │    └───┬────┘                        ▼           ▼
     ┌───────────┐                     │        │                      ┌───────────┐ ┌──────────────┐
     │  INVOICE  │  rolls up per-ad    │        │ 1:1                  │SPACE_PHOTO│ │SPACE_AVAIL.  │
     │ per CAMP  │  charges across     │        ▼                      └───────────┘ │ +Google/iCal │
     └───────────┘  all adsets/ads ────┘   ┌──────────┐                              │ sync (pref)  │
                                           │ BOOKING  │                              └──────────────┘
                                           │ dates,   │
                  ┌────────────────────────┤ status,  ├───────────────┐
                  ▼ 1:1                     │ price    │               ▼ 1:n
            ┌───────────┐                   └────┬─────┘         ┌────────────┐
            │  PAYMENT  │ $                      │ 1:n           │   PROOF    │ provider uploads
            │ +refund   │──┐                     ▼               │ photo/video│ (video=radio)
            └───────────┘  │ writes        (client flags        │ deadline   │
                           ▼                 mismatch)──────────▶│ status     │
                    ┌─────────────┐                             └─────┬──────┘
                    │ WALLET_ENTRY│ $ (centavos ledger)               │ mismatch →
                    └─────────────┘                                  ▼ auto-creates
                                                              ┌──────────────┐
   CONVERSATION (client↔provider, PII-masked) ──< MESSAGE     │   TICKET     │ polymorphic →
        │  attaches: SPACE (the one of interest/booked)       │ ticketable = │ campaign|adset|
        └─ support can JOIN (📣 announced) · admin (🤫 silent) │ ad|space|…   │ ad|space
                                                               └──────┬───────┘
   SYSTEM_CONFIGURATION (admin/provider-admin tunables)               │ 1:n
   AUDIT_LOG (every staff action: who/what/which object 📝)     ┌────────────┐
                                                               │TICKET_MSG  │ +is_internal
                                                               │(support↔pay)│ (client never sees)
                                                               └────────────┘

{All logs can be seen by admin}

KEY EDGES (for eagle-eye filters — "navigate between nodes"):
  Ad ─┬─ belongs to → Adset → Campaign → Client(+publicist collabs)
      ├─ booked on  → Space → Provider(+installator collabs)
      ├─ has        → Booking → Payment → WalletEntry
      ├─ has        → Proof(s)
      ├─ rolled in  → Invoice (via Campaign)
      └─ referenced by → Ticket(s) → Support/Payments + internal thread → Admin
  So filtering by ANY node (a space, a provider, a support user, a client) reaches all related nodes.

================================================================================
2. ROLE × OBJECT CAPABILITY MATRIX
================================================================================
(rows = object, cols = role; 🟢=own CRUD, 👁=inspect, ✏=edit-any, $=money, 🤫=silent, 📣=announce, ·=none, 📝=logged)

OBJECT            CLIENT  COLLAB(c)  PROVIDER  COLLAB(p)        SUPPORT     PAYMENTS  ADMIN
                                              inst|publ|mgr
Campaign          🟢       publ:🟢    ·         ·               👁 ✏📝       👁        👁🤫 ✏📝
Adset             🟢       publ:🟢    ·         ·               👁 ✏📝       👁        👁🤫 ✏📝
Ad                🟢       publ:🟢    👁(theirs) ·               👁 ✏📝       👁        👁🤫 ✏📝
Space             👁       👁         🟢        ·  | 👁 | 🟢      👁 ✏📝       👁        👁🤫 ✏📝
Space photo/avail 👁       👁         🟢        ·  | 👁 | 🟢      👁 ✏📝       👁        👁🤫 ✏📝
Booking           🟢       publ:🟢    👁✏(theirs)·  | 👁 | ✏     👁 ✏📝       👁        👁🤫 ✏📝
Proof             🟢review inst:create provider:🟢 create|·|review 👁 ✏📝(mism) 👁 (no review) 👁🤫 ✏📝
                  +flag    /flag       (primary)  publ/mgr:review/flag                $hold-on-reject
Payment           👁(own)  mgr:👁     👁(payout) ·  | · | 👁      👁 flag$     🟢 $📝     👁🤫 $📝
Wallet/Refund     👁(own)  mgr:👁     ·         ·               flag only    $ execute  👁🤫 $📝
Invoice           👁(own)  mgr:👁     👁(own)    ·  | · | 👁      👁 ✏📝       👁         👁🤫 ✏📝
Conversation/Msg  🟢(own)  publ:🟢    🟢(own)    ·  |publ:🟢|🟢    📣 join 📝   📣 join    🤫 silent or 📣📝
Ticket            🟢 raise  publ/mgr   🟢 raise   ·  |publ:🟢|🟢   🟢 handle📝  🟢 handle  👁🤫 ✏📝
Internal thread   ·        ·          ·         ·               🟢 w/Pay     🟢 w/Sup   👁🤫 ✏📝
User/account      ·        ·          ·         ·               👁 ✏📝(no$)  ·         🟢 ✏📝
Collaborator      🟢(own)  ·          🟢(own)    ·               👁 ✏📝       ·         👁🤫 ✏📝
RBAC permissions  ·        ·          ·         ·               👁 collab-roles ·       🟢 employees
System config     ·        ·          provider-admin:🟢(own acct) ·          ·         🟢 global
Audit log         ·        ·          ·         ·               👁(writes)    👁(writes) 👁🤫 (read-all)

NOTES ON STAFF POWERS
- SUPPORT  = near-admin: eagle-eye + EDIT every non-money object (spaces, ads, collaborators,
  providers, users, bookings, proofs), joins conversations ANNOUNCED, every action 📝-logged.
  Can flag (not execute) refunds/holds. Can change provider collaborators' roles.
- PAYMENTS = the only role that MOVES money ($): approve/reject payments, refunds, payouts.
  Payments does NOT review proof CONTENT (owner decision 2026-06-20). Instead: a rejected proof
  AUTO-HOLDS the related payment (Payments only sees it held); an accepted proof makes the payment
  releasable, which a Payments person must MANUALLY approve/release. From a held payment, Payments
  can open a Support ticket with the payment attached, and on agreement release. Private internal
  threads with Support; logged. PROOF CONTENT is reviewed/flagged by client + collaborators, with
  Support adjudicating mismatches.
- AUTO-APPROVE: admin System Config = toggle + amount threshold (auto-approve payments under $X only),
  default OFF; larger payments still need manual Payments review.
- ADMIN    = eagle-eye EVERYTHING, SILENT 🤫 by default (no "X joined" message) but may choose
  ANNOUNCE 📣 per ticket; full edit; owns global System config, employee RBAC, reads all audit.
- COLLABORATOR subroles (per account): installator = proofs only; publicist = campaigns/ads/
  chat/tickets, NO money; manager = everything incl. money for that account.

================================================================================
3. WHAT THE GRAPH IMPLIES FOR UI MENUS
================================================================================
CLIENT  : [Map+Search (landing)] [Campaigns→{specs edit, adsets edit, per-ad Book + availability}]
          [Invoices→{PDF download (server-side dompdf)}] [Messages] [Configurations→{Collaborators (account-scoped)}]   (drop Dashboard)
PROVIDER: [Spaces→{view==edit layout, Calendar-preferred + Availabilities accordion, ? help}]
          [Bookings] [Proofs(booking dropdown)] [Messages(space attached)] [Collaborators]
          [Configurations (provider-admin only)]
SUPPORT : [Tickets(objects attached, editable)] [Eagle-eye+edit: Spaces/Providers/Users/Collabs]
          [Collaborator-roles editor] — money actions hidden
PAYMENTS: [Payments review (stats dashboard)] [Held payments → open ticket to Support] [Refunds/Payouts]
          [Internal threads]   (NO proof-content review — money only; auto-approve toggle+threshold via Admin config)
ADMIN   : [Activity (was Oversight)] + per-object eagle-eye menus each with 👁 emoji +
          advanced node filters: [👁 Providers][👁 Spaces][👁 Users][👁 Bookings][👁 Payments]
          [👁 Invoices][👁 Tickets] ; [Employees split: Support/Payments/Provider/Clients]
          [RBAC employees] [Global config]
DASHBOARDS: every NON-client role (provider, payments, support, admin) lands on a stats dashboard with
          live numbers for their end (provider: active spaces / pending bookings / revenue + held/paid/refunded;
          payments: pending payments / held / refunds; support: open tickets / queue depth; admin: users /
          system health). The CLIENT has NO dashboard (lands on Map+Search).
HEADER (all roles): "Welcome, {name}" + role · [?] Help button → new ticket w/ current object (view-only)
