#!/usr/bin/env python3
"""mvp-init.py — make the local MVP stack ready to log in (self-healing).

The Codespace/Docker database is WIPED on every container restart, which shows up at
login as "The provided credentials are incorrect."

It used to check exactly ONE thing — is the user count zero — and that check has an
expiry date: once the database has been seeded even once, the count is never zero
again, so the script reports "already has 8 users" and does nothing while the two
failures that actually happen after a patch go straight past it:

  • PENDING MIGRATIONS. A patch adds a migration, the schema stays old, and the app
    fails at runtime on a column that does not exist — which reads like a code bug,
    not like a migration nobody ran.
  • STALE SEED. A patch changes a seeder and the seeded rows stay as they were. This
    is the "permissions are right in the tests and the app still says Forbidden" case
    that already cost a session once.

So it now detects all three and says WHICH one fired:

  • DB empty            -> migrate:fresh --seed  (creates the 7 demo users)
  • migrations pending  -> migrate --force
  • seeders changed     -> migrate:fresh --seed  (their hash is stamped in the DB, so
                           a wipe takes the stamp with it, which is the correct default)
  • always              -> cache:clear           (permission cache is DB-backed)
  • unless --no-dispute -> db:seed DisputeDemoSeeder (the 3-chat dispute demo)

Safe to run repeatedly, and cheap: two artisan calls when there is nothing to do.

Usage:
    python3 mvp-init.py              # ensure ready (acts only if something drifted)
    python3 mvp-init.py --fresh      # force a clean wipe + reseed
    python3 mvp-init.py --no-dispute # skip the dispute demo
"""
import argparse
import hashlib
import os
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


SEED_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "backend", "database", "seeders")
STAMP_KEY = "mvp_init_seed_hash"


def seeder_hash():
    """Huella de los sembradores. Si cambia, lo sembrado esta viejo respecto al codigo."""
    if not os.path.isdir(SEED_DIR):
        return None
    h = hashlib.sha256()
    for name in sorted(os.listdir(SEED_DIR)):
        if name.endswith(".php"):
            with open(os.path.join(SEED_DIR, name), "rb") as fh:
                h.update(name.encode())
                h.update(fh.read())
    return h.hexdigest()[:16]


def stamped_hash():
    """La huella guardada en la BASE, no en disco: si la base se borra, el sello se va con
    ella, que es exactamente lo que queremos — una base nueva nunca parece ya sembrada."""
    r = artisan(
        "tinker",
        "--execute=echo optional(DB::table('system_configurations')->where('key','%s')->first())->value;" % STAMP_KEY,
        capture=True)
    if r.returncode != 0:
        return None
    out = (r.stdout or "").strip().splitlines()
    return out[-1].strip() if out and out[-1].strip() else None


def stamp(value):
    artisan(
        "tinker",
        "--execute=DB::table('system_configurations')->updateOrInsert(['key'=>'%s'],"
        "['value'=>'%s','updated_at'=>now(),'created_at'=>now()]);" % (STAMP_KEY, value),
        capture=True)


def pending_migrations():
    """Cuantas migraciones estan sin correr. None si no se puede saber."""
    r = artisan("migrate:status", capture=True)
    if r.returncode != 0:
        return None
    return len(re.findall(r"\bPending\b", r.stdout or "", re.I))


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
    want = seeder_hash()
    # El sello solo se consulta si hay algo sembrado: en una base vacia la pregunta sobra
    # y la tabla puede ni existir.
    have = stamped_hash() if (count and not args.fresh) else None
    pending = 0 if args.fresh else (pending_migrations() or 0)

    reason = None
    if args.fresh:
        reason = "--fresh"
    elif not count:
        reason = "la base esta vacia"
    elif want and have != want:
        reason = "los sembradores cambiaron desde la ultima siembra"

    if reason:
        print("\n→ Sembrando de cero (%s)…" % reason)
        if artisan("migrate:fresh", "--seed").returncode != 0:
            sys.exit("\n✗ migrate:fresh --seed failed. See output above.")
        artisan("cache:clear")
        if not args.no_dispute:
            artisan("db:seed", "--class=DisputeDemoSeeder", "--force")
        if want:
            stamp(want)
    elif pending:
        # Hay datos buenos y solo falta esquema: migrar hacia delante los conserva. Si la
        # migracion estrecha el esquema y las filas la violan, esto falla RUIDOSAMENTE y te
        # manda a --fresh, que es mejor que quedarse a medias en silencio.
        print("\n→ %d migracion(es) pendiente(s) — migrando hacia delante…" % pending)
        if artisan("migrate", "--force").returncode != 0:
            sys.exit("\n✗ migrate fallo. Los datos sembrados probablemente violan el esquema nuevo.\n"
                     "  En un MVP los datos son desechables:  python3 mvp-init.py --fresh\n")
        artisan("cache:clear")
    else:
        print("\n→ %d usuarios, sin migraciones pendientes y los sembradores no han cambiado."
              % count)
        artisan("cache:clear")

    print("\n✓ Ready. Log in at the SPA with password 'password':")
    for name in LOGINS:
        print("    %s@pubads.test" % name)


if __name__ == "__main__":
    main()
