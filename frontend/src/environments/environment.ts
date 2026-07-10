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
  googleMapsApiKey: 'YOUR_GOOGLE_MAPS_API_KEY',
  what3wordsApiKey: 'YOUR_W3W_API_KEY',
};
