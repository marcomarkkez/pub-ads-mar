export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api',
  // Map provider: 'leaflet' = Leaflet + OpenStreetMap (current)
  // Switch to 'google' to use Google Maps (set googleMapsApiKey too)
  mapProvider: 'leaflet' as 'leaflet' | 'google',
  googleMapsApiKey: 'YOUR_GOOGLE_MAPS_API_KEY',
  what3wordsApiKey: 'YOUR_W3W_API_KEY',
};
