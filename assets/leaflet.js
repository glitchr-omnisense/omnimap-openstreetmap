/**
 * omnimap's driver for OpenStreetMap: draws the description of
 * Omnimap\OpenStreetMap\OpenStreetMapProvider::describe() with Leaflet -
 * loaded from description.library (script, style and their integrity) unless
 * window.L is there already - over the description's tile server, its
 * attribution shown; a server of 512-pixel tiles is read as Leaflet reads
 * one (zoomOffset). Markers are circles: no image to fetch. Register it
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
  // A server of 512-pixel tiles numbers them 2^z a side at z: the map's zoom z is its z - 1 (zoomOffset),
  // and at the map's zoom 0 its one tile of zoom 0 is shown at half its size (minNativeZoom).
  const offset = tiles.tileSize > 256 ? Math.round(Math.log2(tiles.tileSize / 256)) : 0;
  const layer = { maxZoom: tiles.maxZoom, tileSize: tiles.tileSize, attribution: tiles.attribution };
  if (offset > 0) Object.assign(layer, { zoomOffset: -offset, minNativeZoom: offset });
  L.tileLayer(tiles.template, layer).addTo(map);
  (view.markers || []).forEach((marker) => {
    const dot = L.circleMarker([marker.position.lat, marker.position.lng], { radius: 6, color: '#ffffff', weight: 2, fillColor: marker.color || '#d33b3b', fillOpacity: 1 });
    if (marker.title) dot.bindTooltip(marker.title);
    dot.addTo(map);
  });
  map.whenReady(() => window.dispatchEvent(new Event('idle')));

  return map;
}

export default { draw };
