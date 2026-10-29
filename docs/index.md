# omnimap/openstreetmap

OpenStreetMap for [omnisense/omnimap](https://github.com/glitchr-omnisense/omnimap): the live map
drawn by [Leaflet](https://leafletjs.com) in the browser over a raster tile server of your choice
(`assets/leaflet.js`, a driver for omnimap's `omnimap.js`), a view as an image made on the server
from the same tiles (`Omnimap\Static\TileStitcher`: fetched with the application's User-Agent,
kept as long as the server's Cache-Control allows, the markers and the attribution drawn), the
tiles themselves (`TileSourceInterface`), and geocoding through geocoder-php's `Nominatim`
provider (one call a second at most per process, answers kept).

| Operation | Where | Address (defaults) |
|---|---|---|
| live map (browser) | Leaflet 1.9.4 from unpkg, then the tiles | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js` and `.css`; `https://tile.openstreetmap.org/{z}/{x}/{y}.png` |
| image (server) | the tile server | `GET https://tile.openstreetmap.org/{z}/{x}/{y}.png`, the tiles the view covers |
| geocode, reverse (server) | Nominatim, through `geocoder-php/nominatim-provider` | `GET https://nominatim.openstreetmap.org/search?format=jsonv2&q=...&addressdetails=1&extratags=1&limit=...` / `/reverse?...` |

Every address is an option: the OpenStreetMap Foundation's servers are the defaults, for
development and modest use; their policies ask heavy users for another provider or their own
server.

## Installation

```sh
composer require omnimap/openstreetmap
```

Requires PHP 8.2, `omnisense/omnimap` (1.x), `geocoder-php/nominatim-provider` (^5.7),
`psr/http-client`, `psr/simple-cache` and `symfony/http-client-contracts`; `ext-gd` for images
made of tiles. Images need a Symfony HTTP client (`symfony/http-client`), geocoding a PSR-18
client (Symfony's `Psr18Client` with `nyholm/psr7`, or any other). In the browser,
`assets/leaflet.js` is registered with `omnimap.js` under `openstreetmap`. With omnibridge/symfony
or omnibridge/laravel, the factory is registered as soon as the package is installed.

## Credentials

None: the Foundation's tile server and Nominatim need no key. What they need instead is a
**User-Agent** naming the application and a contact (`user_agent`): their policies block a
library's default one, and this package refuses to fetch a tile or to geocode without it. From
web pages, a **Referer** (`referer`) as well. A commercial tile provider's key, when it has one,
goes in the `tiles` URL (which the visitor's browser then sees).

## Options

`new OpenStreetMapFactory(?HttpClientInterface $http = null, ?Psr\Http\Client\ClientInterface $psr18 = null, ?Psr\SimpleCache\CacheInterface $cache = null)`;
no option is required.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `tiles` | string | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` (`OpenStreetMapFactory::OSM_TILES`) | no | the tile URL, `{z}`, `{x}`, `{y}` in it, `{s}` for a server's subdomains |
| `attribution` | string (HTML) | `&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors` (`OSM_ATTRIBUTION`) | no | shown on the live map, printed beside a kept picture, drawn on an image |
| `max_zoom` | int | `19` | no | the deepest zoom asked |
| `tile_size` | int | `256` | no | the tiles' size in pixels; 512 for a server numbering 512-pixel tiles 2^z a side at zoom `z`, read at the zoom below the view's (Leaflet's `zoomOffset: -1`) |
| `user_agent` | string | `''` | to fetch tiles and to geocode | sent with every request the server makes |
| `referer` | string | `''` | no | sent with them when set |
| `nominatim` | string | `https://nominatim.openstreetmap.org` (`NOMINATIM`) | no | Nominatim's URL; empty: no geocoding |
| `nominatim_interval` | float | `1.0` | no | seconds between two geocoder calls of a process |
| `geocoder_ttl` | int | `2592000` | no | seconds a geocoding answer is kept in `$cache` |
| `leaflet_script` | string | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js` | no | where the browser gets Leaflet; serve your own copy to keep it off a CDN |
| `leaflet_script_integrity` | string | `sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=` | no | its integrity hash; empty: none |
| `leaflet_style` | string | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css` | no | |
| `leaflet_style_integrity` | string | `sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=` | no | |
| `background` | string | `#aad3df` | no | the colour above and below the world on an image |

A view's own options, under `MapView::options('openstreetmap')`: `map`, Leaflet's map options as
JSON. Nothing else of a view's options is read.

## Capabilities

| Capability | Supported |
|---|---|
| renderer (`MapRendererInterface`) | yes, always |
| static map (`StaticMapInterface`) | with the HTTP client and a `user_agent` |
| geocoder (`GeocoderInterface`) | with the PSR-18 client, a `user_agent` and a `nominatim` URL |
| tiles (`TileSourceInterface`) | yes, always (fetching one needs `user_agent`: `headers()`) |

Browser hosts: the tile URL's host and the Leaflet script's (`tile.openstreetmap.org`,
`unpkg.com` by default), a host with `{s}` given as the three hosts the browser reaches
(`{s}.tiles.example.org`: `a.tiles.example.org`, `b.tiles.example.org`, `c.tiles.example.org`,
Leaflet's default subdomains, the ones `url()` takes); cookies: declared `false`. Terms:
`https://operations.osmfoundation.org/policies/tiles/`,
`https://operations.osmfoundation.org/policies/nominatim/`, `https://www.openstreetmap.org/copyright`.

## Behaviour

**Description.** `describe()` returns `provider: openstreetmap`, `consent: MAP`, the view,
`library: {script, scriptIntegrity, style, styleIntegrity}` (empty values left out),
`tiles: {template, maxZoom, tileSize, attribution}`, `options: {map}` (`{}` when the view sets
none) and `attribution`.

**The live map (`assets/leaflet.js`).** Loads Leaflet's stylesheet and script from `library`
(with `integrity` and `crossorigin="anonymous"` when an integrity is set) unless `window.L`
exists; draws `L.map(element, {zoomControl: controls, attributionControl: true, ...map})` at the
view's centre and zoom, one tile layer (`template`, `maxZoom`, `tileSize`, the attribution; for
512-pixel tiles `zoomOffset: -1` and `minNativeZoom: 1`, so that the map's zoom 0 shows the tile of
zoom 0 at half its size); each
marker as a circle (radius 6, a white ring of 2, filled with its colour or `#d33b3b`, its title as
a tooltip: no marker image to fetch); dispatches `idle` on `window` once the map is ready.

**Fingerprint.** `openstreetmap|` and the JSON of the tile URL template, centre, zoom, markers and
controls: another tile server makes another picture.

**Tiles.** `url($zoom, $x, $y)` fills the template; `{s}` becomes `a`, `b` or `c` by
`(x + y) mod 3`, always the same for a tile. `headers()` is `User-Agent` and, when set, `Referer`;
without a `user_agent` it throws.

**Image.** `TileStitcher` over this provider, with the factory's cache and `background`: the tiles
the view covers, fetched side by side with `headers()`, each kept for max-age plus
stale-while-revalidate of its Cache-Control (25 hours + 7 days as tile.openstreetmap.org served
them on 2026-10-23), or 7 days without one; the world repeating east and west; markers as dots;
the attribution, its tags stripped, drawn in the bottom-right corner. A PNG of the size asked
(times the scale; scale 2 takes the next zoom's tiles).

**Geocoding.** geocoder-php's `Nominatim($psr18, nominatim, user_agent, referer)` behind
`Omnimap\Geocoding\GeocoderPhp`: at most one call every `nominatim_interval` seconds in a
process, answers kept `geocoder_ttl` seconds. The locale is the call's (`accept-language`); a
place's `label` is Nominatim's display name, its `id` the OSM object (`relation/358674`),
`providedBy` `nominatim`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

// Recorded answers; HttpClient::create() and new Psr18Client(HttpClient::create()) in production.
$tiles = new RecordedHttpClient(PlainTiles::answer());
$nominatim = new MockHttpClient(static function (string $method, string $url, array $options) {
    echo 'GET ', $url, "\n    ", $options['normalized_headers']['user-agent'][0], "\n";

    return new MockResponse('[{"place_id":1,"osm_type":"relation","osm_id":358674,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. http://osm.org/copyright","lat":"34.6937569","lon":"135.5014539","category":"boundary","type":"administrative","display_name":"大阪市, 大阪府, 日本","address":{"city":"大阪市","state":"大阪府","country":"日本","country_code":"jp"},"boundingbox":["34.5859","34.7741","135.3549","135.6266"]}]', ['response_headers' => ['content-type' => 'application/json']]);
});

$osm = (new OpenStreetMapFactory($tiles, new Psr18Client($nominatim), new ArrayCache()))->create([
    'tiles' => 'https://{s}.tiles.example.org/{z}/{x}/{y}.png',
    'attribution' => '&copy; OpenStreetMap contributors, tiles by Example',
    'user_agent' => 'MySite/1.0 (+https://example.org; maps@example.org)',
    'referer' => 'https://example.org/',
]);

echo implode(',', $osm->capabilities()->contracts()), ' ', implode(',', $osm->capabilities()->browserHosts), "\n";
echo $osm->url(12, 3592, 1622), "\n";

$view = new MapView(new LatLng(34.6937, 135.5023), 11, [new Marker(new LatLng(34.7025, 135.4959), 'Umeda')]);
$image = $osm->image($view, new Size(600, 400));
echo $image->mimeType, ' ', $image->size, ' from ', count($tiles->requests), " tiles\n";

foreach ($osm->geocode('Osaka, Japan', 'ja', 1) as $place) {
    echo $place->label, ' | ', $place->position, ' | ', $place->id, ' | ', $place->address->countryCode, ' | ', $place->providedBy, "\n";
}
```

```
renderer,staticMap,geocoder,tiles a.tiles.example.org,b.tiles.example.org,c.tiles.example.org,unpkg.com
https://a.tiles.example.org/12/3592/1622.png
image/png 600x400 from 12 tiles
GET https://nominatim.openstreetmap.org/search?format=jsonv2&q=Osaka%2C%20Japan&addressdetails=1&extratags=1&limit=1&accept-language=ja
    User-Agent: MySite/1.0 (+https://example.org; maps@example.org)
大阪市, 大阪府, 日本 | 34.6937569,135.5014539 | relation/358674 | JP | nominatim
```

(run on 2026-10-25 in omnimap's harness, on the recorded answers above: nothing reached a tile
server or Nominatim; the Nominatim answer is written after the one recorded on 2026-10-23 in
`Tests/Fixtures/nominatim-osaka.json`)

In the page, beside omnimap's script:

```js
import { register, mountAll } from '.../omnimap/assets/omnimap.js';
import leaflet from '.../omnimap/openstreetmap/assets/leaflet.js';

register('openstreetmap', leaflet);
mountAll(document, { consent: (feature, load) => Consent.use(feature, {}, load) });
```

In Symfony (omnibridge/symfony):

```yaml
omnimap:
    provider: openstreetmap
    secret: '%env(MAP_SECRET)%'
    providers:
        openstreetmap:
            user_agent: 'MySite/1.0 (+https://example.org; maps@example.org)'
            tiles: '%env(MAP_TILES)%'              # your tile provider or server in production
    cache:
        drive: local.uploads
```

## Errors

- Tiles: a status other than 200 is a `ProviderException` (`OpenStreetMap answered 503 for
  https://tile.openstreetmap.org/12/3592/1621.png.`), a body that is not an image too; a transport
  failure an `UnreachableException`; the other tiles still on their way are cancelled. No HTTP
  client or no `user_agent`: `image()` throws `NotSupportedException` (`staticMap` is off);
  `headers()` without a `user_agent` an `InvalidConfigException`.
- Geocoding: geocoder-php's errors are `ProviderException`s naming `nominatim` and the question, a
  network failure an `UnreachableException`; no PSR-18 client, no `user_agent` or no `nominatim`
  URL a `NotSupportedException`. Nothing found is an empty list.

## Verified, and not

Tested offline in omnimap's harness (PHP 8.4): `Tests/OpenStreetMapContractTest.php`, omnimap's
conformance suite on recorded answers (an HTTP error or a proxy page among the tiles a
`ProviderException`, the other tiles dropped); `Tests/OpenStreetMapProviderTest.php`: the
description and Leaflet's integrity hashes (computed from unpkg's files on 2026-10-23), a tile
server of one's own, no tile fetched without a User-Agent, an image made through a recorded client
with the User-Agent sent, geocoding with Nominatim's answer for "Osaka, Japan" recorded on
2026-10-23 (one request, then the cache); `Tests/BareTest.php`: every class loading with the
package's requirements alone.

Verified live (`docker compose run --rm omnimap live`, `Tests/LiveTest.php`, 2026-10-23): an image
of Kyoto made of tile.openstreetmap.org's tiles (`cache-control: max-age=91080,
stale-while-revalidate=604800, stale-if-error=604800`) and one Nominatim query, with the package's
User-Agent. On lesringostars (2026-10-23): the page's map made by the server from these tiles and
kept in omnimap's cache, no request to the tile server from the visitor's browser.

Not verified: `assets/leaflet.js` drawn in a browser (its tile layer is tested over a stand-in
Leaflet, `Tests/js/leaflet.test.mjs`); another tile provider; 512-pixel tiles from a real server
(tested on tiles drawn by the tests).

## Responsibility

An independent, unofficial client, affiliated with the OpenStreetMap Foundation in no way. The map
data is © OpenStreetMap contributors, under the ODbL. Whoever runs it complies with the tile and
Nominatim usage policies (https://operations.osmfoundation.org/policies/tiles/,
https://operations.osmfoundation.org/policies/nominatim/): see the README's "The policies" section,
which gives what they ask and what this package does about it.
