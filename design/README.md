# PubAds — Design (canonical) + Dashboard

`design.json` **is** the canonical design reference for the whole app — specs, decisions,
schema, routes, the role/object graph, user stories, lineage and the sprint backlog. It is not
generated from anything: it is the source.

> **Folded in on 2026-08-02:** `design.md` and `.claude/todos/mvp-sprint.json` were merged into
> this file and deleted. The full prose of every `design.md` section is preserved **verbatim** in
> `specs[].body`; everything from `mvp-sprint.json` lives under `mvpSprint`, with ids carrying an
> `mvp-sprint:` prefix so their provenance stays readable (the bare code is kept in `code` /
> `mvpSprintId` so existing cross-links still resolve).

## Reading it

Section references in code comments still say `design.md §7`. **The section number is still
valid** — it is `specs[].id` in this file. New comments should say `design.json §7`.

```
jq '.specs[] | select(.id=="7") | .body' design/design.json      # one spec, full prose
jq -r '.specs[] | "\(.id)\t\(.kanban.status)\t\(.title)"' design/design.json   # the board
jq -r '.mvpSprint.specBacklog.domains | to_entries[] | .value[]' design/design.json
```

## Structure

| key | what |
|---|---|
| `meta` | title, preamble (the old design.md header rules), the section-ref convention |
| `notation` | §0 — how to read the `kanban:` state of each spec |
| `specs[]` | §1–§21, full prose in `body`, state in `kanban` |
| `userStories[]` | §19 — the 43 UCs, per-actor |
| `endpoints[]` | §17 — the backend endpoint map, structured |
| `schema` | §15 — entities |
| `diagrams` | flow / ER / classes (mermaid definitions) |
| `todos[]` | the Fxx feature index, enriched with the mvp-sprint feature-review notes |
| `lineage` | FL/ER/CL id registry + the parked UC/todo proposals |
| `groups` | cross-cutting work fronts — the slug registry (membership lives on each object) |
| `glossary` | what every id prefix means (`§`, `UC-`, `Fxx`, `Bn`, …) + the domain vocabulary |
| `openQuestions` | decisions only the owner can make: `items` are open, `resolved` is one line each |
| `walkthroughs` | `WALK-n` — the human UI recorridos that gate a feature reaching `done` |
| `mvpSprint` | the granular build backlog, checklist, ui-phase history, session notes |

### Groups — one work front across all three categories

A spec (`§N`), a user story (`UC-N`) and a todo (`Fxx`) are three *kinds* of object, not three
buckets of work. A **group** cuts across them: it answers "what are we working on right now".

- The slug is declared once in `.groups.registry[]` (slug, label, description, date).
- Membership lives in each object's own `groups: []` array — one source of truth, and an object can
  belong to several fronts.
- It is a separate field from `todos[].tags` on purpose: those tags (`F08`, `§10`, …) feed the
  dashboard's cross-link graph, and a work-front slug dropped in there would wire every object in
  the front to every other one.

```bash
# everything in a front, whatever kind of object it is
jq '[.specs[],.userStories[],.todos[]]
    | map(select((.groups//[]) | index("mensajeria-soporte"))) | map(.id)' design/design.json
```

In the dashboard the group shows as a filled pill on kanban cards, spec rows, todo rows, story rows
and in every drawer, and the slug is searchable in all four views — typing `mensajeria-soporte` in
any search box narrows that view to the front. The copied prompt reference carries a `grupo:` line
too, so a pasted `§10` is unambiguous even when several fronts touch the same spec.

### Glossary — the id vocabulary, written down once

`§10`, `UC-21`, `F09`, `B9`, `C08`, `WALK-1`, `Q50`, `AD-delguard-08` are **eight different id
axes**, and nothing used to say so. `.glossary.idPrefixes[]` explains each one: what it means, an
example, and which key it lives under. `.glossary.terms[]` does the same for domain words (*proof*,
*hold*, *flag*, *apply-scope*, *eagle-eye*, …).

Retired vocabulary is **marked, not deleted** — `Conversation`, `MessageController`, *ticket dual*
and the *48h re-upload window* all still appear in dated session logs, and a reader needs to know
those words describe history rather than current behaviour.

The one trap worth repeating here: **an `Fxx` carries two independent statuses**.
`.todos[].status` answers *is the work closed?*; `.mvpSprint.featureReview` answers *is the spec
clear?*. `in_progress` + `resolved` is a legitimate combination — it means the spec has no open
questions but the work is still gated (usually by a `WALK`).

### Open questions — what is genuinely undecided

`.openQuestions.items[]` holds only the questions that are **still open**; while one sits there, no
agent should invent the answer or build on top of it. When it is answered it leaves `items` and
leaves one line behind in `.openQuestions.resolved[]`, so nobody re-litigates a settled decision.
The long reasoning stays dated in `history.md` — this block is the index, not the record.

### Walkthroughs — the human recorridos

`.walkthroughs.items[]` are verifications a **person** performs against the running UI. They exist
because a green suite proves a function responds, not that a screen is usable or that a flow can be
completed end to end. The rule is hard: **a feature does not reach `done` while the `WALK` that
closes it is `pending`** — which is exactly why `F08` and `F09` are still open with no code left to
write. Each step names a role, a concrete action and what must be observed.

## Dashboard

The dashboard renders `design.json` via `fetch`, so it must be **served over HTTP** — opening
`design.html` as a `file://` URL blocks the fetch.

```
cd design && python3 -m http.server 8080
```

Then open http://localhost:8080/ — la raíz redirige al tablero (`index.html` → `design.html`), que
es lo que hace que el aviso de puerto reenviado de Codespaces caiga en el sitio correcto en vez de
en el listado de ficheros. `http://localhost:8080/design.html` sigue funcionando igual.

Si el comando muere con `Address already in use`, el puerto ya lo tiene otro proceso —
probablemente un `http.server` anterior sirviendo otra carpeta, que es peor que no arrancar
porque contesta: `python3 -m http.server 8081` y listo, o `kill $(lsof -ti:8080)`.

### Views
- **Specs** — filterable cards (feature Fxx, kanban status) + search; click for the full body,
  kanban chips and impacts/deps/ids. Toggles between list and kanban layout.
- **Todos** — the Fxx feature index; the drawer shows the mvp-sprint feature-review history.
- **Flow / ER / Classes** — mermaid diagrams from `diagrams.*` (vendored, no network needed).
- **User Stories** — grouped by actor, searchable.
- **Sprint** — the granular backlog by domain, colour-coded built / partial / missing.
- **Glosario** — the id prefixes and the domain vocabulary, searchable; retired terms flagged.
- **Preguntas** — open questions first, then the resolved log with the decision on each.
- **Recorridos** — each `WALK` as a card: its steps per role, action → what must be observed,
  and which `Fxx` it closes.

The theme button cycles system → light → dark. The UI files (`design.html`, `app.js`,
`styles.css`) contain no baked-in data.

### Editing the spec — the prompt tray

The dashboard is **read-only**: it is three static files and a `fetch`, with no server behind
it, so nothing in the UI can write back to `design.json`. Edits happen where the file lives —
in the repo, through the prompt.

What the UI does instead is hand over an unambiguous **citation** of whatever you are looking
at, so you never have to describe it by hand:

- **`＋`** on any spec row, kanban card, todo or user story adds it to the tray (bottom bar).
- **`⧉ Copiar ref`** in the drawer copies just the open item.
- **`⧉ Copiar para el prompt`** copies the whole tray.

The copied block carries the id, the title, the *live* status/features and the `jq` path that
locates the node, then leaves a `Cambio solicitado:` line for you to finish:

```
Contexto — design/design.json (Design Dashboard):

1. §10 · Chats, Objetos & Flags
   status: wip · weight: XL · features: F08, F09
   jq: '.specs[] | select(.id=="10")'

Cambio solicitado:
```

Paste it into the prompt, write the change after the colon, and the edit lands on the exact
node — no ambiguity about *which* §10 or *which* UC. The tray survives reloads
(`sessionStorage`) and stays clickable while a drawer is open, so you can collect several
items across views before copying.

`jq` is the standard command-line JSON tool (`apt install jq`) — the path is included so the
same node can be inspected from a terminal: `jq '.specs[] | select(.id=="10")' design/design.json`.
