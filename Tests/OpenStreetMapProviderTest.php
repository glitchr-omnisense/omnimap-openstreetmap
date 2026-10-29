<?php

namespace Omnimap\OpenStreetMap\Tests;

use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\OpenStreetMap\OpenStreetMapProvider;
use Omnimap\Registry;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenStreetMapProviderTest extends TestCase
{
    private const UA = 'omnimap-tests/1.0 (+https://github.com/glitchr-omnisense/omnimap)';

    public function testTheDescriptionLeafletDraws(): void
    {
        $provider = (new OpenStreetMapFactory())->create();
        \assert($provider instanceof OpenStreetMapProvider);
        $view = new MapView(new LatLng(34.6937, 135.5023), 10, [new Marker(new LatLng(34.6937, 135.5023), 'Osaka')], false, ['google' => ['styles' => []]], 'myMap');
        $description = $provider->describe($view);

        self::assertSame('openstreetmap', $description['provider']);
        self::assertSame('MAP', $description['consent']);
        self::assertSame($view->toArray(), $description['view']);
        self::assertSame(['template' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'maxZoom' => 19, 'tileSize' => 256, 'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'], $description['tiles']);
        self::assertSame(['script' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'scriptIntegrity' => 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=', 'style' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', 'styleIntegrity' => 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='], $description['library']);
        self::assertSame(['tile.openstreetmap.org', 'unpkg.com'], $provider->capabilities()->browserHosts);
        self::assertSame(['renderer', 'tiles'], $provider->capabilities()->contracts(), 'no HTTP client given: no image, no geocoder');
        self::assertNotSame($provider->fingerprint($view), $provider->fingerprint($view->withMarkers([])), 'its markers are in its pictures');
    }

    public function testATileServerOfOnesOwn(): void
    {
        $provider = (new OpenStreetMapFactory())->create(['tiles' => 'https://{s}.tiles.example/{z}/{x}/{y}.png', 'attribution' => 'Tiles: example', 'max_zoom' => '17', 'user_agent' => self::UA, 'referer' => 'https://ringo.example/']);
        \assert($provider instanceof OpenStreetMapProvider);

        self::assertSame('https://a.tiles.example/3/4/5.png', $provider->url(3, 4, 5));
        self::assertSame(17, $provider->maxZoom());
        self::assertSame(['User-Agent' => self::UA, 'Referer' => 'https://ringo.example/'], $provider->headers());
    }

    public function testAnImageMadeOfItsTilesWithTheApplicationsUserAgent(): void
    {
        $http = new RecordedHttpClient(PlainTiles::answer());
        $registry = new Registry([new OpenStreetMapFactory($http, null, new ArrayCache())], ['maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => self::UA]]]);
        $image = $registry->staticMap('maps')->image(new MapView(new LatLng(34.6937, 135.5023), 10), new Size(400, 300));

        self::assertSame('400x300', (string) $image->size);
        self::assertStringStartsWith('https://tile.openstreetmap.org/10/', $http->requests[0]['url']);
        self::assertSame(['User-Agent' => self::UA], $http->requests[0]['options']['headers']);
    }

    /** No user_agent: no image declared (the bridges' cache.make: auto makes none), and none made. */
    public function testNoUserAgentNoTileFetched(): void
    {
        $http = new RecordedHttpClient(PlainTiles::answer());
        $provider = (new OpenStreetMapFactory($http))->create();
        \assert($provider instanceof OpenStreetMapProvider);

        self::assertFalse($provider->capabilities()->staticMap, 'an HTTP client and no user_agent: no image');
        try {
            $provider->image(new MapView(new LatLng(0, 0), 2), new Size(10, 10));
            self::fail('A tile fetched without a User-Agent.');
        } catch (NotSupportedException $e) {
            self::assertStringContainsString('user_agent', $e->getMessage());
        }
        try {
            $provider->headers();
            self::fail('A tile proxied without a User-Agent.');
        } catch (InvalidConfigException $e) {
            self::assertStringContainsString('user_agent', $e->getMessage());
        }
        self::assertSame([], $http->requests);
        $provider = (new OpenStreetMapFactory($http))->create(['user_agent' => self::UA]);
        self::assertTrue($provider->capabilities()->staticMap);
    }

    /** A template with {s}: the hosts the browser reaches are Leaflet's subdomains a, b and c (url() takes the same). */
    public function testTheSubdomainsOfATemplateAreTheBrowsersHosts(): void
    {
        $provider = (new OpenStreetMapFactory())->create(['tiles' => 'https://{s}.tiles.example/{z}/{x}/{y}.png']);

        self::assertSame(['a.tiles.example', 'b.tiles.example', 'c.tiles.example', 'unpkg.com'], $provider->capabilities()->browserHosts);
    }

    /** Nominatim's answer for "Osaka, Japan", recorded on 2026-10-23 (format=jsonv2, addressdetails=1). */
    public function testGeocodingThroughNominatimOncePerQuestion(): void
    {
        $requests = [];
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$url, $options['normalized_headers']['user-agent'][0] ?? null];

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/nominatim-osaka.json'), ['response_headers' => ['content-type' => 'application/json']]);
        });
        $provider = (new OpenStreetMapFactory(null, new Psr18Client($mock), new ArrayCache()))->create(['user_agent' => self::UA, 'nominatim_interval' => 0]);
        \assert($provider instanceof OpenStreetMapProvider);

        $places = $provider->geocode('Osaka, Japan', 'ja', 1);
        $provider->geocode('Osaka, Japan', 'ja', 1);

        self::assertCount(1, $places);
        self::assertSame(['lat' => 34.6937569, 'lng' => 135.5014539], $places[0]->position->toArray());
        self::assertSame(['大阪市, 大阪府, 日本', 'nominatim', 'relation/358674', 'JP'], [$places[0]->label, $places[0]->providedBy, $places[0]->id, $places[0]->address?->countryCode]);
        self::assertSame(['south' => 34.586154, 'west' => 135.3099784, 'north' => 34.768849, 'east' => 135.5991702], $places[0]->bounds?->toArray());
        self::assertCount(1, $requests, 'the second answer from the cache');
        self::assertStringStartsWith('https://nominatim.openstreetmap.org/search?format=jsonv2&q=Osaka%2C%20Japan&addressdetails=1&extratags=1&limit=1', $requests[0][0]);
        self::assertSame('User-Agent: '.self::UA, $requests[0][1]);
    }
}
