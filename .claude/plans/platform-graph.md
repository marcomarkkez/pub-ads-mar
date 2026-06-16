# Publisher — Platform Object Graph & Role Capabilities

Source of truth for "what objects exist, how they connect, and which role can do
what to each." Drives the UI menus (esp. admin/support eagle-eye) and the endpoint list.

Capability legend (role → object):
  👁 inspect (read)   ✏ edit (create/update/delete)   🟢 own (CRUD on own)
  🤫 silent (observe a thread without announcing)   📣 announce (join is visible)
  $ money action      ·  = no access      📝 = action is audit-logged

ROLES: Client · Provider · Collaborator{installator|publicist|manager} · Support · Payments · Admin

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
Proof             👁+flag  inst:create provider:🟢 create|·|👁    👁 ✏📝       👁(review) 👁🤫 ✏📝
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
- PAYMENTS = the only role that MOVES money ($): approve/reject payments, refunds, payouts,
  review proofs; private internal threads with Support; logged.
- ADMIN    = eagle-eye EVERYTHING, SILENT 🤫 by default (no "X joined" message) but may choose
  ANNOUNCE 📣 per ticket; full edit; owns global System config, employee RBAC, reads all audit.
- COLLABORATOR subroles (per account): installator = proofs only; publicist = campaigns/ads/
  chat/tickets, NO money; manager = everything incl. money for that account.

================================================================================
3. WHAT THE GRAPH IMPLIES FOR UI MENUS
================================================================================
CLIENT  : [Map+Search (landing)] [Campaigns→{specs edit, adsets edit, per-ad Book + availability}]
          [Invoices] [Messages] [Configurations→{Collaborators}]   (drop Dashboard)
PROVIDER: [Spaces→{view==edit layout, Calendar-preferred + Availabilities accordion, ? help}]
          [Bookings] [Proofs(booking dropdown)] [Messages(space attached)] [Collaborators]
          [Configurations (provider-admin only)]
SUPPORT : [Tickets(objects attached, editable)] [Eagle-eye+edit: Spaces/Providers/Users/Collabs]
          [Collaborator-roles editor] — money actions hidden
PAYMENTS: [Payments review] [Proof review] [Refunds/Payouts] [Internal threads]
ADMIN   : [Activity (was Oversight)] + per-object eagle-eye menus each with 👁 emoji +
          advanced node filters: [👁 Providers][👁 Spaces][👁 Users][👁 Bookings][👁 Payments]
          [👁 Invoices][👁 Tickets] ; [Employees split: Support/Payments/Provider/Clients]
          [RBAC employees] [Global config]
HEADER (all roles): "Welcome, {name}" + role · [?] Help button → new ticket w/ current object (view-only)
