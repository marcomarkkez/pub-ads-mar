import * as L from 'leaflet';
import { environment } from '../../../environments/environment';

/**
 * The ONE place the base map's tiles are configured.
 *
 * Two components draw a Leaflet map (the client's Explore Spaces and the provider's
 * location picker) and each used to carry its own hard-coded `L.tileLayer(...)` call with
 * the OpenStreetMap URL spelled out inline. That is one provider decision written down
 * twice: changing basemaps meant editing two files and noticing there were two.
 *
 * It also failed SILENTLY. OpenStreetMap's tile servers are a donated, rate-limited public
 * service and they may refuse a client — and Leaflet's answer to a refused tile is to draw
 * nothing at all, so the map reads as a blank grey rectangle with no explanation anywhere
 * on the screen, in the console, or in the DOM (the <img> elements still carry
 * `leaflet-tile-loaded`). Marco hit exactly that during a walk and reasonably read it as
 * the app being broken: "algo pasó con los mapas, es un error quizás porque la API ya negó
 * el acceso" (2026-09-22).
 *
 * So this helper does two things the inline calls did not:
 *   1. reads the URL from `environment.tileUrl`, so swapping providers is one line; and
 *   2. reports an unreachable basemap to the caller, so the screen can SAY the base map is
 *      missing instead of showing an unexplained void.
 *
 * Nothing else about the map depends on the tiles: markers, clicks, popups and the search
 * all keep working over an empty background, which is why the notice is a notice and not
 * an error state that hides the map.
 */

/**
 * How many consecutive tile failures count as "the basemap is down".
 *
 * Not 1: a single tile can lose a race at the edge of a pan and come back on the next
 * request, and a banner that blinks on every such hiccup is noise. Any successful tile
 * resets the count, so a partially-drawn map is never reported as an outage.
 */
const FAILURES_BEFORE_REPORTING = 3;

/**
 * Add the configured base tiles to `map`.
 *
 * @param onAvailabilityChange Called with `true` when the basemap looks unreachable and
 *        with `false` as soon as a tile loads again. Called from Leaflet's own event
 *        callbacks, i.e. OUTSIDE Angular when the map was built inside
 *        `runOutsideAngular` — callers that write component state here must wrap it in
 *        `zone.run()` themselves, which is also where they get to decide what the screen
 *        says.
 */
export function addBaseTiles(map: L.Map, onAvailabilityChange?: (down: boolean) => void): L.TileLayer {
  const layer = L.tileLayer(environment.tileUrl, {
    attribution: environment.tileAttribution,
    maxZoom: 19,
  });

  if (onAvailabilityChange) {
    let consecutiveFailures = 0;
    let reportedDown = false;

    layer.on('tileerror', () => {
      consecutiveFailures++;
      if (reportedDown || consecutiveFailures < FAILURES_BEFORE_REPORTING) return;
      reportedDown = true;
      onAvailabilityChange(true);
    });

    layer.on('tileload', () => {
      consecutiveFailures = 0;
      if (!reportedDown) return;
      reportedDown = false;
      onAvailabilityChange(false);
    });
  }

  layer.addTo(map);

  return layer;
}
