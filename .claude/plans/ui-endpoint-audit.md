# UI/Behavior Work — Endpoint Audit & Classification (2026-06-15)

Source: read-only analysis agent over routes/api.php + controllers + Angular components.
See [platform-graph.md](platform-graph.md) for the object/role graph this derives from.
Nav menu = `frontend/src/app/shared/components/sidebar/sidebar.component.ts` (client 28-37, provider 38-46, admin 47-54).
Header/navbar = `frontend/src/app/shared/layouts/main-layout/` (navbar).

## Classification (✅ done this session in **bold**)

### UI-ONLY (endpoint already exists — just wire/restyle)
- **#1 map not rendering on space click** — space-search initMap/invalidateSize; selectSpace only re-centers if map already visible.
- #2 client landing = map-centered 3-pane [menu][map][sidebar] — search endpoint exists (api.php:72).
- #3 campaign spec edit + per-ad **Book** button — CampaignController@update (api.php:48) + Client\BookingController@store (api.php:67) BOTH EXIST; campaign-detail just never calls them.
- #4 move Collaborators under a "Configurations" nav tab — CollaboratorController exists (api.php:75-77).
- #5 remove Dashboard nav item.
- #7 editable ad/adset/campaign — AdController@update(62), AdsetController@update(55), CampaignController@update(48) all exist; no edit forms in UI.
- #9 provider calendar "?" help (ALREADY EXISTS, space-detail.ts:197) + mark calendar preferred + manual Availabilities as closed accordion.
- #10 space view vs edit look different — align space-detail (rich) vs space-form (bare).
- #11 "Back to view" button in space-form (only "Back to Spaces" today).

### DATA-ONLY (seed) — ✅ DONE via MvpDemoSeeder
- **#12 provider bookings** (was 1 → now 5 across providers/statuses)
- **#13/#17 proofs + pending payments** (was 0 proofs → 4; payments to review → 7)
- **#18 support tickets with objects attached** (was 0 → 5: Ad/Space attached, internal Sup↔Pay thread, reference-less)
- **#6 (supporting)** staggered availabilities for "Available in X days" text
- Proof dropdown (#13): provider bookings endpoint exists (api.php:116) → feed dropdown (UI-only).

### NEEDS-BACKEND (new endpoint/column/logic)
- #8 invoice per-ad breakdown — Invoice has single total_amount; no line-items. Add rollup in InvoiceController@show or a line-item table.
- #14 **/conversations/{id} spinner** — ROOT CAUSE: conversation-detail expects `{data:{conversation,messages}}` but MessageController@index (api.php:182) returns bare array → TypeError in next() → infinite spinner. FIX: wrap response `{data:{conversation,messages}}` (or fix frontend to consume bare array + fetch conversation).
- #15 provider-side collaborators — today collaborators are campaign-scoped (Client\CollaboratorController only). Need provider-scoped relation + endpoints + nav.
- #16 provider Configurations menu (account-admin gated) — no provider config endpoint; no provider "owner" concept.
- #19/#21 SUPPORT edit-any-non-money-object — support routes are tickets-only today. Need generic support/admin write controller across spaces/users/collaborators/providers/campaigns (exclude money).
- #20 AUDIT LOG — no table/model/endpoint exists (design.md mentions it, no code). Need audit_logs + write-on-action + read endpoint.
- #22 admin Users role filter — Admin\UserController@index is flat paginate, no ?role=. Add filter (small) + split UI tabs.
- #23 provider sub-roles collaborator:installator/publisher/manager on provider users — no subrole column.
- #24 employee-only RBAC scoping + a Support-facing collaborator-role editor.
- #25 admin per-object oversight ("Activity") endpoints with ADVANCED FILTERS (by space/provider/support-user/client/related node) + per-ticket announced/silent flag. Oversight today = conversations+tickets only, no filters.
- #26 header welcome+name (mostly UI) + Help "?" → ticket-form prefilled with current object as VIEW-ONLY (needs a view-only ticket flag; booking attachable type).

## NEW endpoints (deduped)
1. Fix `GET /conversations/{id}/messages` shape (or add `GET /conversations/{id}`).
2. `?role=` filter on `GET /admin/users`.
3. Provider collaborators CRUD `GET/POST/DELETE /provider/collaborators` (+roles).
4. Provider configurations `GET/PUT /provider/configurations` (account-admin gated).
5. Support eagle-eye read+edit across objects (exclude money).
6. Audit log write + `GET .../audit`.
7. Admin per-object oversight endpoints + filters.
8. Invoice per-ad breakdown on `GET /client/invoices/{id}`.
9. (opt) server-computed next_available_date string.
10. (opt) booking as attachable ticketable type + view-only flag.

## Suggested build order (after seed — DONE)
B1 Quick UI bug-fixes: #1 map, #14 conversation spinner (backend shape), #3 campaign edit+Book, #2 landing.
B2 Provider polish: #9/#10/#11 space screens, #13 proof dropdown, #12 bookings (done-data).
B3 Nav/header: #4 Configurations tab, #5 drop Dashboard, #26 welcome+Help button.
B4 Support powers (backend-heavy): #19/#21 edit-any + #20 audit log.
B5 Admin eagle-eye: #25 per-object + filters, #22 user split, #24 RBAC employees.
B6 Provider collaborators/config: #15/#16/#23.
B7 Invoices: #8 per-ad rollup.
