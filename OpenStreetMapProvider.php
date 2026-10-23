<?php

namespace Omnimap\OpenStreetMap;

use Omnimap\Capabilities;
use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;
use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\GeocoderInterface;
use Omnimap\MapRendererInterface;
use Omnimap\Model\Image;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\Static\TileStitcher;
use Omnimap\StaticMapInterface;
use Omnimap\TileSourceInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenStreetMap: the live map drawn by Leaflet (assets/leaflet.js) over a
 * tile server, a view as an image made on the server from the same tiles
 * (Static\TileStitcher: fetched with the application's User-Agent, kept as
 * long as the server's Cache-Control allows), and geocoding through
 * geocoder-php's Nominatim provider (one call a second at most, answers
 * kept).
 *
 * Nothing of a view's options is OpenStreetMap's own but
 * MapView::options('openstreetmap')['map'] - Leaflet's map options, as JSON.
 */
final class OpenStreetMapProvider implements MapRendererInterface, StaticMapInterface, GeocoderInterface, TileSourceInterface
{
    /**
     * @param array<string, string> $leaflet
     */
    public function __construct(
        private readonly string $tiles = OpenStreetMapFactory::OSM_TILES,
        private readonly string $attribution = OpenStreetMapFactory::OSM_ATTRIBUTION,
        private readonly int $maxZoom = 19,
        private readonly int $tileSize = 256,
        private readonly ?string $userAgent = null,
        private readonly ?string $referer = null,
        private readonly array $leaflet = [],
        private readonly string $background = '#aad3df',
        private readonly ?HttpClientInterface $http = null,
        private readonly ?CacheInterface $cache = null,
        private readonly ?GeocoderPhp $geocoder = null,
    ) {
    }

    public function getName(): string
    {
        return 'openstreetmap';
    }

    public function getTitle(): string
    {
        return 'OpenStreetMap';
    }

    public function capabilities(): Capabilities
    {
        $hosts = array_values(array_filter([parse_url($this->tiles, \PHP_URL_HOST), parse_url($this->leaflet['script'] ?? '', \PHP_URL_HOST)], 'is_string'));

        return new Capabilities(
            renderer: true,
            staticMap: null !== $this->http,
            geocoder: null !== $this->geocoder,
            tiles: true,
            browserHosts: array_values(array_unique($hosts)),
            cookies: false,
            terms: ['https://operations.osmfoundation.org/policies/tiles/', 'https://operations.osmfoundation.org/policies/nominatim/', 'https://www.openstreetmap.org/copyright'],
        );
    }

    public function describe(MapView $view): array
    {
        return [
            'provider' => 'openstreetmap',
            'consent' => 'MAP',
            'view' => $view->toArray(),
            'library' => $this->leaflet,
            'tiles' => ['template' => $this->tiles, 'maxZoom' => $this->maxZoom, 'tileSize' => $this->tileSize, 'attribution' => $this->attribution],
            'options' => ['map' => $view->options('openstreetmap')['map'] ?? new \stdClass()],
            'attribution' => $this->attribution,
        ];
    }

    public function fingerprint(MapView $view): string
    {
        return 'openstreetmap|'.json_encode([$this->tiles, $view->center, $view->zoom, $view->markers, $view->controls], \JSON_THROW_ON_ERROR);
    }

    public function attribution(): string
    {
        return $this->attribution;
    }

    public function image(MapView $view, Size $size, int $scale = 1): Image
    {
        if (null === $this->http) {
            throw new NotSupportedException('No HTTP client was given to the OpenStreetMap provider: no image made of tiles.');
        }

        return (new TileStitcher($this, $this->http, $this->cache, $this->background))->image($view, $size, $scale);
    }

    public function template(): string
    {
        return $this->tiles;
    }

    public function url(int $zoom, int $x, int $y): string
    {
        return str_replace(['{s}', '{z}', '{x}', '{y}'], ['abc'[($x + $y) % 3], (string) $zoom, (string) $x, (string) $y], $this->tiles);
    }

    public function tileSize(): int
    {
        return $this->tileSize;
    }

    public function maxZoom(): int
    {
        return $this->maxZoom;
    }

    /**
     * The User-Agent the policy asks for (none: refused here rather than blocked there), and the Referer when set.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if (null === $this->userAgent) {
            throw new InvalidConfigException('The OpenStreetMap provider needs a user_agent naming the application and a contact before the server fetches tiles: the tile policy blocks a library\'s default one.');
        }

        return array_filter(['User-Agent' => $this->userAgent, 'Referer' => (string) $this->referer]);
    }

    public function geocode(string $address, ?string $locale = null, int $limit = 5): array
    {
        return $this->geocoder()->geocode($address, $locale, $limit);
    }

    public function reverse(LatLng $position, ?string $locale = null, int $limit = 5): array
    {
        return $this->geocoder()->reverse($position, $locale, $limit);
    }

    private function geocoder(): GeocoderPhp
    {
        return $this->geocoder ?? throw new NotSupportedException('No PSR-18 client, no user_agent or no nominatim URL was given to the OpenStreetMap provider: no geocoding.');
    }
}
