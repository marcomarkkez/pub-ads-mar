# Agent team — pub-ads-mar MVP

Three roles, each pinned to a model via its `model:` frontmatter. The orchestrator (main
thread) drives the workflow; each agent starts cold, so it is handed an explicit brief.

| Agent          | Model            | Role                                              |
|----------------|------------------|---------------------------------------------------|
| `plan-opus48`  | `claude-opus-4-8`| Plan / design / scope / trade-offs (read-only)    |
| `build-opus5`  | `claude-opus-5`  | Implement code + infra, run tests, make the patch |
| `review-opus5` | `claude-opus-5`  | Review the diff before it ships (read-only)       |

## Flow per feature
`plan-opus48` → (owner confirms) → `build-opus5` → `review-opus5` → deliver `pNN.patch`.

## Notes
- Agent types load at session start — these are active from your next session on.
- Model pinning lives in the frontmatter (the Agent tool's inline `model` param only knows
  generic opus/sonnet/haiku/fable and cannot distinguish 4.8 from 5).
- Cold start: agent teams pay off on parallel, self-contained work; tight iterative work
  coupled to accumulated context is better kept in the main thread.
