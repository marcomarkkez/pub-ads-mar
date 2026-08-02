#!/usr/bin/env python3
"""
Fake data generator for pub-ads-mar database.
Inserts realistic test data via the API, exercising all endpoints.

Usage:
  python insert_data.py [--base-url http://localhost:8000/api] [--scale small|medium|large]

Scale presets:
  small  — 5 providers, 5 clients, ~20 spaces, ~10 campaigns
  medium — 15 providers, 15 clients, ~60 spaces, ~30 campaigns (default)
  large  — 40 providers, 40 clients, ~160 spaces, ~80 campaigns
"""

import argparse
import json
import random
import string
import struct
import sys
import zlib
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError

BASE_URL = "http://localhost:8000/api"

# ── Helpers ──────────────────────────────────────────────────

FIRST_NAMES = [
    "Jean", "Marie", "Pierre", "Sophie", "Carlos", "Ana", "Luc", "Emma",
    "Thomas", "Claire", "Hugo", "Léa", "Antoine", "Camille", "Nicolas",
    "Julie", "Maxime", "Isabelle", "Julien", "Charlotte", "Alexandre",
    "Pauline", "Romain", "Céline", "Mathieu", "Nathalie", "David", "Laura",
    "Vincent", "Sarah", "Étienne", "Aurélie", "Olivier", "Margaux", "Fabien",
    "Delphine", "Raphaël", "Manon", "Sébastien", "Chloé",
]

LAST_NAMES = [
    "Dupont", "Martin", "Bernard", "Dubois", "Moreau", "Laurent", "Simon",
    "Michel", "Lefebvre", "Leroy", "Garcia", "David", "Bertrand", "Roux",
    "Fontaine", "Blanc", "Guérin", "Boyer", "Gauthier", "Perrin", "Robin",
    "Masson", "Clément", "Morin", "Chevalier", "Mercier", "Denis", "Duval",
    "Renaud", "Picard", "Girard", "André", "Lemaire", "Lambert", "Bonnet",
    "François", "Martinez", "Legrand", "Moulin", "Dumas",
]

COMPANY_SUFFIXES = [
    "Media", "Publicité", "Affichage", "Communication", "Marketing",
    "Digital", "Advertising", "Display", "Signalétique", "Vision",
]

PARIS_LOCATIONS = [
    ("Champs-Élysées, Paris 8e", 48.8698, 2.3076),
    ("Place de la République, Paris 3e", 48.8675, 2.3637),
    ("Gare du Nord, Paris 10e", 48.8809, 2.3553),
    ("Montmartre, Paris 18e", 48.8867, 2.3431),
    ("Saint-Lazare, Paris 8e", 48.8760, 2.3250),
    ("Bastille, Paris 11e", 48.8533, 2.3693),
    ("Opéra, Paris 9e", 48.8713, 2.3318),
    ("Nation, Paris 12e", 48.8485, 2.3960),
    ("Châtelet, Paris 1er", 48.8581, 2.3472),
    ("Belleville, Paris 20e", 48.8718, 2.3765),
    ("Pigalle, Paris 18e", 48.8822, 2.3375),
    ("Les Halles, Paris 1er", 48.8620, 2.3448),
    ("Place d'Italie, Paris 13e", 48.8311, 2.3558),
    ("Porte de Versailles, Paris 15e", 48.8322, 2.2876),
    ("La Défense, Puteaux", 48.8918, 2.2382),
    ("Bercy, Paris 12e", 48.8396, 2.3829),
    ("Invalides, Paris 7e", 48.8567, 2.3126),
    ("Trocadéro, Paris 16e", 48.8629, 2.2868),
    ("Oberkampf, Paris 11e", 48.8647, 2.3757),
    ("Canal Saint-Martin, Paris 10e", 48.8716, 2.3645),
    ("Ménilmontant, Paris 20e", 48.8660, 2.3880),
    ("Alésia, Paris 14e", 48.8280, 2.3269),
    ("Batignolles, Paris 17e", 48.8876, 2.3195),
    ("Buttes-Chaumont, Paris 19e", 48.8810, 2.3830),
    ("Père-Lachaise, Paris 20e", 48.8618, 2.3938),
    ("Denfert-Rochereau, Paris 14e", 48.8338, 2.3325),
    ("Madeleine, Paris 8e", 48.8700, 2.3246),
    ("Sentier, Paris 2e", 48.8684, 2.3481),
    ("Arts et Métiers, Paris 3e", 48.8656, 2.3564),
    ("Gambetta, Paris 20e", 48.8650, 2.3988),
]

SPACE_TYPES = ["billboard", "digital_screen", "bus_shelter", "metro_panel", "wall_mural", "taxi_top"]

SPACE_NAMES_TPL = [
    "{type_label} {location}", "Panneau {location}", "Écran {location}",
    "Affichage {location}", "Display {location}", "Espace pub {location}",
]

CAMPAIGN_THEMES = [
    "Summer Launch", "Brand Awareness Q{q}", "Holiday Promo",
    "Product Launch", "Store Opening", "Flash Sale",
    "Seasonal Campaign", "Event Promotion", "Rebranding",
    "New Collection", "Back to School", "Winter Special",
    "Spring Drive", "Festival Tie-in", "Weekend Blitz",
]

AD_NAMES_TPL = [
    "Banner {theme}", "Video Spot {theme}", "Poster {theme}",
    "Display Ad {theme}", "Creative {n}", "Visual {theme}",
]

TICKET_SUBJECTS = [
    "Billing inquiry for campaign", "Space availability question",
    "Proof not yet uploaded", "Ad rejected — need explanation",
    "Wrong dimensions on space listing", "Need invoice copy",
    "Booking dates conflict", "Payment not reflected",
    "Request to extend campaign", "Technical issue with upload",
]

MESSAGE_BODIES = [
    "Hi, I'm interested in renting this space for my campaign.",
    "Is this space available for the dates I need?",
    "What are the exact dimensions of the billboard?",
    "Can I get a discount for a long-term booking?",
    "Sure! The space is available. When do you need it?",
    "We can offer a 10% discount for bookings over 30 days.",
    "The dimensions are listed in the space details.",
    "Let me check availability and get back to you.",
    "I'd like to proceed with the booking.",
    "Great, I'll confirm the reservation now.",
]


def rand_str(n=8):
    return ''.join(random.choices(string.ascii_lowercase, k=n))


def rand_phone():
    return f"+33{random.randint(600000000, 699999999)}"


def jitter(lat, lon, km=0.5):
    d = km / 111.0
    return (
        round(lat + random.uniform(-d, d), 7),
        round(lon + random.uniform(-d, d), 7),
    )


def make_test_image():
    """Minimal valid PNG (1x1 red pixel)."""
    def chunk(ct, data):
        c = ct + data
        crc = struct.pack(">I", zlib.crc32(c) & 0xFFFFFFFF)
        return struct.pack(">I", len(data)) + c + crc

    sig = b'\x89PNG\r\n\x1a\n'
    ihdr = chunk(b'IHDR', struct.pack(">IIBBBBB", 1, 1, 8, 2, 0, 0, 0))
    idat = chunk(b'IDAT', zlib.compress(b'\x00\xff\x00\x00'))
    iend = chunk(b'IEND', b'')
    return sig + ihdr + idat + iend


TEST_IMAGE = make_test_image()


def api(method, path, token=None, data=None, files=None):
    """Make API request. Returns (status, response_dict) or (None, None) on connection error."""
    url = f"{BASE_URL}{path}"
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"

    body = None
    if files:
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
            parts.append(None)
            parts.append(f"\r\n")
        parts.append(f"--{boundary}--\r\n")

        body_parts = []
        file_values = list(files.values())
        file_idx = 0
        for part in parts:
            if part is None:
                body_parts.append(file_values[file_idx][1])
                file_idx += 1
            else:
                body_parts.append(part.encode("utf-8"))
        body = b"".join(body_parts)
    elif data:
        headers["Content-Type"] = "application/json"
        body = json.dumps(data).encode("utf-8")

    req = Request(url, data=body, headers=headers, method=method)
    try:
        resp = urlopen(req)
        status = resp.status
        raw = resp.read().decode("utf-8")
    except HTTPError as e:
        status = e.code
        raw = e.read().decode("utf-8")
    except URLError as e:
        print(f"  CONNECTION ERROR: {method} {path} -> {e.reason}")
        return None, None

    try:
        return status, json.loads(raw) if raw else {}
    except json.JSONDecodeError:
        return status, {}


def login(email, password="password"):
    _, data = api("POST", "/login", data={"email": email, "password": password})
    if data and "token" in data:
        return data["token"]
    return None


def register_and_login(name, email, role, password="password"):
    """Register a new user and return their token."""
    status, data = api("POST", "/register", data={
        "name": name,
        "email": email,
        "password": password,
        "password_confirmation": password,
        "role": role,
        "phone": rand_phone(),
        "company_name": f"{name.split()[0]} {random.choice(COMPANY_SUFFIXES)}",
        "address": random.choice(PARIS_LOCATIONS)[0],
    })
    if data and "token" in data:
        return data["token"]
    return None


# ── Main ─────────────────────────────────────────────────────

def main():
    global BASE_URL

    parser = argparse.ArgumentParser(description="Generate fake data for pub-ads-mar")
    parser.add_argument("--base-url", default="http://localhost:8000/api")
    parser.add_argument("--scale", choices=["small", "medium", "large"], default="medium")
    args = parser.parse_args()
    BASE_URL = args.base_url

    scales = {
        "small":  {"providers": 5,  "clients": 5,  "spaces_per": 4,  "campaigns_per": 2},
        "medium": {"providers": 15, "clients": 15, "spaces_per": 4,  "campaigns_per": 2},
        "large":  {"providers": 40, "clients": 40, "spaces_per": 4,  "campaigns_per": 2},
    }
    s = scales[args.scale]

    print("=" * 60)
    print(f"FAKE DATA GENERATOR — scale: {args.scale}")
    print(f"  Providers: {s['providers']}, Clients: {s['clients']}")
    print(f"  Spaces/provider: {s['spaces_per']}, Campaigns/client: {s['campaigns_per']}")
    print("=" * 60)

    # Login as admin (seeded)
    print("\n[1/9] Logging in as admin...")
    admin_token = login("admin@pubads.test")
    if not admin_token:
        print("FATAL: Cannot login as admin. Run: php artisan migrate:fresh --seed")
        sys.exit(1)
    print("  OK")

    # ── Register providers ───────────────────────────────────
    print(f"\n[2/9] Registering {s['providers']} providers...")
    provider_tokens = []
    provider_ids = []
    used_names = set()

    for i in range(s["providers"]):
        fn = random.choice(FIRST_NAMES)
        ln = random.choice(LAST_NAMES)
        while f"{fn}{ln}" in used_names:
            fn = random.choice(FIRST_NAMES)
            ln = random.choice(LAST_NAMES)
        used_names.add(f"{fn}{ln}")

        name = f"{fn} {ln}"
        email = f"provider.{fn.lower()}.{ln.lower()}{i}@test.local"
        token = register_and_login(name, email, "provider")
        if token:
            # Get user ID from /me
            _, me = api("GET", "/me", token=token)
            provider_tokens.append(token)
            provider_ids.append(me.get("id") if me else None)
            print(f"  + {name}")
        else:
            print(f"  ! Failed: {name}")

    print(f"  Registered {len(provider_tokens)} providers")

    # ── Register clients ─────────────────────────────────────
    print(f"\n[3/9] Registering {s['clients']} clients...")
    client_tokens = []
    client_ids = []

    for i in range(s["clients"]):
        fn = random.choice(FIRST_NAMES)
        ln = random.choice(LAST_NAMES)
        while f"{fn}{ln}" in used_names:
            fn = random.choice(FIRST_NAMES)
            ln = random.choice(LAST_NAMES)
        used_names.add(f"{fn}{ln}")

        name = f"{fn} {ln}"
        email = f"client.{fn.lower()}.{ln.lower()}{i}@test.local"
        token = register_and_login(name, email, "client")
        if token:
            _, me = api("GET", "/me", token=token)
            client_tokens.append(token)
            client_ids.append(me.get("id") if me else None)
            print(f"  + {name}")
        else:
            print(f"  ! Failed: {name}")

    print(f"  Registered {len(client_tokens)} clients")

    # ── Create spaces for each provider ──────────────────────
    print(f"\n[4/9] Creating spaces ({s['spaces_per']} per provider)...")
    all_spaces = []  # (space_id, provider_token, provider_id)

    for idx, (ptoken, pid) in enumerate(zip(provider_tokens, provider_ids)):
        locations = random.sample(PARIS_LOCATIONS, min(s["spaces_per"], len(PARIS_LOCATIONS)))
        for loc_name, lat, lon in locations:
            stype = random.choice(SPACE_TYPES)
            type_label = stype.replace("_", " ").title()
            name_tpl = random.choice(SPACE_NAMES_TPL)
            space_name = name_tpl.format(type_label=type_label, location=loc_name)

            jlat, jlon = jitter(lat, lon)
            ppd = round(random.uniform(50, 400), 2)
            ppm = round(ppd * random.uniform(25, 30), 2)

            status, space = api("POST", "/provider/spaces", token=ptoken, data={
                "name": space_name,
                "type": stype,
                "latitude": jlat,
                "longitude": jlon,
                "price_per_day": ppd,
                "price_per_month": ppm,
                "pricing_unit": random.choice(["day", "month"]),
                "description": f"Professional {type_label.lower()} in {loc_name}. High visibility area.",
                "location_text": loc_name,
                "width": round(random.uniform(1.0, 6.0), 2),
                "height": round(random.uniform(1.0, 4.0), 2),
            })
            if space and space.get("id"):
                sid = space["id"]
                all_spaces.append((sid, ptoken, pid))

                # Upload a photo
                api("POST", f"/provider/spaces/{sid}/photos",
                    token=ptoken,
                    data={"is_primary": "1", "sort_order": "1"},
                    files={"photo": ("space.png", TEST_IMAGE, "image/png")})

                # Create availability
                api("POST", f"/provider/spaces/{sid}/availabilities", token=ptoken, data={
                    "start_date": "2026-03-01",
                    "end_date": "2026-12-31",
                    "status": "available",
                })

    print(f"  Created {len(all_spaces)} spaces with photos & availability")

    # ── Create campaigns, adsets, ads ────────────────────────
    print(f"\n[5/9] Creating campaigns, adsets & ads ({s['campaigns_per']} per client)...")
    all_ads = []      # (ad_id, client_token, space_id)
    all_adset_ids = []
    all_campaign_ids = []  # (campaign_id, client_token)

    for cidx, (ctoken, cid) in enumerate(zip(client_tokens, client_ids)):
        for ci in range(s["campaigns_per"]):
            theme = random.choice(CAMPAIGN_THEMES).format(q=random.randint(1, 4), n=ci + 1)
            budget = round(random.uniform(1000, 20000), 2)

            start_month = random.randint(3, 8)
            end_month = min(start_month + random.randint(1, 3), 12)

            _, campaign = api("POST", "/client/campaigns", token=ctoken, data={
                "name": f"{theme} — {rand_str(3).upper()}",
                "description": f"Campaign: {theme}. Target: Paris area.",
                "status": random.choice(["draft", "active"]),
                "start_date": f"2026-{start_month:02d}-01",
                "end_date": f"2026-{end_month:02d}-28",
                "budget": budget,
            })
            camp_id = campaign.get("id") if campaign else None
            if not camp_id:
                continue
            all_campaign_ids.append((camp_id, ctoken))

            # Create 1-3 adsets per campaign
            n_adsets = random.randint(1, 3)
            for ai in range(n_adsets):
                loc = random.choice(PARIS_LOCATIONS)
                jlat, jlon = jitter(loc[1], loc[2], km=2)

                _, adset = api("POST", f"/client/campaigns/{camp_id}/adsets", token=ctoken, data={
                    "name": f"Targeting {loc[0]} #{ai + 1}",
                    "latitude": jlat,
                    "longitude": jlon,
                    "location_name": loc[0],
                    "radius_km": round(random.uniform(1, 8), 1),
                })
                adset_id = adset.get("id") if adset else None
                if not adset_id:
                    continue
                all_adset_ids.append(adset_id)

                # Create 1-2 ads per adset, each linked to a random space
                n_ads = random.randint(1, 2)
                for adi in range(n_ads):
                    if not all_spaces:
                        continue
                    space_id, _, _ = random.choice(all_spaces)
                    ad_name = random.choice(AD_NAMES_TPL).format(
                        theme=theme[:20], n=adi + 1)
                    media = random.choice(["image", "video"])
                    ppd = round(random.uniform(50, 300), 2)

                    ad_start = f"2026-{start_month:02d}-{random.randint(1, 15):02d}"
                    ad_end = f"2026-{end_month:02d}-{random.randint(15, 28):02d}"

                    _, ad = api("POST",
                                f"/client/campaigns/{camp_id}/adsets/{adset_id}/ads",
                                token=ctoken,
                                data={
                                    "name": ad_name,
                                    "media_type": media,
                                    "space_id": str(space_id),
                                    "price": str(ppd),
                                    "pricing_unit": "day",
                                    "start_date": ad_start,
                                    "end_date": ad_end,
                                },
                                files={"file": ("ad.png", TEST_IMAGE, "image/png")})
                    if ad and ad.get("id"):
                        all_ads.append((ad["id"], ctoken, space_id))

    print(f"  Created {len(all_campaign_ids)} campaigns, {len(all_adset_ids)} adsets, {len(all_ads)} ads")

    # ── Create bookings ──────────────────────────────────────
    print(f"\n[6/9] Creating bookings...")
    all_bookings = []  # (booking_id, client_token, space_id)

    # Book ~60% of ads
    ads_to_book = random.sample(all_ads, k=min(int(len(all_ads) * 0.6), len(all_ads)))
    for ad_id, ctoken, space_id in ads_to_book:
        start_day = random.randint(1, 15)
        end_day = start_day + random.randint(5, 30)
        month = random.randint(4, 9)
        end_month = month if end_day <= 28 else min(month + 1, 12)
        if end_day > 28:
            end_day = end_day - 28

        _, booking = api("POST", "/client/bookings", token=ctoken, data={
            "space_id": space_id,
            "ad_id": ad_id,
            "start_date": f"2026-{month:02d}-{start_day:02d}",
            "end_date": f"2026-{end_month:02d}-{min(end_day, 28):02d}",
        })
        if booking and booking.get("id"):
            all_bookings.append((booking["id"], ctoken, space_id))

    print(f"  Created {len(all_bookings)} bookings (with auto-generated payments)")

    # ── Provider actions: confirm some bookings, upload proofs ──
    print(f"\n[7/9] Provider actions (confirm bookings, upload proofs)...")
    confirmed = 0
    proofs_uploaded = 0

    for ptoken, pid in zip(provider_tokens, provider_ids):
        _, prov_bookings = api("GET", "/provider/bookings", token=ptoken)
        if not prov_bookings or not isinstance(prov_bookings, list):
            continue

        for b in prov_bookings:
            bid = b.get("id")
            if not bid:
                continue

            # Confirm ~70% of bookings
            if random.random() < 0.7:
                api("PUT", f"/provider/bookings/{bid}", token=ptoken, data={"status": "confirmed"})
                confirmed += 1

                # Upload proof for ~50% of confirmed
                ad_data = b.get("ad")
                if ad_data and random.random() < 0.5:
                    api("POST", "/provider/proofs", token=ptoken,
                        data={
                            "ad_id": str(ad_data["id"]),
                            "booking_id": str(bid),
                            "media_type": "image",
                            "notes": f"Proof for booking #{bid} — photo taken on-site",
                        },
                        files={"file": ("proof.png", TEST_IMAGE, "image/png")})
                    proofs_uploaded += 1

    print(f"  Confirmed {confirmed} bookings, uploaded {proofs_uploaded} proofs")

    # ── Support tickets ──────────────────────────────────────
    print(f"\n[8/9] Creating support tickets & conversations...")
    tickets_created = 0
    conversations_created = 0

    # Clients create tickets
    for ctoken, cid in zip(client_tokens, client_ids):
        if random.random() < 0.4 and all_campaign_ids:
            camp_id, _ = random.choice(all_campaign_ids)
            subject = random.choice(TICKET_SUBJECTS)
            api("POST", "/tickets", token=ctoken, data={
                "ticketable_type": "campaign",
                "ticketable_id": camp_id,
                "subject": subject,
                "description": f"Details: {subject.lower()}. Please investigate.",
                "priority": random.choice(["low", "medium", "high"]),
            })
            tickets_created += 1

    # Providers create tickets too
    for ptoken, pid in zip(provider_tokens, provider_ids):
        if random.random() < 0.3 and all_adset_ids:
            adset_id = random.choice(all_adset_ids)
            subject = random.choice(TICKET_SUBJECTS)
            api("POST", "/tickets", token=ptoken, data={
                "ticketable_type": "adset",
                "ticketable_id": adset_id,
                "subject": subject,
                "description": f"Provider concern: {subject.lower()}.",
                "priority": random.choice(["low", "medium", "high"]),
            })
            tickets_created += 1

    # Create conversations (clients messaging providers about spaces)
    for ctoken in client_tokens:
        if not all_spaces:
            break
        n_convos = random.randint(0, 3)
        spaces_sample = random.sample(all_spaces, min(n_convos, len(all_spaces)))
        for space_id, _, _ in spaces_sample:
            _, convo = api("POST", "/conversations", token=ctoken, data={
                "space_id": space_id,
            })
            if convo and convo.get("id"):
                convo_id = convo["id"]
                conversations_created += 1
                # Exchange a few messages
                for _ in range(random.randint(1, 3)):
                    api("POST", f"/conversations/{convo_id}/messages", token=ctoken, data={
                        "body": random.choice(MESSAGE_BODIES),
                    })
                    # Provider replies (find their token)
                    for sid, ptk, _ in all_spaces:
                        if sid == space_id:
                            api("POST", f"/conversations/{convo_id}/messages", token=ptk, data={
                                "body": random.choice(MESSAGE_BODIES),
                            })
                            break

    print(f"  Created {tickets_created} tickets, {conversations_created} conversations")

    # ── Payments team + client proof verdicts ────────────────
    # design.json §7 (B9): Payments approves PAYMENTS. The proof verdict belongs to
    # the CLIENT — accepting is what makes a payout releasable. The old
    # /payments/proofs/{id}/approve path was a B9 violation and is gone.
    print(f"\n[9/9] Payments approve payments; clients accept proofs...")
    payments_token = login("payments@pubads.test")
    payments_approved = 0
    proofs_accepted = 0

    if payments_token:
        _, pay_list = api("GET", "/payments/payments", token=payments_token)
        if pay_list and "data" in pay_list:
            for p in pay_list["data"]:
                if p.get("status") == "pending" and random.random() < 0.6:
                    api("POST", f"/payments/payments/{p['id']}/approve", token=payments_token)
                    payments_approved += 1

    # Clients accept ~70% of the proofs on their own bookings.
    for ctoken in client_tokens:
        _, bookings = api("GET", "/client/bookings", token=ctoken)
        if not bookings or not isinstance(bookings, list):
            continue

        for b in bookings:
            for pr in b.get("proofs") or []:
                if pr.get("status") == "pending_review" and random.random() < 0.7:
                    api("POST", f"/client/proofs/{pr['id']}/accept", token=ctoken)
                    proofs_accepted += 1

    print(f"  Approved {payments_approved} payments, clients accepted {proofs_accepted} proofs")

    # ── Summary ──────────────────────────────────────────────
    print("\n" + "=" * 60)
    print("SUMMARY")
    print("=" * 60)
    print(f"  Providers:      {len(provider_tokens)}")
    print(f"  Clients:        {len(client_tokens)}")
    print(f"  Spaces:         {len(all_spaces)}")
    print(f"  Campaigns:      {len(all_campaign_ids)}")
    print(f"  Adsets:         {len(all_adset_ids)}")
    print(f"  Ads:            {len(all_ads)}")
    print(f"  Bookings:       {len(all_bookings)}")
    print(f"  Proofs:         {proofs_uploaded}")
    print(f"  Tickets:        {tickets_created}")
    print(f"  Conversations:  {conversations_created}")
    print(f"  Payments approved: {payments_approved}")
    print(f"  Proofs accepted:   {proofs_accepted}  (by the client — B9)")
    print("=" * 60)

    return 0


if __name__ == "__main__":
    sys.exit(main())
