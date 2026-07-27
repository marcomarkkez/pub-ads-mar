#!/usr/bin/env python3
"""mvp-init.py — make the local MVP stack ready to log in (self-healing).

The Codespace/Docker database is WIPED on every container restart, which shows
up at login as "The provided credentials are incorrect." Run this after any
restart, or whenever login fails, and it fixes itself:

  • if the DB is empty  -> migrate:fresh --seed  (creates the 7 demo users)
  • always              -> cache:clear           (permission cache is DB-backed)
  • unless --no-dispute -> db:seed DisputeDemoSeeder (the 3-chat dispute demo)

Safe to run repeatedly. It only reseeds when the DB is empty (or with --fresh).

Usage:
    python3 mvp-init.py              # ensure ready (reseed only if DB is empty)
    python3 mvp-init.py --fresh      # force a clean wipe + reseed
    python3 mvp-init.py --no-dispute # skip the dispute demo
"""
import argparse
import re
import subprocess
import sys

COMPOSE = ["docker", "compose"]
ARTISAN = COMPOSE + ["exec", "-T", "backend", "php", "artisan"]
LOGINS = ["client1", "client2", "provider1", "provider2", "support", "payments", "admin"]


def run(args, capture=False):
    print("  $ " + " ".join(args))
    return subprocess.run(args, capture_output=capture, text=True)


def artisan(*args, capture=False):
    return run(ARTISAN + list(args), capture=capture)


def user_count():
    """Return the User row count, or None if the DB/table can't be queried yet."""
    r = artisan("tinker", "--execute=echo App\\Models\\User::count();", capture=True)
    if r.returncode != 0:
        return None
    nums = re.findall(r"\d+", r.stdout or "")
    return int(nums[-1]) if nums else None


def main():
    ap = argparse.ArgumentParser(description="Initialize / heal the local MVP stack.")
    ap.add_argument("--fresh", action="store_true", help="force migrate:fresh --seed even if seeded")
    ap.add_argument("--no-dispute", action="store_true", help="skip the DisputeDemoSeeder demo")
    args = ap.parse_args()

    # connectivity probe — the stack must be up
    if artisan("--version", capture=True).returncode != 0:
        sys.exit(
            "\n✗ Can't reach the 'backend' container. Bring the stack up first:\n"
            "    docker compose up -d\n"
        )

    count = None if args.fresh else user_count()
    if args.fresh or not count:  # None or 0 -> needs a seed
        print("\n→ Seeding the database (%s)…" % ("--fresh" if args.fresh else "DB is empty"))
        if artisan("migrate:fresh", "--seed").returncode != 0:
            sys.exit("\n✗ migrate:fresh --seed failed. See output above.")
        artisan("cache:clear")
        if not args.no_dispute:
            artisan("db:seed", "--class=DisputeDemoSeeder", "--force")
    else:
        print("\n→ DB already has %d users — clearing caches only." % count)
        artisan("cache:clear")

    print("\n✓ Ready. Log in at the SPA with password 'password':")
    for name in LOGINS:
        print("    %s@pubads.test" % name)


if __name__ == "__main__":
    main()
