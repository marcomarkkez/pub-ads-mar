// Resolves the API origin from the current browser location so the same build
// works on plain localhost and on GitHub Codespaces (which serves the frontend
// and backend on different -PORT subdomains). See claude.md → GitHub Codespaces.
function resolveApiUrl(): string {
  if (typeof window === 'undefined') {
    return 'http://localhost:8000/api';
  }
  const { hostname, protocol } = window.location;
  const codespacesMatch = hostname.match(/^(.*)-4200\.(app\.github\.dev|githubpreview\.dev)$/);
  if (codespacesMatch) {
    return `${protocol}//${codespacesMatch[1]}-8000.${codespacesMatch[2]}/api`;
  }
  return 'http://localhost:8000/api';
}

export const environment = {
  production: false,
  apiUrl: resolveApiUrl(),
  // Map provider: 'leaflet' = Leaflet + OpenStreetMap (current)
  // Switch to 'google' to use Google Maps (set googleMapsApiKey too)
  mapProvider: 'leaflet' as 'leaflet' | 'google',
  // Base map tiles — read by shared/map/base-tiles.ts and by NOTHING else, so this is the
  // single line to change to move off OpenStreetMap. OSM's tile servers are a donated,
  // rate-limited public service that may refuse a client outright; when that happens the
  // map draws an unexplained empty background, so both map screens now say so out loud.
  // Any {z}/{x}/{y} XYZ provider works here (Carto, Stadia, a self-hosted tileserver…) —
  // change tileAttribution with it, the licence usually requires it.
  tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  tileAttribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  googleMapsApiKey: 'YOUR_GOOGLE_MAPS_API_KEY',
  what3wordsApiKey: 'YOUR_W3W_API_KEY',
};
