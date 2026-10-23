/**
 * omnimap's driver for OpenStreetMap: draws the description of
 * Omnimap\OpenStreetMap\OpenStreetMapProvider::describe() with Leaflet -
 * loaded from description.library (script, style and their integrity) unless
 * window.L is there already - over the description's tile server, its
 * attribution shown. Markers are circles: no image to fetch. Register it
 * with omnimap.js: register('openstreetmap', leaflet); omnimap.js hands it its
 * loadScript() and loadStyle().
 */
export async function draw(element, description, { loadScript, loadStyle }) {
  const library = description.library || {};
  if (!window.L) {
    if (library.style) loadStyle(library.style, { integrity: library.styleIntegrity, crossorigin: library.styleIntegrity ? 'anonymous' : null });
    if (!library.script) throw new Error('omnimap: no Leaflet to load.');
    await loadScript(library.script, { integrity: library.scriptIntegrity, crossorigin: library.scriptIntegrity ? 'anonymous' : null });
  }
  const L = window.L;
  const view = description.view;
  const tiles = description.tiles;

  const map = L.map(element, { zoomControl: view.controls, attributionControl: true, ...((description.options || {}).map || {}) })
    .setView([view.center.lat, view.center.lng], view.zoom);
  L.tileLayer(tiles.template, { maxZoom: tiles.maxZoom, tileSize: tiles.tileSize, attribution: tiles.attribution }).addTo(map);
  (view.markers || []).forEach((marker) => {
    const dot = L.circleMarker([marker.position.lat, marker.position.lng], { radius: 6, color: '#ffffff', weight: 2, fillColor: marker.color || '#d33b3b', fillOpacity: 1 });
    if (marker.title) dot.bindTooltip(marker.title);
    dot.addTo(map);
  });
  map.whenReady(() => window.dispatchEvent(new Event('idle')));

  return map;
}

export default { draw };
