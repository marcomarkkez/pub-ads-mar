This document is the main design reference for all the app (API and headless frontend)

Roles:
    Clients: People that want one or multiple spaces, and will pay per campaigns
    Providers: They own spaces so they can put price per day or month, or any time span
    Admins: They check messages between Providers and Clients, check possible problems reported by users
    Support: Support agents role check tickets and problems reported by users in each adset, ad (space) or whole campaigns
    Payments: Payments personel should check and approve payments for campaigns with proofs of display (images or videos) and stop payments to providers that don't complain with the requisites or proofs. 

Campaign: Is a unit where clients can put a budget, ad one or more adsets with one or more ads, this is the top level of the whole system for the users, campaigns must be listed, with columns like value of the whole campaign, the invoice of that campaign and other relevant data.

Adset: Is the group of ads, in this middle level adsets can be groups of ads for one place in particular, let's say, multiple image ads, that are being displayed in displays in the streets or maybe in taxis, for example, so the adset is a group where multiple ads from multiple providers can be, is not other thing than a "tag", it has no other purpose than order and group ads.

Ad: Is the basic unit of the publicity and can be audios (for radio), can be images (for billboards), can be videos (for big scren billboards) and other types of advertisment, the provider is responsibsle for approve the files uploaded, in order to be displayed in different places or printed or maybe played in radio stations, every ad has a cost and can be from different providers, but the invoice is printed in campaign level, each ad has its own price and time span for the place, and even diferent provider.

The backend should be Laravel and it will be a service for a headless website, and possible an app in the near future. Create fake endpoint requests, to test every endpoint with fake data, and create a script to send the fake tests or erase everything, this script should be called by terminal like "python insert_data.py", with options like "messaging", or "support ticket", and an option like "erase-all-data" to clear the whole database.

Ticket system / Support: The chat with support can be called from any ad or adset or campaign, with a button and it will save in the chat the reference for that specific item, with provider information, budget, payments and everything an agent could need.

Providers UI: The providers can insert, update, delete and pause all the spaces they want, those spaces can be audio, video, gif's, images, in the basic format of that kind of media for publicity, because they own the publicity spaces, they have to insert the name, the location in text, and the location in lat-long format, the google maps api should be available to use that, and one or several photos of the space. They should set the price by day, month or any time span, for the availability they can setup any calendar like Outlook or Google Calendar, import a cal file or simply connect to a one public calendar, for this porpuse should be a little question mark button to help with this topic, they should take photos as proof in less than 5 days to prove that the ad is being displayed, this is per ad. Te providers can see a list of all the ads they need to add proofs with photos of the ad of the client (video or image) being displayed, in the case of audios for radio stations or any other possible media source the proof should be a video.

Clients UI: They want pubicity spaces to put their publicity campaigns, with images, audios, videos or any normal media for ads, and they will pay per day or month or any time span they use, they need to see the proof that the ad is being displayed in the place they choose (the ad), if the proof is not added, the ad can be cancelled and the money will not be send to the provider. They should be able to search spaces in a map (Google maps api) select it from the point in the map and check the information of the space in the right bar (50% wide) then fill the fields with information of the time span requested, it should give information of availability by turning green or red depending if the space is available in that date range, if it's available it should be sended to approval from the provider and once approved the provider have 5 days to upload the proof. Clients can add employees to upload proofs only by adding them by email in a collaborators menu, the collaborators should be able to see the list of proofs they need to fill in les than 5 days.

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