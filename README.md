# omnimap/openstreetmap

[omniscient/omnimap](https://github.com/glitchr-studio/omnimap)'s OpenStreetMap, under any PHP
host:

- **the live map** - `OpenStreetMapProvider::describe()` for `assets/leaflet.js`: Leaflet
  (1.9.4 from unpkg with its integrity hashes by default - serve your own copy to keep the
  visitor's browser off a CDN) over a tile server, its attribution shown, markers as circles;
- **a view as an image** - `image()`: the tiles the view covers, fetched by the server with the
  application's User-Agent, kept as long as the tile server's Cache-Control allows, put together,
  the markers and the attribution drawn (`Omnimap\Static\TileStitcher`);
- **geocoding** - geocoder-php's `Nominatim` provider behind omnimap's `GeocoderInterface`: one
  call a second at most per process, answers kept 30 days.

```php
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Registry;
use Symfony\Component\HttpClient\{HttpClient, Psr18Client};

$http = HttpClient::create();
$registry = new Registry([new OpenStreetMapFactory($http, new Psr18Client($http), $cache)], [
    'maps' => ['factory' => 'openstreetmap', 'options' => [
        'tiles' => 'https://tiles.example.org/{z}/{x}/{y}.png',   // your provider or your own server in production
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'user_agent' => 'MySite/1.0 (+https://example.org; contact: maps@example.org)',
    ]],
]);
```

Options: `tiles`, `attribution`, `max_zoom` (19), `tile_size` (256), `user_agent`, `referer`,
`nominatim` (its URL; empty: no geocoding), `nominatim_interval` (1 s), `geocoder_ttl`,
`leaflet_script`, `leaflet_style` and their `_integrity`, `background` (#aad3df, above and below
the world on an image).

## The policies, as read on 2026-10-23

**Tiles** (<https://operations.osmfoundation.org/policies/tiles/>): use exactly
`https://tile.openstreetmap.org/{z}/{x}/{y}.png`; show the attribution visibly
("© OpenStreetMap contributors"); send a User-Agent naming the application (a library's default
is blocked) and, from web pages, a Referer; cache tiles according to the HTTP caching headers, or
at least 7 days; no bulk download, no prefetch, no offline use; capacity is limited and access
may be withdrawn without notice - heavy use belongs on another provider or your own server
(<https://wiki.openstreetmap.org/wiki/Raster_tile_providers>, <https://switch2osm.org>); a
caching proxy in front of it is "generally not recommended" and must identify itself and honour
the headers. What this package does: the tile URL is an option (the default is the Foundation's,
for development and modest use); the server refuses to fetch a tile without a `user_agent`; tiles
are cached for max-age plus stale-while-revalidate (25 h + 7 days as served on 2026-10-23: the
headers read `max-age=91080, stale-while-revalidate=604800`); a picture of a view is made once
from the tiles of that view only; the attribution is drawn on the picture and printed beside it.

**Nominatim** (<https://operations.osmfoundation.org/policies/nominatim/>): at most 1 request per
second, a User-Agent or Referer identifying the application, results cached, no autocomplete, no
systematic queries, the attribution shown; switching to another server must be possible without a
software update (the `nominatim` option). What this package does: the pace and the cache above;
no autocomplete is offered.

## Verified, and not

- Verified (the core's harness, PHP 8.4): omnimap's conformance suite (`Tests/OpenStreetMapContractTest.php`, `Omnimap\Testing\ProviderContractTests`), offline, on recorded answers; an HTTP error or a proxy page among the tiles is a ProviderException, the other tiles dropped (before: the unread ones threw a ClientException on destruction).
- Verified (the core's harness, PHP 8.4): the description and Leaflet's integrity hashes
  (computed from unpkg's files on 2026-10-23); a tile server of one's own; no tile fetched
  without a User-Agent; an image made through a recorded client with the User-Agent sent;
  geocoding through geocoder-php with Nominatim's answer for "Osaka, Japan" recorded on
  2026-10-23 (one request, then the cache); every class loading with the package's requirements
  alone (`BareTest`).
- Verified live (`docker compose run --rm omnimap live`, 2026-10-23): an image of Kyoto made of
  tile.openstreetmap.org's tiles (the response headers: `access-control-allow-origin: *`,
  `cache-control: max-age=91080, stale-while-revalidate=604800, stale-if-error=604800`) and one
  Nominatim query, with the package's User-Agent.
- Verified on lesringostars (2026-10-23, MAP_PROVIDER=openstreetmap): the page's map made by the
  server from these tiles and kept in omnimap's cache, the attribution shown, no request to Google
  nor to the tile server from the visitor's browser (headless Chrome, both hosts, 1440 and 390).
- Not verified: Leaflet's live map (assets/leaflet.js) in a browser; another tile provider.

"openmap": OpenStreetMap's raster tiles with Leaflet are what this package does. Vector tiles
(OpenMapTiles, MapLibre GL) would be another driver and another tile source, not done here.

## Licence

MIT. An unofficial client, affiliated with the OpenStreetMap Foundation in no way. The map data
is © OpenStreetMap contributors, under the ODbL.
