/**
 * One null-safe label for `Space.type`, shared by the three screens that render it.
 *
 * [bug 2026-08-15] Each screen carried its own private copy:
 *
 *     formatType(type: string): string {
 *       return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
 *     }
 *
 * `spaces.type` is NULLABLE and rows with a null type exist in the demo data
 * (DisputeDemoSeeder mints "Demo Dispute Billboard" with no type and no price), so the
 * call threw `TypeError: Cannot read properties of null (reading 'replace')` — and it
 * threw from inside a template EXPRESSION, i.e. during Angular's update pass.
 *
 * That is much worse than one blank cell. The update pass is one linear run of bindings
 * per view, so everything AFTER the throw is silently skipped:
 *   · the `[routerLink]` on View/Edit never resolved -> `<a class="btn btn-sm">View</a>`
 *     with NO href, a button that does nothing,
 *   · the `@if (space.delete_scheduled_at)` never picked a branch -> the delete control
 *     was simply absent ("no veo forma de borrar el espacio"),
 *   · and because the throw escapes `ApplicationRef.tick()`, the navbar — which is
 *     refreshed AFTER the router-outlet content in the same tick — stopped repainting
 *     too, so clicking the avatar flipped `menuOpen` and rendered nothing: no logout.
 *
 * A missing datum must never be able to do that. The label is computed defensively and
 * lives in ONE place so the next copy cannot re-introduce the assumption.
 */

/** Shown when the API has no type for a listing. Deliberately not "Other": `other` is a
 *  real, chosen value, and "we were not told" is a different fact. */
export const UNKNOWN_SPACE_TYPE_LABEL = 'Unspecified';

/** `big_screen` -> `Big Screen`; null / undefined / '' -> `Unspecified`. Never throws. */
export function formatSpaceType(type: string | null | undefined): string {
  if (typeof type !== 'string' || type === '') {
    return UNKNOWN_SPACE_TYPE_LABEL;
  }
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/** The `type-*` modifier class for the badge, with a slot for the untyped case so the
 *  DOM never shows `class="badge type-null"`. */
export function spaceTypeClass(type: string | null | undefined): string {
  return 'badge type-' + (typeof type === 'string' && type !== '' ? type : 'unknown');
}
