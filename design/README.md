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
| `mvpSprint` | the granular build backlog, checklist, ui-phase history, session notes |

## Dashboard

The dashboard renders `design.json` via `fetch`, so it must be **served over HTTP** — opening
`design.html` as a `file://` URL blocks the fetch.

```
cd design && python3 -m http.server 8080
```

Then open http://localhost:8080/design.html

### Views
- **Specs** — filterable cards (feature Fxx, kanban status) + search; click for the full body,
  kanban chips and impacts/deps/ids. Toggles between list and kanban layout.
- **Todos** — the Fxx feature index; the drawer shows the mvp-sprint feature-review history.
- **Flow / ER / Classes** — mermaid diagrams from `diagrams.*` (vendored, no network needed).
- **User Stories** — grouped by actor, searchable.
- **Sprint** — the granular backlog by domain, colour-coded built / partial / missing.

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
