#!/usr/bin/env python3
"""
Comprehensive API endpoint tester for pub-ads-mar.
Tests all 75 endpoints with fake data, grouped by role.
Usage: python test_all_endpoints.py [--base-url http://localhost:8000]
"""

import argparse
import json
import os
import random
import string
import sys
import tempfile
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode

BASE_URL = "http://localhost:8000/api"

# Counters
passed = 0
failed = 0
errors = []


def rand_str(n=8):
    return ''.join(random.choices(string.ascii_lowercase, k=n))


def rand_email():
    return f"{rand_str(6)}@test.local"


def rand_phone():
    return f"+1{random.randint(1000000000, 9999999999)}"


def rand_lat():
    return round(random.uniform(48.80, 48.90), 7)


def rand_lon():
    return round(random.uniform(2.25, 2.40), 7)


def api(method, path, token=None, data=None, files=None, expect_status=None):
    """Make an API request. Returns (status_code, response_dict)."""
    global passed, failed, errors
    url = f"{BASE_URL}{path}"

    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"

    body = None
    if files:
        # Multipart form data
        boundary = f"----FormBoundary{rand_str(16)}"
        headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
        parts = []
        if data:
            for key, val in data.items():
                parts.append(
                    f"--{boundary}\r\n"
                    f'Content-Disposition: form-data; name="{key}"\r\n\r\n'
                    f"{val}\r\n"
                )
        for key, (filename, content, content_type) in files.items():
            parts.append(
                f"--{boundary}\r\n"
                f'Content-Disposition: form-data; name="{key}"; filename="{filename}"\r\n'
                f"Content-Type: {content_type}\r\n\r\n"
            )
            # We need to handle binary content separately
            parts.append(None)  # placeholder for binary
            parts.append(f"\r\n")
        parts.append(f"--{boundary}--\r\n")

        # Build body bytes
        body_parts = []
        file_values = list(files.values())
        file_idx = 0
        for part in parts:
            if part is None:
                # Insert file binary content
                body_parts.append(file_values[file_idx][1])
                file_idx += 1
            else:
                body_parts.append(part.encode("utf-8"))
        body = b"".join(body_parts)
    elif data:
        headers["Content-Type"] = "application/json"
        body = json.dumps(data).encode("utf-8")

    if method in ("DELETE", "GET") and body is None:
        body = None

    req = Request(url, data=body, headers=headers, method=method)

    label = f"{method} {path}"
    try:
        resp = urlopen(req)
        status = resp.status
        resp_body = resp.read().decode("utf-8")
        try:
            resp_data = json.loads(resp_body) if resp_body else {}
        except json.JSONDecodeError:
            resp_data = {"_raw": resp_body[:200]}
    except HTTPError as e:
        status = e.code
        resp_body = e.read().decode("utf-8")
        try:
            resp_data = json.loads(resp_body) if resp_body else {}
        except json.JSONDecodeError:
            resp_data = {"_raw": resp_body[:200]}
    except URLError as e:
        print(f"  CONN_ERR {label} -> {e.reason}")
        failed += 1
        errors.append(f"CONN_ERR {label}: {e.reason}")
        return None, None

    expected = expect_status or (201 if method == "POST" else 200)
    if isinstance(expected, (list, tuple)):
        ok = status in expected
    else:
        ok = status == expected

    if ok:
        print(f"  PASS {label} -> {status}")
        passed += 1
    else:
        print(f"  FAIL {label} -> {status} (expected {expected})")
        msg = resp_data.get("message", resp_data.get("_raw", ""))
        if msg:
            print(f"       {str(msg)[:120]}")
        failed += 1
        errors.append(f"FAIL {label}: got {status}, expected {expected}")

    return status, resp_data


def login(email, password="password"):
    """Login and return token."""
    _, data = api("POST", "/login", data={"email": email, "password": password}, expect_status=200)
    if data and "token" in data:
        return data["token"]
    return None


def make_test_image():
    """Create a minimal valid PNG file (1x1 red pixel)."""
    import struct
    import zlib

    def chunk(chunk_type, data):
        c = chunk_type + data
        crc = struct.pack(">I", zlib.crc32(c) & 0xFFFFFFFF)
        return struct.pack(">I", len(data)) + c + crc

    signature = b'\x89PNG\r\n\x1a\n'
    ihdr_data = struct.pack(">IIBBBBB", 1, 1, 8, 2, 0, 0, 0)
    ihdr = chunk(b'IHDR', ihdr_data)
    raw_data = b'\x00\xff\x00\x00'  # filter byte + RGB
    idat = chunk(b'IDAT', zlib.compress(raw_data))
    iend = chunk(b'IEND', b'')
    return signature + ihdr + idat + iend


def main():
    global BASE_URL, passed, failed

    parser = argparse.ArgumentParser(description="Test all pub-ads-mar API endpoints")
    parser.add_argument("--base-url", default="http://localhost:8000/api")
    args = parser.parse_args()
    BASE_URL = args.base_url

    print("=" * 60)
    print("PUB-ADS-MAR API ENDPOINT TESTER")
    print("=" * 60)

    test_image = make_test_image()

    # =========================================================
    # 1. PUBLIC: Register & Login
    # =========================================================
    print("\n--- PUBLIC: Auth ---")

    # Register a fresh test user
    test_email = rand_email()
    api("POST", "/register", data={
        "name": f"Test User {rand_str(4)}",
        "email": test_email,
        "password": "password123",
        "password_confirmation": "password123",
        "role": "client",
    })

    # Login with seeded users
    admin_token = login("admin@pubads.test")
    support_token = login("support@pubads.test")
    payments_token = login("payments@pubads.test")
    provider1_token = login("provider1@pubads.test")
    provider2_token = login("provider2@pubads.test")
    client1_token = login("client1@pubads.test")
    client2_token = login("client2@pubads.test")

    if not all([admin_token, support_token, payments_token, provider1_token, client1_token]):
        print("\nFATAL: Could not login with seeded users. Is the server running? Did you run migrate:fresh --seed?")
        sys.exit(1)

    # Test /me and /logout
    api("GET", "/me", token=client1_token)
    # Don't actually logout our working tokens — test with a disposable one
    disposable_token = login(test_email, "password123")
    if disposable_token:
        api("POST", "/logout", token=disposable_token, expect_status=200)

    # =========================================================
    # 2. ADMIN: User CRUD
    # =========================================================
    print("\n--- ADMIN: Users CRM ---")

    api("GET", "/admin/users", token=admin_token)

    _, new_user = api("POST", "/admin/users", token=admin_token, data={
        "name": f"Created User {rand_str(4)}",
        "email": rand_email(),
        "password": "password123",
        "role": "client",
        "phone": rand_phone(),
        "company_name": f"TestCo {rand_str(3)}",
    })
    new_user_id = new_user.get("id") if new_user else None

    if new_user_id:
        api("GET", f"/admin/users/{new_user_id}", token=admin_token)
        api("PUT", f"/admin/users/{new_user_id}", token=admin_token, data={
            "name": "Updated Name",
            "is_active": False,
        }, expect_status=200)
        api("DELETE", f"/admin/users/{new_user_id}", token=admin_token)

    # Test role enforcement: client can't access admin routes
    api("GET", "/admin/users", token=client1_token, expect_status=403)

    # =========================================================
    # 3. CLIENT: Campaigns
    # =========================================================
    print("\n--- CLIENT: Campaigns ---")

    api("GET", "/client/campaigns", token=client1_token)

    _, campaign = api("POST", "/client/campaigns", token=client1_token, data={
        "name": f"Test Campaign {rand_str(4)}",
        "description": "A test campaign for endpoint testing",
        "status": "draft",
        "start_date": "2026-04-01",
        "end_date": "2026-05-01",
        "budget": 3000.00,
    })
    camp_id = campaign.get("id") if campaign else None

    if camp_id:
        api("GET", f"/client/campaigns/{camp_id}", token=client1_token)
        api("PUT", f"/client/campaigns/{camp_id}", token=client1_token, data={
            "name": "Updated Campaign Name",
            "budget": 5000.00,
        }, expect_status=200)

    # =========================================================
    # 4. CLIENT: Adsets
    # =========================================================
    print("\n--- CLIENT: Adsets ---")

    adset_id = None
    if camp_id:
        api("GET", f"/client/campaigns/{camp_id}/adsets", token=client1_token)

        _, adset = api("POST", f"/client/campaigns/{camp_id}/adsets", token=client1_token, data={
            "name": f"Test Adset {rand_str(4)}",
            "latitude": rand_lat(),
            "longitude": rand_lon(),
            "location_name": "Test Location, Paris",
            "radius_km": 3.5,
        })
        adset_id = adset.get("id") if adset else None

        if adset_id:
            api("GET", f"/client/campaigns/{camp_id}/adsets/{adset_id}", token=client1_token)
            api("PUT", f"/client/campaigns/{camp_id}/adsets/{adset_id}", token=client1_token, data={
                "name": "Updated Adset Name",
                "radius_km": 5.0,
            }, expect_status=200)

    # =========================================================
    # 5. CLIENT: Ads (with file upload)
    # =========================================================
    print("\n--- CLIENT: Ads ---")

    ad_id = None
    space_id_for_ad = None
    if camp_id and adset_id:
        api("GET", f"/client/campaigns/{camp_id}/adsets/{adset_id}/ads", token=client1_token)

        # Get a space to link the ad to (space 1 belongs to provider1)
        _, spaces_data = api("GET", "/client/spaces/search?latitude=48.87&longitude=2.33&radius_km=10", token=client1_token)

        # Space search returns paginated response with 'data' key
        if spaces_data and "data" in spaces_data and len(spaces_data["data"]) > 0:
            space_id_for_ad = spaces_data["data"][0].get("id")

        # Create ad with file upload
        ad_data = {
            "name": f"Test Ad {rand_str(4)}",
            "media_type": "image",
        }
        if space_id_for_ad:
            ad_data["space_id"] = str(space_id_for_ad)
            ad_data["price"] = "150.00"
            ad_data["pricing_unit"] = "day"
            ad_data["start_date"] = "2026-04-01"
            ad_data["end_date"] = "2026-04-30"

        _, ad = api("POST", f"/client/campaigns/{camp_id}/adsets/{adset_id}/ads",
                     token=client1_token,
                     data=ad_data,
                     files={"file": ("test_ad.png", test_image, "image/png")})
        ad_id = ad.get("id") if ad else None

        if ad_id:
            api("GET", f"/client/campaigns/{camp_id}/adsets/{adset_id}/ads/{ad_id}", token=client1_token)
            api("PUT", f"/client/campaigns/{camp_id}/adsets/{adset_id}/ads/{ad_id}", token=client1_token, data={
                "name": "Updated Ad Name",
                "status": "pending_approval",
            }, expect_status=200)

    # =========================================================
    # 6. CLIENT: Space Search
    # =========================================================
    print("\n--- CLIENT: Space Search ---")

    api("GET", "/client/spaces/search?latitude=48.87&longitude=2.33&radius_km=10", token=client1_token)
    api("GET", "/client/spaces/search?latitude=48.87&longitude=2.33&radius_km=1", token=client1_token)

    # =========================================================
    # 7. CLIENT: Bookings
    # =========================================================
    print("\n--- CLIENT: Bookings ---")

    api("GET", "/client/bookings", token=client1_token)

    booking_id = None
    if space_id_for_ad:
        book_data = {
            "space_id": space_id_for_ad,
            "start_date": "2026-05-01",
            "end_date": "2026-05-15",
        }
        if ad_id:
            book_data["ad_id"] = ad_id
        if adset_id:
            book_data["adset_id"] = adset_id

        _, booking = api("POST", "/client/bookings", token=client1_token, data=book_data)
        booking_id = booking.get("id") if booking else None

        if booking_id:
            api("GET", f"/client/bookings/{booking_id}", token=client1_token)

    # =========================================================
    # 8. CLIENT: Collaborators
    # =========================================================
    print("\n--- CLIENT: Collaborators ---")

    # design.json §3 — ACCOUNT-scoped, not campaign-nested, and the subroles are
    # publicist|manager (proof_uploader was never one of them).
    api("GET", "/client/collaborators", token=client1_token)
    _, collab = api("POST", "/client/collaborators", token=client1_token, data={
        "email": rand_email(),
        "role": "publicist",
    })
    collab_id = collab.get("id") if collab else None

    if collab_id:
        api("DELETE", f"/client/collaborators/{collab_id}", token=client1_token)

    # =========================================================
    # 9. CLIENT: Invoices
    # =========================================================
    print("\n--- CLIENT: Invoices ---")

    api("GET", "/client/invoices", token=client1_token)
    # No invoices exist yet, just check endpoint returns 200

    # =========================================================
    # 10. PROVIDER: Spaces CRUD
    # =========================================================
    print("\n--- PROVIDER: Spaces ---")

    api("GET", "/provider/spaces", token=provider1_token)

    _, space = api("POST", "/provider/spaces", token=provider1_token, data={
        "name": f"Test Space {rand_str(4)}",
        "type": "billboard",
        "latitude": rand_lat(),
        "longitude": rand_lon(),
        "price_per_day": 100.00,
        "price_per_month": 2500.00,
        "pricing_unit": "day",
        "description": "A test billboard for endpoint testing",
        "location_text": "Test Street, Paris",
        "width": 3.0,
        "height": 2.0,
    })
    prov_space_id = space.get("id") if space else None

    if prov_space_id:
        api("GET", f"/provider/spaces/{prov_space_id}", token=provider1_token)
        api("PUT", f"/provider/spaces/{prov_space_id}", token=provider1_token, data={
            "name": "Updated Test Space",
            "price_per_day": 120.00,
            "is_active": True,
        }, expect_status=200)

    # =========================================================
    # 11. PROVIDER: Space Photos
    # =========================================================
    print("\n--- PROVIDER: Space Photos ---")

    photo_id = None
    if prov_space_id:
        _, photo = api("POST", f"/provider/spaces/{prov_space_id}/photos",
                        token=provider1_token,
                        data={"is_primary": "1", "sort_order": "1"},
                        files={"photo": ("space_photo.png", test_image, "image/png")})
        photo_id = photo.get("id") if photo else None

        if photo_id:
            api("DELETE", f"/provider/spaces/{prov_space_id}/photos/{photo_id}", token=provider1_token)

    # =========================================================
    # 12. PROVIDER: Space Availabilities
    # =========================================================
    print("\n--- PROVIDER: Availabilities ---")

    avail_id = None
    if prov_space_id:
        api("GET", f"/provider/spaces/{prov_space_id}/availabilities", token=provider1_token)

        _, avail = api("POST", f"/provider/spaces/{prov_space_id}/availabilities", token=provider1_token, data={
            "start_date": "2026-05-01",
            "end_date": "2026-08-31",
            "status": "available",
        })
        avail_id = avail.get("id") if avail else None

        if avail_id:
            api("DELETE", f"/provider/spaces/{prov_space_id}/availabilities/{avail_id}", token=provider1_token)

    # =========================================================
    # 13. PROVIDER: iCal Sync
    # =========================================================
    print("\n--- PROVIDER: iCal ---")

    if prov_space_id:
        # sync-ical should fail (no ical_url set)
        api("POST", f"/provider/spaces/{prov_space_id}/sync-ical", token=provider1_token, expect_status=422)

        # import-ical with a minimal .ics file
        ics_content = (
            "BEGIN:VCALENDAR\r\n"
            "VERSION:2.0\r\n"
            "BEGIN:VEVENT\r\n"
            "DTSTART:20260601\r\n"
            "DTEND:20260602\r\n"
            "SUMMARY:Blocked\r\n"
            "END:VEVENT\r\n"
            "END:VCALENDAR\r\n"
        ).encode("utf-8")

        api("POST", f"/provider/spaces/{prov_space_id}/import-ical",
            token=provider1_token,
            data={},
            files={"file": ("calendar.ics", ics_content, "text/calendar")},
            expect_status=200)

    # =========================================================
    # 14. PROVIDER: Bookings
    # =========================================================
    print("\n--- PROVIDER: Bookings ---")

    api("GET", "/provider/bookings", token=provider1_token)

    # Get a booking that belongs to provider1's spaces to update
    # From seeder: booking1 is on space1 (provider1), status=active
    _, prov_bookings = api("GET", "/provider/bookings", token=provider1_token)
    prov_booking_id = None
    if prov_bookings and isinstance(prov_bookings, list) and len(prov_bookings) > 0:
        prov_booking_id = prov_bookings[0].get("id")

    if prov_booking_id:
        api("PUT", f"/provider/bookings/{prov_booking_id}", token=provider1_token, data={
            "status": "waiting_proof",
        }, expect_status=200)

    # =========================================================
    # 15. PROVIDER: Dashboard
    # =========================================================
    print("\n--- PROVIDER: Dashboard ---")

    api("GET", "/provider/dashboard", token=provider1_token)

    # =========================================================
    # 16. PROVIDER: Proofs
    # =========================================================
    print("\n--- PROVIDER: Proofs ---")

    api("GET", "/provider/proofs", token=provider1_token)

    proof_id = None
    # Find an ad assigned to provider1 and a booking to create a proof for
    # From seeder: ad1 (provider1, space1), booking1 (space1)
    if prov_booking_id:
        # Get the ad_id from the booking
        _, booking_detail = api("GET", f"/provider/bookings", token=provider1_token)
        seeded_ad_id = None
        seeded_booking_id = None
        if booking_detail and isinstance(booking_detail, list):
            for b in booking_detail:
                if b.get("ad") and b["ad"].get("provider_user_id"):
                    seeded_ad_id = b["ad"]["id"]
                    seeded_booking_id = b["id"]
                    break

        if seeded_ad_id and seeded_booking_id:
            _, proof = api("POST", "/provider/proofs",
                           token=provider1_token,
                           data={
                               "ad_id": str(seeded_ad_id),
                               "booking_id": str(seeded_booking_id),
                               "media_type": "image",
                               "notes": "Proof photo showing billboard is up",
                           },
                           files={"file": ("proof.png", test_image, "image/png")})
            proof_id = proof.get("id") if proof else None

            if proof_id:
                api("GET", f"/provider/proofs/{proof_id}", token=provider1_token)

    # =========================================================
    # 17. SUPPORT: Tickets
    # =========================================================
    print("\n--- SUPPORT: Tickets ---")

    # First, create a ticket as a client (shared route)
    ticket_id = None
    if camp_id:
        _, ticket = api("POST", "/tickets", token=client1_token, data={
            "ticketable_type": "campaign",
            "ticketable_id": camp_id,
            "subject": f"Test ticket {rand_str(4)}",
            "description": "Something is wrong with my campaign",
            "priority": "high",
        })
        ticket_id = ticket.get("id") if ticket else None

    # Support agent: list, show, update, reply
    api("GET", "/support/tickets", token=support_token)

    if ticket_id:
        api("GET", f"/support/tickets/{ticket_id}", token=support_token)
        api("PUT", f"/support/tickets/{ticket_id}", token=support_token, data={
            "status": "in_progress",
            "assigned_to_user_id": 2,  # support user
        }, expect_status=200)
        api("POST", f"/support/tickets/{ticket_id}/reply", token=support_token, data={
            "body": "We are looking into this issue.",
            "is_internal": False,
        })
        # Internal note
        api("POST", f"/support/tickets/{ticket_id}/reply", token=support_token, data={
            "body": "Internal: escalate to payments team",
            "is_internal": True,
        })

    # Role enforcement: client can't access support routes
    api("GET", "/support/tickets", token=client1_token, expect_status=403)

    # =========================================================
    # 18. SHARED: Tickets (any authenticated user)
    # =========================================================
    print("\n--- SHARED: Tickets ---")

    api("GET", "/tickets", token=client1_token)

    if ticket_id:
        api("GET", f"/tickets/{ticket_id}", token=client1_token)
        api("POST", f"/tickets/{ticket_id}/reply", token=client1_token, data={
            "body": "Thanks for looking into it!",
        })

    # Provider creates a ticket too
    if adset_id:
        api("POST", "/tickets", token=provider1_token, data={
            "ticketable_type": "adset",
            "ticketable_id": adset_id,
            "subject": "Provider concern about adset",
            "description": "The adset targeting seems off",
            "priority": "medium",
        })

    # =========================================================
    # 19. PAYMENTS: Payments
    # =========================================================
    print("\n--- PAYMENTS: Payments ---")

    api("GET", "/payments/payments", token=payments_token)

    # Find a payment to approve/reject
    _, pay_list = api("GET", "/payments/payments", token=payments_token)
    payment_id = None
    if pay_list and "data" in pay_list and len(pay_list["data"]) > 0:
        # Find a pending one if possible
        for p in pay_list["data"]:
            if p.get("status") == "pending":
                payment_id = p["id"]
                break
        if not payment_id:
            payment_id = pay_list["data"][0]["id"]

    if payment_id:
        api("GET", f"/payments/payments/{payment_id}", token=payments_token)
        api("POST", f"/payments/payments/{payment_id}/approve", token=payments_token, expect_status=200)

    # Role enforcement: provider can't access payments routes
    api("GET", "/payments/payments", token=provider1_token, expect_status=403)

    # =========================================================
    # 20. PAYMENTS: no proof-CONTENT review (B9)
    # =========================================================
    print("\n--- PAYMENTS: proof content review is GONE (B9) ---")

    # design.json §7/§17: the proof verdict is the CLIENT's; Payments only reacts
    # to it with money. These routes were removed on 2026-08-02 — assert they
    # stay gone, so nobody reintroduces the violation by accident.
    api("GET", "/payments/proofs", token=payments_token, expect_status=404)

    if proof_id:
        api("POST", f"/payments/proofs/{proof_id}/approve", token=payments_token, expect_status=404)
        api("POST", f"/payments/proofs/{proof_id}/reject", token=payments_token, expect_status=404)

    # The verdict that DOES exist — POST /client/proofs/{proof}/accept|reject —
    # is owner-scoped, and the proof above belongs to whichever client booked
    # provider1's space, so it is not assertable from here. Its coverage lives in
    # the PHPUnit feature suite, not in this smoke script.

    # =========================================================
    # 21. CONVERSATIONS
    # =========================================================
    print("\n--- SHARED: Conversations ---")

    api("GET", "/conversations", token=client1_token)

    # Create a conversation (client to provider about a space)
    # Space 1 belongs to provider1 (user_id 4)
    _, convo = api("POST", "/conversations", token=client1_token, data={
        "space_id": 1,
        "provider_user_id": 4,
    })
    convo_id = convo.get("id") if convo else None

    if convo_id:
        api("GET", f"/conversations/{convo_id}/messages", token=client1_token)
        api("POST", f"/conversations/{convo_id}/messages", token=client1_token, data={
            "body": "Hi, I'm interested in renting this space for my campaign.",
        })
        # Provider replies
        api("POST", f"/conversations/{convo_id}/messages", token=provider1_token, data={
            "body": "Sure! The space is available. When do you need it?",
        })

    # Provider sees conversations too
    api("GET", "/conversations", token=provider1_token)

    # =========================================================
    # 22. ADMIN: Permissions API (RBAC)
    # =========================================================
    print("\n--- ADMIN: Permissions ---")

    # List all permissions
    api("GET", "/admin/permissions", token=admin_token)

    # Get permissions for a specific role
    _, client_perms = api("GET", "/admin/permissions/client", token=admin_token)
    if client_perms:
        assert client_perms.get("role") == "client", "Should return client role"

    # Update permissions for a role (bulk replace)
    api("PUT", "/admin/permissions/client", token=admin_token, data={
        "permissions": {
            "campaigns": ["create", "read", "update", "delete"],
            "adsets": ["create", "read", "update", "delete"],
            "ads": ["create", "read", "update", "delete"],
            "bookings": ["create", "read", "update"],
            "spaces": ["read"],
            "conversations": ["create", "read"],
            "tickets": ["create", "read"],
            "collaborators": ["create", "read", "delete"],
            "invoices": ["read"],
        },
    }, expect_status=200)

    # Update single resource permissions
    api("PATCH", "/admin/permissions/client/invoices", token=admin_token, data={
        "actions": ["read"],
    }, expect_status=200)

    # Filter by role
    api("GET", "/admin/permissions?role=provider", token=admin_token)

    # Role enforcement: client can't access admin permissions
    api("GET", "/admin/permissions", token=client1_token, expect_status=403)

    # Test permission enforcement: temporarily remove client bookings.create
    api("PATCH", "/admin/permissions/client/bookings", token=admin_token, data={
        "actions": ["read", "update"],  # removed 'create'
    }, expect_status=200)

    # Client should now get 403 when trying to create a booking
    api("POST", "/client/bookings", token=client1_token, data={
        "space_id": 1, "start_date": "2026-09-01", "end_date": "2026-09-10",
    }, expect_status=403)

    # Restore bookings.create permission
    api("PATCH", "/admin/permissions/client/bookings", token=admin_token, data={
        "actions": ["create", "read", "update"],
    }, expect_status=200)

    # =========================================================
    # 23. CLEANUP: Delete ad, adset, campaign
    # =========================================================
    print("\n--- CLEANUP ---")

    if ad_id and camp_id and adset_id:
        api("DELETE", f"/client/campaigns/{camp_id}/adsets/{adset_id}/ads/{ad_id}", token=client1_token)

    if adset_id and camp_id:
        api("DELETE", f"/client/campaigns/{camp_id}/adsets/{adset_id}", token=client1_token)

    # Cancel the booking (client can only set to cancelled)
    if booking_id:
        api("PUT", f"/client/bookings/{booking_id}", token=client1_token, data={
            "status": "cancelled",
        }, expect_status=200)

    if prov_space_id:
        api("DELETE", f"/provider/spaces/{prov_space_id}", token=provider1_token)

    if camp_id:
        api("DELETE", f"/client/campaigns/{camp_id}", token=client1_token)

    # =========================================================
    # SUMMARY
    # =========================================================
    print("\n" + "=" * 60)
    print(f"RESULTS: {passed} passed, {failed} failed, {passed + failed} total")
    print("=" * 60)

    if errors:
        print("\nFailed tests:")
        for e in errors:
            print(f"  - {e}")

    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
