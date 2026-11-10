# Agent Instructions

This project uses **bd** (beads) for issue tracking. Run `bd onboard` to get started.

## Positive reinforcements (do MORE of this)

Patterns the owner explicitly praised and wants repeated — a "do more of this" list.

- **Collapse parallel views/entities into ONE filtered structure.** When two screens or
  tables differ only by the *entity* they carry (e.g. Admin oversight had a
  `/oversight/conversations` view AND a `/oversight/tickets` view) and those entities are
  really the same concept, propose UNIFYING them into a single model with filters (by type,
  flag, object, status, participant) instead of maintaining parallel code paths. The owner
  flagged this instinct as repeat-worthy (2026-07-17). Generalizes to: prefer one primitive +
  attributes over N near-duplicate primitives; fewer tables/endpoints/screens, more filters.
- **Timestamp every decision change** (owner, 2026-07-17). When a decision is set or changed,
  stamp it `[owner YYYY-MM-DD]` in design/design.json and commit the design change FIRST (before the code),
  so the decision history is reviewable in git and `latest-decision-wins` resolves by date.

## Standing rules (owner, 2026-08-04)

- **One obvious option, not a menu.** "Cuando haya dudas siempre ofrece una opción obvia, si la
  opción es demasiado obvia sólo aplícala." Propose THE obvious reading; if it is overwhelmingly
  obvious, just apply it and say so afterwards. Never stop work to ask a question that has one
  sane answer.
- **Closed decisions stay closed.** "Cerremos decisiones y testeamos." A recorded decision is
  closed; re-opening it needs a NEW dated owner ruling. Every closed decision ships with a test
  that fails if it is quietly undone — that test is what stops the circling.
- **Everything ends as running code; polish comes from walks.** "Termina de codificar todo… a
  partir de ahí pulimos desde los walks solamente, nada de teoría en código seco." Nothing
  planned stays half-written. Once it runs, further changes come ONLY from a human WALK-n
  walkthrough finding a real problem — never from speculative design.
- **One circuit per fault, in this order — BR-16 (`sin-bucles-de-codigo-seco`).** "Si bien
  revisar el código en seco es bueno, produce bucles entre los agentes y se repiten las mismas
  preguntas constantemente." So: (1) a fault that recurs enters `errorHunt` as a PATTERN, not as
  an incident; (2) if the fix is already agreed, it goes into CODE in the same patch, without
  asking again; (3) then the todo; (4) then the todo hangs off a WALK. After that, hands off
  until there is a reproducible error or the human walkthrough finds the mismatch. Q35, Q38 and
  Q40 were the same question three times — that is the failure mode this rule exists to stop.
- **A WALK's references are the closure criterion.** Each walkthrough points at what it proves:
  `closes` (todos), `specs`, `ucs`, `br`, `eh`. While its `status` is `pending` or `failed`,
  everything it references is OPEN — however green the suite is. Only `passed` marks those
  references done; todos that existed solely for that walk can then be deleted. A `§` is never
  finished by reading code.
- **A walk may use the console, HTTP and cURL — BR-17.** A step can carry a `probe`: a
  browser-console check or a cURL call run during the walkthrough. It is still human — a person
  runs it — but it reaches what the screen cannot show. A 404 and a 403 look identical in the
  interface and are the whole of BR-3.

## Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --status in_progress  # Claim work
bd close <id>         # Complete work
bd sync               # Sync with git
```

## Landing the Plane (Session Completion)

**The agent pushes, and tells the owner** (owner rule [owner 2026-11-10], supersedes 2026-08-02).
*"Modifica las reglas para que tú hagas push de ahora en adelante siempre y cuando me informes."*
The 403 that made pushing impossible from a Claude Code (web) session is gone (verified
2026-11-10: `b21ac2d` pushed to `claude/charming-franklin-7wmp4i` and GitHub accepted it).
Every push is announced in the reply: branch, commit hash, one line of what it carries.

**When ending a work session:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds (design/WORKFLOW.md §6)
3. **Update issue status** - Close finished work, update in-progress items
4. **Commit and push to `mvp-build`, then inform** - numbered `pNN — …` commit message as before,
   `git push origin mvp-build`, and say in the reply: branch, hash, what it carries. If the push
   fails (403 or anything else), fall back to the patch and say why:
   ```bash
   git add -A && git diff --cached --binary > pNN.patch && git reset
   ```
5. **Clean up** - Clear stashes
6. **Hand off** - Provide context for next session: what landed, what is pending, what needs a
   decision from the owner


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

See **Landing the Plane** above — that section is authoritative. Beads ships a "Session Completion"
block here that mandates `git push`; it was removed on 2026-08-02 by owner rule. Pushing is now the
agent's job (owner 2026-11-10), but the rule above (push AND inform) governs, not the beads block:
if `bd` regenerates it, delete it again.
<!-- END BEADS INTEGRATION -->
