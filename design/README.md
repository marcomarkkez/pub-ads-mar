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
