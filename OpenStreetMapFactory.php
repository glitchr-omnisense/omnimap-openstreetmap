<?php

namespace Omnimap\OpenStreetMap;

use Geocoder\Provider\Nominatim\Nominatim;
use Omnimap\Config;
use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\ProviderFactory;
use Omnimap\ProviderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenStreetMap, from its options:
 *
 *   tiles          the tile URL, {z}/{x}/{y} in it (and {s} for a server's subdomains). The default is the
 *                  OpenStreetMap Foundation's server, whose policy allows normal viewing and forbids heavy use:
 *                  in production, a tile provider of your own choice or your own server
 *   attribution    as HTML, shown on the map (the data's licence requires it)
 *   max_zoom       19
 *   tile_size      256
 *   user_agent     the application's name and a contact, sent with every request the server makes (tiles,
 *                  Nominatim): required by both policies - a library's default User-Agent is blocked
 *   referer        sent with them too, when set
 *   nominatim      the geocoder's URL (https://nominatim.openstreetmap.org); empty: no geocoding
 *   nominatim_interval  seconds between two geocoder calls of a process (1: "an absolute maximum of 1 request per second")
 *   leaflet_script, leaflet_style, leaflet_script_integrity, leaflet_style_integrity
 *                  where the browser gets Leaflet (unpkg, 1.9.4, with its hashes): serve your own copy to keep
 *                  the visitor's browser off a CDN
 *   background     the colour above and below the world on an image made of tiles
 */
final class OpenStreetMapFactory extends ProviderFactory
{
    public const OSM_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    public const NOMINATIM = 'https://nominatim.openstreetmap.org';

    public function __construct(
        private readonly ?HttpClientInterface $http = null,
        private readonly ?ClientInterface $psr18 = null,
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnimap.factory_name' => 'openstreetmap',
            'omnimap.factory_title' => 'OpenStreetMap',
            'omnimap.required_options' => [],
            'tiles' => self::OSM_TILES,
            'attribution' => self::OSM_ATTRIBUTION,
            'max_zoom' => 19,
            'tile_size' => 256,
            'user_agent' => '',
            'referer' => '',
            'nominatim' => self::NOMINATIM,
            'nominatim_interval' => 1.0,
            'geocoder_ttl' => 2592000,
            'leaflet_script' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            'leaflet_script_integrity' => 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=',
            'leaflet_style' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            'leaflet_style_integrity' => 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=',
            'background' => '#aad3df',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $userAgent = $c->string('user_agent');
        $geocoder = null;
        if (null !== $this->psr18 && null !== ($root = $c->string('nominatim')) && null !== $userAgent) {
            $geocoder = new GeocoderPhp(new Nominatim($this->psr18, $root, $userAgent, (string) $c->string('referer')), $this->cache, $c->int('geocoder_ttl', 2592000), $c->float('nominatim_interval', 1.0));
        }

        return new OpenStreetMapProvider(
            tiles: $c->string('tiles') ?? self::OSM_TILES,
            attribution: (string) $c->string('attribution'),
            maxZoom: $c->int('max_zoom', 19),
            tileSize: $c->int('tile_size', 256),
            userAgent: $userAgent,
            referer: $c->string('referer'),
            leaflet: array_filter([
                'script' => $c->string('leaflet_script'),
                'scriptIntegrity' => $c->string('leaflet_script_integrity'),
                'style' => $c->string('leaflet_style'),
                'styleIntegrity' => $c->string('leaflet_style_integrity'),
            ]),
            background: $c->string('background') ?? '#aad3df',
            http: $this->http,
            cache: $this->cache,
            geocoder: $geocoder,
        );
    }
}
