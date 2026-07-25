# PubAds — Design Dashboard

Interactive single-page dashboard built from `design.md`, rendering `design.json` (loaded via `fetch`). It must be **served over HTTP** — opening `design.html` as a `file://` URL blocks the fetch.

```
cd design && python3 -m http.server 8080
```

Then open http://localhost:8080/design.html

## Views
- **Specs** — filterable cards (by feature Fxx and kanban status) + search; click a card for the full body, kanban chips, and impacts/deps/ids.
- **Kanban** — backlog / wip / review / done columns; click a card for the same detail drawer.
- **Flow / ER / Classes** — mermaid diagrams from `diagrams.*` (mermaid loads from CDN, so these views need network access).
- **User Stories** — grouped by actor, searchable, click to expand.

The theme button cycles system → light → dark. All content comes from `design.json` at runtime (owned by the data agent); the UI files (`design.html`, `app.js`, `styles.css`) contain no baked-in data.
