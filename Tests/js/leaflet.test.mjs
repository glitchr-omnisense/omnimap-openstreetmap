// assets/leaflet.js on node's test runner, over a stand-in for Leaflet that records what the driver asks of it.
import { test } from 'node:test';
import assert from 'node:assert/strict';

function fakeLeaflet() {
  const calls = { layers: [], view: null };
  const map = {
    setView(center, zoom) { calls.view = { center, zoom }; return map; },
    whenReady() { return map; },
  };
  const L = {
    map: () => map,
    tileLayer(template, options) { calls.layers.push({ template, options }); return { addTo: () => {} }; },
    circleMarker: () => ({ bindTooltip() {}, addTo() {} }),
  };
  return { L, calls };
}

globalThis.Event = class { constructor(type) { this.type = type; } };

const { draw } = await import('../../assets/leaflet.js');

function description(tileSize) {
  return {
    provider: 'openstreetmap',
    view: { id: 'map', center: { lat: 0, lng: 0 }, zoom: 1, markers: [], controls: true },
    library: {},
    tiles: { template: 'https://tiles.example/{z}/{x}/{y}.png', maxZoom: 19, tileSize, attribution: '' },
    options: { map: {} },
  };
}

test('256-pixel tiles: the view\'s zoom is the server\'s', async () => {
  const { L, calls } = fakeLeaflet();
  globalThis.window = { L, dispatchEvent() {} };
  await draw({}, description(256), {});

  assert.deepEqual(calls.layers[0].options, { maxZoom: 19, tileSize: 256, attribution: '' });
});

// https://leafletjs.com/reference.html#tilelayer-zoomoffset: a server of 512-pixel tiles numbered 2^z a side at z.
test('512-pixel tiles: read at the zoom below (zoomOffset -1), the zoom-0 tile scaled at zoom 0', async () => {
  const { L, calls } = fakeLeaflet();
  globalThis.window = { L, dispatchEvent() {} };
  await draw({}, description(512), {});

  assert.equal(calls.layers[0].options.tileSize, 512);
  assert.equal(calls.layers[0].options.zoomOffset, -1);
  assert.equal(calls.layers[0].options.minNativeZoom, 1, 'map zoom 0 asks the tiles of zoom 0, not -1');
});
