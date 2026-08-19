# Claude Agents

Instructions and context for Claude AI agents working on this project.

## Project Overview

Web-based advertising spaces marketplace built with **Laravel 12** (API backend) and **Angular 18** (frontend). Five system roles (`client | provider | admin | support | payments`) plus per-account collaborator subroles. **Everything canonical — roles, specs, decisions, schema, routes, the role/object graph, and user stories — lives in [design/design.json](design/design.json), the SINGLE design source. Not duplicated here.** In short: clients create campaigns with geolocated adsets and ads and book provider spaces; providers post spaces; admin/support/payments staff run operations.

**See [design/design.json](design/design.json) for full folder structure, database schema, API routes, data flow, dev commands, specs, user stories, and implementation status — and [design/README.md](design/README.md) for how to read it (or run the dashboard: `cd design && python3 -m http.server 8080`).**

---

## Guidelines

- Follow existing code conventions
- Write clear commit messages
- Test changes before committing
- Always append a new entry to `history.md` at the end of each session
- **Generate FEWER documents (owner rule, 2026-06-26; hardened 2026-06-27; ONE FILE since
  2026-08-02).** `design/design.json` is the SINGLE canonical home — schema, routes, specs,
  decisions, user stories, the role/object graph AND the sprint backlog all live there
  (ARCHITECTURE.md / cases.md / platform-graph.md merged in and DELETED 2026-06-27; **design.json
  and .claude/todos/mvp-sprint.json merged in and DELETED 2026-08-02**). Do NOT create a new
  `.md` or todos file — not even for a new topic: if it fits in design.json (and it almost always
  does), add it there. EVERYTHING ELSE — analyses, audits, reports, summaries, answers — is
  printed in the Claude UI chat, NOT written to a file, unless the owner explicitly asks for one.
  Temporary working files must be folded into design.json and deleted once consumed.

## Working Method — feature-by-feature MVP review (owner rule, 2026-07-10)

Too much time was lost on definitions that never got acted on. From now on we
review the product **one feature at a time**, driving to a deployable MVP:

- **Enumerate every feature** and keep them ordered in `design/design.json` — `todos[]`
  for the Fxx index, `mvpSprint.specBacklog` for the granular tasks — alongside the spec
  section they belong to. `design.json` is the single canonical spec: there is no
  ARCHITECTURE.md, no design.md, and no `.claude/todos/` any more (all folded in).
- **MVP bias: functional over precise.** Shipping something that runs beats a
  perfect spec. **No feature is in a client's or architect's request backlog**,
  so we are free to decide. If a feature question is simple, RESOLVE IT NOW with
  common sense and record the decision — do not defer.
- **Only escalate real forks:** ask the owner ONLY when a feature is genuinely
  ambiguous or when two features clash with each other. Otherwise decide and move.
- **Per feature:** if doubts/clashes → ask; else resolve + mark the feature's
  status in `design.json` (`todos[]` / `mvpSprint`) + update its spec if needed, then report the NEXT
  feature in the list so the owner can review, correct, or skip to the next.
- Latest owner instruction on a topic wins over any earlier spec.

### Stop circling — five standing rules (owner, 2026-08-04)

- **"Cuando haya dudas siempre ofrece una opción obvia, si la opción es demasiado obvia sólo
  aplícala."** Ambiguity is answered with ONE obvious proposal, never a menu of options for
  the owner to pick from. When the obvious option is overwhelmingly obvious, do not propose it
  at all — apply it, then say which way you went and why. **Never stop work to ask a question
  that has one sane answer**; a question with one sane answer is not a fork, it is latency.
  This sharpens "only escalate real forks" above: a real fork has at least two defensible
  answers.
- **"En algunas cosas siento que estamos dando vueltas en círculos, cerremos decisiones y
  testeamos."** A decision, once recorded in `design/design.json` with its `[owner YYYY-MM-DD]`
  stamp, is CLOSED. Re-opening it takes a NEW dated owner ruling — not an agent's second
  opinion, not a re-reading of older spec text, not "while I was in there I noticed…".
  **Every closed decision carries a test that fails if it is quietly undone**, and that test is
  the thing that stops the circling: the whitelist, the 409, the revoked permission each have a
  case that goes red the moment someone walks it back. Closing a decision without leaving that
  test behind means the decision will be re-litigated, because nothing is holding it.
- **"Con las preguntas ya respondidas termina de codificar todo… a partir de ahí pulimos desde
  los walks solamente, nada de teoría en código seco."** Everything planned must end as
  RUNNING CODE — nothing stays half-written, no endpoint without its UI, no UI without its
  endpoint, no spec paragraph without a route behind it. Once that is true, further polish is
  driven ONLY by a human **WALK-n** walkthrough that hits a real problem in the running app.
  Speculative design work on code that already runs — refactors, "we could also", theoretical
  edge cases nobody walked into — is exactly the circling the rule above forbids.
- **"Si bien revisar el código en seco es bueno, produce bucles entre los agentes y se repiten
  las mismas preguntas constantemente."** → `BR-16` (`sin-bucles-de-codigo-seco`). There is ONE
  circuit for a fault and it runs in this order: **(1)** a fault that keeps coming back enters
  `errorHunt` as a PATTERN, not as an incident — the value is in `howToFind`, the search that
  can be repeated tomorrow on new code; **(2)** if the fix is already agreed, it goes into CODE
  in the same patch, without asking again; **(3)** then the todo; **(4)** then the todo hangs
  off a WALK. From there, hands off until there is a reproducible error or the human
  walkthrough finds the mismatch. Reading code without running it generates infinite hypotheses
  and zero evidence, and each lap costs the owner a decision while adding no information — Q35,
  Q38 and Q40 were literally the same question three times, already answered by
  `walkthroughs.convention`.
- **A WALK's references are what closes a spec, and a walk may use cURL** → `BR-17`
  (`un-walk-tambien-se-comprueba-con-curl`). Every walkthrough points at what it proves:
  `closes`, `specs`, `ucs`, `br`, `eh`. While its status is `pending` or `failed`, **everything
  it references is open** — however green the suite is; only `passed` marks them done, and
  todos that existed only for that walk can then be deleted. A step may carry a `probe`: a
  console check or a cURL call the person runs during the walkthrough. That is not a
  concession — it is necessary. A 404 and a 403 look identical on screen and are the whole of
  `BR-3`, so without `probe` the step that checks them would be unfalsifiable.

**Citing a rule.** Every `businessRules` and `errorHunt` entry carries a short, stable `key`
alongside its id, so a rule can be named in conversation without restating it: `BR-4` and
`dinero-depositado-no-se-devuelve` reach the same place. The dashboard renders the key as a
click-to-copy chip.

## Rule — a command you write down is a command you already ran (2026-08-13)

Three failures in one day, all the same shape: **instructions that had never been executed**.

**1. Zero placeholders. Ever.** WALK-6 step 1 shipped as
`curl -si -X POST localhost:8000/api/register -d '{...}'`. `'{...}'` was a placeholder I meant
to fill in and never did. The owner copied it, got nothing back, and lost the run. A probe that
cannot be pasted and executed is **worse than no probe**: it costs the walker time and it
discredits every other line in the script. Every probe now carries complete JSON, resolves its
own token inline, and states the status code it demands — and each was **run verbatim, with its
real output pasted into the report**, before being written into design.json.

**2. Do not prescribe host tooling that lives in a container.** I told the owner
`cd frontend && npx ng build`. It failed with `could not determine executable to run`, because
`frontend/node_modules` is a **Docker named volume**: `npm ci` runs inside the image, and on the
host that directory does not exist. It is also unnecessary — compose mounts the source and the
container runs `ng serve --poll`, so **`frontend/` rebuilds itself and only needs a browser
reload**. `landing/` is NOT in compose, so it does build on the host. Same-looking projects,
opposite commands; `patch-preflight.py` now emits the right one per project.

**3. Never chain `cd x && …` in a recipe.** When that build failed, the `&& cd ..` never ran,
so the next line — `python3 mvp-init.py` — executed from inside `frontend/` and failed too. One
wrong command became two. Recipes now wrap every directory change in a subshell: `( cd landing
&& npm ci )` cannot move the caller's shell, whether it succeeds or fails.

**The rule behind all three:** an instruction handed to someone else is a claim that it works.
Run it verbatim first, from the directory you are telling them to be in, on the stack they
actually have. This is `BR-17` (a walk may use cURL) taken seriously: a probe is only worth
having if it is falsifiable, and one that errors on its own syntax falsifies nothing.

## Learning — a command nobody can copy is a command that gets retyped (2026-08-15)

WALK-6 step W6-4 failed with `405 Method Not Allowed` where `409` was demanded. The probe in
design.json was **correct** — it already contained the `GET /api/collaborations` line that
resolves the row id. The owner ran a different command, with variable names (`$MI_PROPIA_FILA`,
`$TOKEN_COLABORADOR`) that **appear nowhere in this repository**: they retyped it by hand, and
the line that fills the id was the one that fell out. With the variable empty the URL collapsed
to the collection URI, which exists for `GET`/`POST` and not for `DELETE`. The request never
reached the controller; the guardrail was never even asked.

The cause was presentation, not content: `.walk-probe code` was rendered muted grey at 11.5px
with `word-break: break-all` — which chops words mid-token and makes a shell command
unrecognisable — and **had no copy button**, in a dashboard where the BR/EH keys had one.

Two things follow, and the second is the general one:

- **Anything meant to be pasted must be one click from the clipboard.** Correctness is not
  enough; a probe is a UI, and its usability is part of whether it works.
- **`405` on a REST collection route means the id was empty.** Read the `allow:` header before
  suspecting the endpoint: `allow: GET, HEAD, POST` on a `DELETE` is the router saying the URI
  never had an id in it. It is an unpopulated variable, not a broken guard.

This is `EH-17` ("la instruccion que nadie ejecuto") one layer up: the probe here *had* been
run. Running it was necessary and still not sufficient — it also has to survive the trip to the
person who runs it.

## Learning — one null field blanks a screen you did not touch (2026-08-15)

The same walk reported three unrelated-looking breakages on `/provider/spaces`: View/Edit did
nothing, no delete button existed, and — on a completely different component — the avatar menu
would not open, so the provider could not sign out. One cause:

```
ERROR TypeError: Cannot read properties of null (reading 'replace')
    at _SpaceListComponent.formatType (...)
```

`formatType(type: string) { return type.replace(...) }`, and `spaces.type` is nullable —
`DisputeDemoSeeder` had been writing one row with `type: null` and `price_per_day: null`.

**Read this DOM signature; it names the failure exactly.**

```html
<td class="actions"><a class="btn btn-sm">View</a><a class="btn btn-sm">Edit</a><!--container--><!--container--></td>
```

Angular builds a view in two passes. The *create* pass makes elements and the comment anchors
that control-flow blocks attach to — so the `<a>` tags and their static text exist. The *update*
pass evaluates bindings: `RouterLink` sets `href` there, and `@if` chooses a branch there. So
**anchors present + `href` absent + `<!--container-->` with nothing rendered = the update pass
threw**, at the first binding that raised, leaving everything after it untouched. Never read
that as a stale bundle or a missing `routerLink`; grep the console instead.

The blast radius is the whole tick, not the component: `ApplicationRef.tick()` refreshes the
router outlet's contents before the navbar, so an exception in the routed component means the
navbar never repaints. `menuOpen` toggled correctly on every click — verified — and no toast
was covering the avatar; the repaint simply never happened. **When an unrelated shell control
dies at the same time as a page, stop looking for a second bug.**

Three rules from it:

- **Every field a migration allows to be null must be handled as null in the template**, in
  formatters most of all. A formatter is called inside change detection, where a throw is not
  a blank cell — it is the rest of the screen.
- **A shared formatter lives in one file.** The same `formatType` had been copied into three
  components, so one null crashed the provider list, the provider detail and the client search
  independently. It is now `core/models/space-type.helper.ts`.
- **Seeders write the fixtures the UI is judged on.** A seeder that leaves NULLs in columns the
  UI treats as required is not test data, it is a crash waiting for a walk. And note how it
  hid: the crash reproduced **only via SPA navigation**, not on a full page load — so a
  regression script must click through the app like a human, not `goto()` the URL.

## Rule — before and after EVERY `git apply` / docker action (2026-08-09)

**The prevention used to sit at the wrong moment.** It said: *"the moment the owner says
credentials incorrect, tell them to run `mvp-init.py`."* That fires when the damage is already
done and the symptom has already cost half an hour of guessing passwords. The patch itself
announces what it will break — its file paths say so — so the check belongs **before** it lands.

**Before applying any patch:**

```bash
python3 patch-preflight.py pNN.patch     # reads the patch, prints what it will require
```

It reports migrations, seeder changes, permission edits, dependency changes, and — the
expensive one — whether the patch **narrows the schema** (`dropColumn`, `->change()`, `CHECK`,
`NOT NULL`, `unique`). A narrowing migration against rows that are already seeded fails
half-way and leaves the database between two states. Data is disposable in this MVP (owner
2026-08-03), so the cheap answer is the right one there: `python3 mvp-init.py --fresh`.

**After applying, always, unconditionally — doc-only patches included:**

```bash
python3 mvp-init.py
```

**Why `mvp-init.py` alone is not enough any more.** It used to check exactly ONE thing: is the
user count zero. That check has an expiry date — once the database has been seeded even once
the count is never zero again, so it reports "already has 8 users" and does nothing, while the
two failures that actually happen after a patch walk straight past it:

- **Pending migrations.** The schema stays old and the app fails at runtime on a column that
  does not exist — which reads like a code bug, not like a migration nobody ran.
- **Stale seed.** A seeder changed and the rows did not. This is the "permissions are correct in
  the tests and the app still says Forbidden" case, and it has already cost a session once.

It now detects all three (empty / pending migrations / seeder hash changed) and says which one
fired. The seeder hash is stamped **in the database**, not on disk, so a wipe takes the stamp
with it — a fresh database can never look already-seeded.

**When the stack is restarted** (`docker compose up`, container restart, Codespace resume) the
database may be empty regardless of what any patch touched. Same command, same place: run
`mvp-init.py` before doing anything else, which is why it is step **W0** of every walkthrough
that touches the app.

**And the meta-rule, which is the one that actually failed here:** when the owner proposes a
cause — *"¿otra vez el error de seed?"* — that is a hypothesis from someone with more context
about their own machine than this file has. **Test it before contradicting it.** The seed rule
below was already written, correct and complete; what failed was retrieving it in the moment.
Answering "no, it is X" and being wrong costs more than checking would have.

## Learning — a written rule that does not fire is not a rule (2026-08-09)

The seed learning below (search "The provided credentials are incorrect") is correct, complete,
and was already in this file. The owner asked, in as many words, **"¿otra vez el error de
seed?"** — the exact trigger it names — and the answer given was "no, it is the credentials",
followed by guessing passwords. Their database had zero users. It was the seed, from the first
message.

**Nothing was missing from the knowledge. What failed was retrieving it.** The rule sits at line
~352 of a very long file, inside a twenty-line paragraph, and `mvp-init.py` — which exists at the
repo root precisely for this, is idempotent, and fixes it in one command — went unmentioned for
an entire session.

That is BR-16 applied to this file: a rule with no enforcement is a convention, and conventions
fail. So the check moved to where it cannot be forgotten — **step W0 of every walkthrough that
touches the app** is now `python3 mvp-init.py`. The human running the walk carries it; no model
has to remember.

**Generalisation worth keeping:** when the owner proposes a cause ("is it the seed again?"), that
is a hypothesis from someone with more context about their own machine than this file has. Test
it before contradicting it. Answering "no, it is X" and being wrong costs more than checking
would have.

## Learning — npm supply chain (2026-08-09, after the keyv compromise)

**The lesson: installing a dependency runs its code.** On 2026-08-04 the maintainer account
behind `keyv` and `cacheable` was taken over and malicious versions of nine packages were
published — `keyv`, `cacheable-request`, `cache-manager`, `@cacheable/utils`, `flat-cache`,
`file-entry-cache`, `cacheable`, `@cacheable/memory`, `@cacheable/node-cache` — spreading to
roughly 444 packages across ~2,236 versions, together over two billion monthly downloads.

What makes it different from "a library with a bug" is **when it runs**. The payload lived in a
`preinstall` hook (`setup.mjs`) that fetched a standalone Bun runtime, ran an obfuscated second
stage, and harvested npm, GitHub, AWS and HashiCorp Vault credentials — then used the stolen
publish tokens to trojanize more packages. A worm. **Nobody had to import anything. Installing
was enough.** Every instinct we have about "code I don't call can't hurt me" is about import
time, and this runs before that, as your user, in the one place where the tokens live.

**Our audit (2026-08-09): clean.** None of the nine appears in `frontend/package-lock.json` or
`landing/package-lock.json`; no `preinstall` hook in anything installed; no `setup.mjs`. A Bun
binary at `/root/.bun/bin/bun` — the exact IoC — was ruled out **by date**: built 2026-03-18,
installed 2026-03-31, declared in the image's `.bashrc`, months before the compromise. Date a
suspicious artefact before concluding anything about it; `stat` separates the base image from
what an install dropped.

**What changed, so this is design and not luck** → `BR-18` (`npm-sin-scripts-de-instalacion`)
and `EH-15` (`el-codigo-que-corre-antes-de-ser-importado`):

- `frontend/.npmrc` and `landing/.npmrc` carry `ignore-scripts=true`. Verified: `npm ci
  --ignore-scripts` followed by `ng build` works in both. If a dependency ever genuinely needs
  a build step, run `npm rebuild <package>` **naming it** — the exception gets said out loud.
- **`npm ci`, never a routine `npm install`.** `ci` installs exactly what the lockfile says and
  fails if it disagrees; `install` resolves new versions without asking, and that is the door a
  freshly published malicious version walks through.
- **The lockfile diff is reviewed.** A `package-lock.json` that changes packages nobody asked
  for is a finding, not formatting noise.

Honest note on how this was found: I had run `npm install` and `npm ci` in `landing/` several
times *after* 2026-08-04 while fighting the lockfile, without once looking at which scripts were
executing. The exposure was real even though the outcome was clean.

## Key Files

| File | Purpose |
|------|---------|
| `design/design.json` | THE single canonical source — overview, roles, specs, decisions, schema, API/endpoint map, role/object graph, dev commands, status, the user stories (§19) AND the build backlog. Absorbed ARCHITECTURE.md + cases.md + platform-graph.md (deleted 2026-06-27), then design.md + `.claude/todos/mvp-sprint.json` (deleted 2026-08-02). See `design/README.md` for how to read it. |
| `design/design.html` | Dashboard over that data — `cd design && python3 -m http.server 8080`. |
| `history.md` | Session diary — append entries, never delete |
| `claude.md` | This file — agent instructions |
| `install_beads.md` | Guide for installing the `bd` task CLI |
| `backend/.env` | Environment config (DB, keys) |
| `backend/routes/api.php` | All API route definitions |
| `backend/app/Http/Controllers/` | API controllers grouped by role |
| `backend/app/Models/` | Eloquent models |

---

## Tech Stack

- **Backend:** Laravel 12 (API only) — `backend/` directory
- **Frontend:** Angular 18 SPA — `frontend/` directory
- **Database:** PostgreSQL 17 local **port 5434** — db: `pub_ads_mar`, user: `postgres`, no password (trust auth)
- **Auth:** Laravel Sanctum (token-based, Bearer token in header)
- **CORS:** Allow `http://localhost:4200` with credentials
- **File storage:** Laravel `storage/app/public/` via `php artisan storage:link`

---

## User Roles

Five system roles — `client`, `provider`, `admin`, `support`, `payments` — plus per-account
**collaborator subroles** (installator / publicist / manager). The canonical role + permission
spec and the collaborator model live in **[design.json](design.json)** (§Roles, §Collaborator Roles);
they are not duplicated here to avoid drift.

---

## Database Schema (11 tables)

| Table | Owner | Key Columns |
|-------|-------|-------------|
| users | — | role (client/provider/admin/support/payments), phone, company_name, address, is_active |
| campaigns | user (client) | name, description, status, start_date, end_date, budget |
| adsets | campaign | name, latitude, longitude, location_name, radius_km, status |
| ads | adset | name, media_type (image/video/sound/gif), file_path, file_name |
| spaces | user (provider) | name, type, latitude, longitude, price_per_day, width, height, is_active |
| space_photos | space | file_path, file_name, is_primary, sort_order |
| space_availabilities | space | start_date, end_date, status (available/booked/blocked) |
| bookings | client + space + adset | start_date, end_date, total_price, status |
| payments | booking | amount, status, payment_method (mocked), transaction_id |
| conversations | space + client + provider | unique per (space_id, client_user_id) |
| messages | conversation + sender | body, is_read |

---

## API Route Structure

```
POST /api/register, POST /api/login             — public
POST /api/logout, GET /api/me                   — any authenticated

/api/client/*    role:client   — campaigns, adsets, ads, bookings, space search
/api/provider/*  role:provider — spaces, photos, availabilities, bookings, dashboard
/api/manager/*   role:manager  — user CRUD (CRM)
/api/conversations/*           — any authenticated — messaging
```

---

## Dev Commands

```bash
# Backend
cd backend
php artisan serve                 # API at http://localhost:8000
php artisan migrate               # Run pending migrations
php artisan migrate:fresh --seed  # Reset DB + seed demo data
php artisan route:list            # List all routes
php artisan storage:link          # Link storage for file uploads

# Frontend
cd frontend
npm install
ng serve                          # SPA at http://localhost:4200

# PostgreSQL (local PG 17 on port 5434)
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5434 -d pub_ads_mar
```

---

## Skills Available in This Project

These skills are globally installed and available in all sessions:

| Skill | Trigger |
|-------|---------|
| `todo-tracker` | Session start/end, task management |
| `session-recorder` | Save session to history.md with token count |
| `skill-creator` | Create or update skills |
| `find-skills` | Discover installable skills |
| `pg:design-postgres-tables` | Design PostgreSQL schemas |

The `skill-creator` tool is also installed locally at `.agents/skills/skill-creator/` (symlinked to `.claude/skills/`).

---

## Implementation Status

See [design.json](design.json) §18 (System & Dev → Implementation status) for the full table.

**Completed:** Full backend API — Laravel scaffold, PostgreSQL DB, Sanctum auth, CORS, 14 migrations, 11 models, RoleMiddleware, 14 controllers, 49 routes, demo seeders, storage link. All endpoints tested and working.

**Next up (unblocked):**
1. Angular frontend development (auth, client, provider, manager features)
2. Frontend auth service + token interceptor
3. UI components (map, media preview, booking card)

---

## Notes

- `history.md` is the session diary — always append, never overwrite previous entries. Insert new entries before the `<!-- Add new sessions above this line -->` marker.
- Payments are mocked (no real gateway) for MVP.
- Geolocation search uses bounding-box filter for MVP; PostGIS is the upgrade path.
- Message body is filtered (strip phone numbers, emails, URLs via regex) to prevent direct contact sharing.
- The `bd` CLI (beads) can be installed for persistent task tracking — see `install_beads.md`. Install via: `go install github.com/steveyegge/beads/cmd/bd@latest` then add `%USERPROFILE%\go\bin` to Windows PATH.

---

## Docker Workflow (Canonical)

Dev is now Docker-first. `docker compose up -d --build` from repo root brings
up the whole stack: `backend` (PHP 8.4-fpm), `nginx` (:8000), `frontend`
(ng serve :4200), `db` (postgres:17-alpine, host :5435 → container :5432).

Key files: `docker-compose.yml`, `backend/Dockerfile`, `backend/docker/`,
`frontend/Dockerfile`. Plan lives at `.claude/plans/dockerize-stack.md`.

The PostgreSQL section below is now **legacy** — only relevant when running
without Docker.

---

## PostgreSQL Troubleshooting (Legacy — bare-metal only)

This machine has **multiple PostgreSQL installations** on different ports:

| Version | Port | Auth | Status |
|---------|------|------|--------|
| PG 17 | **5434** | `trust` (no password) | **Use this one** |
| PG 18 | 5432 | `scram-sha-256` (unknown password) | Do NOT use |
| PG 16 | 5433 | `scram-sha-256` | Do NOT use |

**Always use port 5434** for this project. The `.env` must have:
```
DB_PORT=5434
DB_PASSWORD=
```

The `pg_hba.conf` for PG 17 (`C:\Program Files\PostgreSQL\17\data\pg_hba.conf`) has `trust` for 127.0.0.1/32 and ::1/128, meaning no password is needed for local TCP connections.

**Common pitfall:** If you see "password authentication failed for user postgres", you're hitting the wrong PostgreSQL instance (port 5432 = PG 18). Always specify `-p 5434` in psql commands.

---

## Learnings / Common Mistakes

- **Timestamp every DECISION change.** Whenever the owner sets or changes a decision, record it inline with a dated marker `[owner YYYY-MM-DD]` in design.json (and history.md / todos) and commit it, so `latest-decision-wins` can be resolved by DATE and the git history shows WHEN each call was made. The owner asked for this explicitly (2026-07-17): persist design decisions first (committed) so they're reviewable in git before the code lands. Same rule in AGENTS.md.

- **Windows `NUL` vs bash `/dev/null`:** On Windows with bash shell, never redirect to `NUL` — bash treats it as a literal filename and creates a `nul` file. Always use `/dev/null` instead.
- **Skill files go in `.agents/skills/`**, not the project root. If `.skill` files appear in the root, move them to `.agents/skills/`.
- **Don't create files in the project root** unless they're documented in design.json. Keep the root clean.
- **Beads `bd.exe` needs the sqlite backend here, not dolt.** The installed `bd.exe` (v0.49.3 dev) was built without CGO, so it CANNOT open the dolt backend ("dolt backend requires CGO"). It only worked while a background daemon (sqlite-backed, over a socket) was alive; once that daemon dies, direct CLI calls fall through to dolt and fail. Fix applied 2026-06-13: re-init to sqlite (`metadata.json` backend=sqlite; `bd init --backend sqlite`). Old dolt store is in `.beads-backup-20260613/`. Invoke as `/mnt/c/Users/mucho/go/bin/bd.exe --db .beads/beads.db ...` (it's a Windows binary, not on the WSL PATH).
- **Never run parallel agents that WRITE files while the user has files open in the IDE.** The first MVP build workflow ran parallel implementers in the same working tree; they wrote files the user had open → "content is newer" save conflicts, AND 4 packets failed mid-run. Rule: for code-mutating agent work, write SEQUENTIALLY (one area at a time), keep a change log (`.claude/mvp-changelog.md`) for surgical rollback, and have the user close affected tabs first.
- **OneDrive sync causes transient `ENOENT`/"file modified since read" on edits.** The repo lives under OneDrive; edits to large files (e.g. `design.json`) intermittently report `ENOENT` even though they LAND — and a blind retry can apply the edit TWICE (duplicate blocks). Rule: after an `ENOENT` on an edit, `grep -c` the inserted text before retrying; don't blindly re-edit.
- **Patch-transport workflow (git push is blocked in the Claude session).** Claude commits locally and hands the user a `.patch` to apply in their Codespace. Two failure modes bit us and are now guarded:
  1. **`git add -A` swallowed the `.patch` file** — twice a commit contained ONLY `Fxx.patch` (178 lines) and none of the code, because `git apply` silently failed and `git add -A` grabbed the patch file. FIX: `*.patch` is now git-ignored (root `.gitignore`), so a stray patch can never be committed again. Apply procedure: `git apply X.patch` → **check `git status` shows the CODE files** → `git commit`. If status shows only the `.patch`, the apply failed — re-run with `git apply --3way X.patch`.
  2. **Don't send a partial patch and then keep coding and send another for the same feature.** It creates ambiguity about which file to apply (the user applied the older `F09-support-join.patch` join-only file). RULE: finish ALL of a feature's changes FIRST, then hand over ONE complete, clearly-named patch. If a superseding patch is unavoidable, say explicitly "DELETE the previous patch, use only this one."
- **The push 403 is READ-vs-WRITE, not visibility and not the branch name — stop re-diagnosing it.** Confirmed 2026-08-01 with commands, after the owner asked whether making the repo private had caused it:
  ```
  git fetch origin <branch>                   → OK
  git push origin <any-branch>                → 403
  git push --dry-run <session's own branch>   → 403
  ```
  The repo was **public** at the time (`private:false` via the GitHub API), and the session's designated branch 403s exactly like a new one, so neither visibility nor branch naming is the cause: the Claude session holds the repo in READ scope only. `add_repo` with `access:"push"` returns `already_present` and changes nothing. The proxy README (`/root/.ccr/README.md`) is explicit — *"Do not retry or route around it — report the blocked host."* RULE: **do not spend turns on the 403.** Build the patch from the index (`git add -A && git diff --cached --binary > pNN.patch && git reset` — see the no-commits rule below; the older `git format-patch` recipe is superseded), hand it over, and let the owner push from their Codespace (their own credentials work — that is how p62 landed). Do not "fix" it by flipping repo visibility; that exposes the code and fixes nothing.

- **Branches are per DEVELOPER, never per feature/task (owner rule, 2026-08-01 — applies to ALL sessions from here on).** The common habit of naming a branch after the feature is wrong for this project: a branch identifies WHO is working, not WHAT they are building. One developer, one long-lived branch, many features on it. **While in MVP mode (current, until further notice): everything goes on `mvp-build`.** Do not open a `claude/...` branch per task — that habit is exactly what produced the divergence below, where the same Codespaces login bug got fixed twice, two different ways, on two branches that never merged. If something genuinely needs isolation, **ask first** and say why. Branch cleanup is the OWNER's job: deleting a remote branch is a push, and pushes 403 from the Claude session. (Housekeeping done 2026-08-01: `k1fgt8` deleted after rescuing its error codes, `main` fast-forwarded to `mvp-build` — they had drifted 80 commits apart with `main` as the default branch, so fresh clones were landing on stale code. `main == mvp-build` now; keep it that way.)

- **In Claude Code (web) sessions, NEVER push and never ask to — deliver a patch plus a ready-to-paste commit message (owner rule, 2026-08-01).** The owner will not ask for a push; the session cannot do one anyway (403, see below). The deliverable is always two things: (1) `pNN.patch` from `git diff --cached --binary` (no local commit — see the 2026-08-02 rule below), numbered above the last `pNN` used, and (2) a **suggested commit message** the owner can paste verbatim. Reason it matters: the owner applies patches with `git apply pNN.patch && git add . && git commit -m "pNN"`, which **squashes every commit in the patch into one** — so whatever detailed messages the patch carries are collapsed and lost. Hand over the message you want on the record, don't bury it in the patch. Always close with the login-readiness line (`python3 mvp-init.py`).

- **A branch is the ONLY home of its unmerged commits — "we have the commits anyway" is false for them.** Before deleting any branch, check what dies with it: `git log --oneline origin/mvp-build..origin/<branch>` (unique commits) and `git cat-file -e origin/mvp-build:<path>` per interesting file. That check saved `ApiErrorCode.php` + `api-error.ts` on 2026-08-01: `k1fgt8` looked fully superseded because its CORS fix was, but its structured-error-code work existed nowhere else and would have been lost silently. Rescue first, delete second.

- **State the BRANCH when you claim something is missing — a branch-scoped fact is not a repo fact.** Burned twice in one day (2026-08-01), in opposite directions, on the same repo:
  - A session sitting on `claude/pub-ads-mar-github-k1fgt8` reported *"history.md ends at Session 18, three commits are undocumented."* True on that branch; false for the repo.
  - A session sitting on `mvp-build` reported *"those commits don't exist in the repo."* Also false — `git branch -r --contains ff98444` shows them living on `k1fgt8`.

  Both were right about their own branch and wrong about the repo, because neither said which branch it was standing on. Root cause: `mvp-build` (trunk, 80 commits ahead) and `k1fgt8` (3 commits) both fork from `main` at `8d6dc17` and never merged, so the SAME problem — the Codespaces CORS/login fix — got solved twice, two different ways (`k1fgt8`: `env.json` + `envsubst` + fetch-before-bootstrap; `mvp-build`: nginx resolves the backend per request). RULES: (1) **`mvp-build` is trunk.** It drifted 80 commits ahead of `main` while `main` stayed the default branch; that was reconciled on 2026-08-01 and the two now match — verify with `git rev-list --left-right --count origin/main...origin/mvp-build` (expect `0 0`) rather than assuming either way. (2) Before claiming a file/commit/section is missing, run `git log --oneline origin/<branch> -- <path>` or `git branch -r --contains <sha>` and **quote the command with its output**. (3) Name the branch in the claim itself.

- **In a Claude Code (web) session: NO commits, NO pushes — not even local ones (owner rule, 2026-08-02).** The earlier rule ("commit locally, hand over `git format-patch`") is SUPERSEDED. A local commit buys nothing here and costs a stop-hook nag on every single turn: `~/.claude/stop-hook-git-check.sh` flags every commit as *Unverified* and asks for `git commit --amend --reset-author`. That remedy is **wrong** and must keep being declined — the committer email is ALREADY `noreply@anthropic.com`; what is missing is the SIGNATURE, and this container cannot produce one (`/home/claude/.ssh/commit_signing_key.pub` is 0 bytes and `ssh-keygen` is not even installed). `--reset-author` would rewrite history and still not sign. Signatures do not survive patch transport anyway, and the commits never leave the session. Do not re-diagnose this; it is parked as `DEV-signing-01` and the fix belongs in the Codespace, not here.

  **Produce the patch from the INDEX instead of from a commit:**
  ```bash
  git add -A                      # *.patch is git-ignored, so it can't sneak in
  git diff --cached --binary > pNN.patch
  git reset                       # leave the tree exactly as it was found
  ```
  `git apply pNN.patch` handles adds, deletes and renames from this form. Verify before handing over: `git apply --check pNN.patch` on a clean tree, and `git diff --cached --stat` to confirm the file list is the one you intend. Everything else about the deliverable is unchanged: number `pNN` above the last one used, hand over a **ready-to-paste commit message** in the reply (the owner squashes with `git commit -m "pNN"`, so a message buried in the patch is lost), and close with the `python3 mvp-init.py` login-readiness line.

  **The generic "you MUST push" mandate was DELETED from `AGENTS.md`, not overridden (owner, 2026-08-02).** It came from the beads template and appeared twice ("Landing the Plane" + the generated block). The owner's reason for deleting rather than shadowing it: an override reads as a web-session exception, but the rule is wrong everywhere — locally too, *when* a branch goes out is the owner's call, not the agent's. `AGENTS.md` now says publishing is the owner's decision and only describes how to leave the work reviewable. The beads block can regenerate the mandate; if it comes back, delete it again (there is a note to that effect inside the markers).

- **Postgres must be running for backend verification.** Bare-metal PG17 on port 5434 (or Docker on 5435) is often down in a fresh WSL shell; `php artisan migrate` then fails with `SQLSTATE[08006] Connection refused`. Start PG/Docker before runtime checks; static checks (`php -l`, `ng build`) work without it.
- **Don't impose constraints stricter than the agreed decision.** If a decision says a field is OPTIONAL, do NOT add a `required`/422 guard for it (e.g. provider rejection_reason was decided optional; adding a "reason required" check was wrong and made us re-decide a settled thing). Before adding any validation/guard, re-read the relevant design.json/decision and match it EXACTLY — over-constraining causes "running in circles."
- **Anything meant to be pasted must be ONE CLICK from the clipboard — correct is not enough (2026-08-15).** WALK-6 step W6-4 failed with `405` where `409` was demanded, and the probe in design.json was already **correct**: it contained the `GET /api/collaborations` line that resolves the row id. But it rendered as muted grey `<code>` at 11.5px with `word-break: break-all` and **no copy button**, in a dashboard where the BR/EH keys had one. So the owner retyped it by hand — with variable names (`$MI_PROPIA_FILA`, `$TOKEN_COLABORADOR`) that appear NOWHERE in this repo — and the line that fills the id was the one that fell out. Empty variable → the URL collapses to the collection URI → `405`, because that URI exists for `GET`/`POST` and not for `DELETE`. The request never reached the controller and the guardrail was never asked. Two rules: **(1)** a probe is a UI, and its copyability is part of whether it works — `.walk-probe` is now a readable code block with a copy button wired through `copyText` (which falls back to `execCommand`, because the dashboard is also served over `http://` port-forwards where `navigator.clipboard` does not exist). **(2)** **`405` on a REST collection route means the id was empty** — read the `allow:` header before suspecting the endpoint; `allow: GET, HEAD, POST` on a `DELETE` is the router saying the URI never had an id in it. Full write-up: *"a command nobody can copy is a command that gets retyped"* above, and `EH-17` in design.json.

- **A verification script that only runs on the machine that WROTE it verifies nothing (2026-08-19).** WALK-6 step W6-7 did not fail — it *crashed*, on `ERR_MODULE_NOT_FOUND: /opt/node22/lib/node_modules/playwright/index.js`, a path that exists in this sandbox and nowhere in the owner's Codespace (Node 24 there, Node 22 here). Three separate host facts had been baked into `frontend/e2e-*.mjs` as literals: where Playwright lives, where the chromium binary lives (`/opt/pw-browsers/chromium`), and that `php artisan` runs natively — while the Codespace reaches artisan only through `docker compose exec -T backend`. Every one of them is a fact about the AUTHOR's machine, and none of them is a fact about the project. Rules: **(1)** resolve host-shaped facts at RUNTIME (`frontend/e2e-env.mjs` does it in one place for both guards: Playwright via `createRequire().resolve` over local → repo → `npm root -g`, chromium by scanning `PLAYWRIGHT_BROWSERS_PATH` and otherwise letting Playwright pick its own, artisan by probing native then docker). **(2)** *Probe*, never assume — and probe the thing you actually need: the first draft asked `DB::connection()->getDatabaseName()`, which only reads config and green-lit a native artisan with Postgres **down**; `DB::connection()->getPdo()` opens the connection and tells the truth. `tinker` prints its exception on stdout and still **exits 0**, so a printed marker decides, not the exit code. **(3)** Separate *"could not run"* from *"the app is broken"*: a missing tool exits **2** with the one line to paste, a failed check still exits **1**. A walker must never have to read a Node stack trace to learn that his machine lacks a tool. **(4)** **Walk tools install OUTSIDE the app's dependency tree.** The first version of that paste-line said `cd frontend && npm install --no-save playwright`, and it bounced with `EACCES: mkdir /workspaces/pub-ads-mar/frontend/node_modules/@ampproject` — in the Codespace that directory is a bind mount written by a container running as **root**, and the human at the terminal is `codespace`. Installing a *test tool* is not a reason to `sudo chown` the *app's* `node_modules`. The tools now go to `$HOME/.pubads-e2e` (override: `E2E_TOOLS_DIR`), which is writable by definition, is nobody's build input, and — being a path the resolver already searches — needs no environment variable on any later run: install once, never think about it again.

- **Spec traceability — annotate code with the spec it implements.** Every file / class / method that implements a design.json behavior MUST carry a short comment naming the spec it satisfies, e.g. `// design.json §B9 — client proof accept/reject gates payout` or `@implements design.json "Proof of Display"`. This keeps code↔design compliance checkable at a glance. Applies to new code and to code you touch.
- **Momentum over perfection — soft vs hard stops (owner working style).** The owner wants forward progress, not 100% compliance. Resolve ambiguous/"soft" logic conflicts yourself by **latest-decision-wins** (the most recently edited doc / newest owner message overrides older text) or by best-guess of the most sensible scenario, and KEEP BUILDING. Only stop to ask on a **HARD stopper**: something you genuinely cannot infer, or a destructive/irreversible/outward-facing action. Target ~**90% doc compliance** (design.json), not 100% — decisions have changed over time, so some older doc text is intentionally stale. The owner reviews the running app and prunes features afterward. Don't create new planning docs unless asked; edit design.json / todos in place.
- **Permission changes need a CACHE BUST + a real re-seed — tests passing ≠ app working.** `PermissionMiddleware` reads `RolePermission::getCachedPermissions($role)` which is `Cache::remember(..., 60 min)` on the `database` cache store. Symptom that bit us: all F07-F10 automated tests GREEN (they use a fresh `array` cache + reseed per test), but the live app returned **"Forbidden. You do not have permission"** for `payments` and `support` on their own menus. Two independent causes stacked: (a) the permission cache was stale (old/empty set, 60-min TTL) — the seeder DOES call `RolePermission::clearCache()` at the end, but a belt-and-suspenders `php artisan cache:clear` is the reliable fix; (b) a first `db:seed --class=RolePermissionSeeder` had **not actually persisted** payments/support rows to `pub_ads_mar` (a later `--force` reseed showed `payments|10, support|7`). RULE after ANY permission/seed edit: `docker compose exec backend php artisan db:seed --class=RolePermissionSeeder --force` **then** `php artisan cache:clear`, then hard-reload. DIAGNOSE cleanly, don't guess: `php artisan db:show` (which DB is artisan really on?) + `psql -d pub_ads_mar -c "select role,count(*) from role_permissions group by role;"` (is the DATA there?) — that pair distinguishes wrong-DB vs missing-data vs stale-cache in one shot. Screenshots of the browser Forbidden add no signal; ask for the TERMINAL text.
- **Codespace container runs the code the tests prove, not necessarily the DB state you assume.** `docker compose exec backend php artisan test` runs against `pub_ads_mar_testing` (phpunit forces it) and reseeds per test — so green tests prove the CONTAINER CODE is current but say NOTHING about the live `pub_ads_mar` app DB. Verify runtime state against `pub_ads_mar` directly (psql / db:show), separately from the test run.
- **"The provided credentials are incorrect" on login = the DB is EMPTY or partially seeded, not a password bug.** Symptom (2026-07-18): every login (client2, client1, all roles) failed with that message after a container/volume reset. Root cause: `App\Models\User::count()` was `0` — the DB had been wiped and never reseeded (Codespace containers lose DB state on restart). DIAGNOSE FIRST, don't guess the password: `php artisan tinker --execute="echo App\Models\User::count();"` — if `0`, nothing is seeded. FIX: `php artisan migrate:fresh --seed` (runs `DatabaseSeeder` → creates ALL 7 demo users inline: admin/support/payments/provider1/provider2/client1/client2, each pw `password`, then calls `RolePermissionSeeder`) → `php artisan cache:clear` → optionally `db:seed --class=DisputeDemoSeeder`. CRITICAL: **`DisputeDemoSeeder` alone only creates client1/provider1/support/payments** — NOT client2/provider2/admin — so seeding *only* that seeder leaves those logins broken. Always run the full `--seed` first. (Note: `migrate:fresh --seed` output only lists sub-seeders invoked via `->call()` — you'll see `RolePermissionSeeder` but NOT the inline `User::create` calls; that's expected, the users ARE created.) **The DB is wiped on EVERY Codespace container restart — INDEPENDENT of what a patch touches.** The durable fix is the repo-root **`mvp-init.py`** (owner asked for it 2026-07-25): a self-healing, idempotent script that reseeds ONLY if the DB is empty, clears the permission cache, and loads the dispute demo. RULE (UNCONDITIONAL — do NOT gate it on "the patch touches backend" or "the instructions include tests"; that gating was the 2026-07-25 miss on p40/p41/p42): **EVERY set of patch-apply instructions ends with the login-readiness line below**, even for doc-only or frontend-only patches, because a restart between patches empties the DB regardless.
  ```bash
  # after applying + committing, make sure you can still log in (DB is wiped on restart):
  python3 mvp-init.py          # seeds only if empty · --fresh to force a clean reseed
  ```
  The moment the owner says "credentials incorrect", just tell them to run `python3 mvp-init.py`. (Manual equivalent, if the script is unavailable: `migrate:fresh --seed` → `cache:clear` → `db:seed --class=DisputeDemoSeeder`. `DisputeDemoSeeder` ALONE only creates client1/provider1/support/payments — never seed only that.) Logins: all 7 users use password `password` — client1@ · client2@ · provider1@ · provider2@ · support@ · payments@ · admin@pubads.test.
- **Docker from WSL: use `docker.exe`, and DISABLE BuildKit to build.** The WSL `docker` socket is `root:docker` and the user isn't in the `docker` group (and sudo needs a password), so use the Windows binary `/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe`. BuildKit context streaming is BROKEN on this machine — every `docker compose build`/`docker build` cancels at "transferring context" (~0.7s) whether foreground or background, even with all base images pre-pulled (plain `docker pull` works fine). FIX: classic builder. `export WSLENV=DOCKER_BUILDKIT:$WSLENV; DOCKER_BUILDKIT=0 docker.exe compose up -d --build`. The stack is project **publisher** (db/backend/nginx/frontend); API :8000, SPA :4200, db :5435. Backend auto-migrates on boot.


<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:ca08a54f -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --claim  # Claim work
bd close <id>         # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

## Session Completion

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd dolt push
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
<!-- END BEADS INTEGRATION -->
