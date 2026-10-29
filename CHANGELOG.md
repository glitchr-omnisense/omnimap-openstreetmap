# Changelog

## [Unreleased]

### Fixed

- `capabilities()->staticMap` is off without a `user_agent`, and `image()` then throws
  `NotSupportedException` before any request: it was on as soon as an HTTP client was given, and
  `image()` threw `InvalidConfigException` from `headers()`, so omnibridge's `cache.make: auto`
  tried to make pictures it could not.
- `capabilities()->browserHosts` gives a tile URL with `{s}` as the three hosts the browser reaches
  (`a.`, `b.`, `c.`, Leaflet's default subdomains, the ones `url()` takes): it listed the literal
  `{s}.tiles.example.org`.
- `assets/leaflet.js` draws a 512-pixel tile source (`tile_size: 512`) with `zoomOffset: -1` and
  `minNativeZoom: 1`, as Leaflet reads a server numbering 512-pixel tiles 2^z a side at `z`: the
  map showed the wrong part of the world. The image made on the server follows omnisense/omnimap's
  `TileStitcher`, fixed the same way.

### Added

- `Tests/js/leaflet.test.mjs`: the driver's tile layer, on node's test runner (omnimap's harness,
  `docker compose run --rm js`).
