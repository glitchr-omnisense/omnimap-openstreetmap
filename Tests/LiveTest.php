<?php

namespace Omnimap\OpenStreetMap\Tests;

use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\OpenStreetMap\OpenStreetMapProvider;
use Omnimap\Testing\ArrayCache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * The OpenStreetMap Foundation's servers themselves, within their policies:
 * a small image (4 to 6 tiles, fetched once, with this package's
 * User-Agent) and one Nominatim question. `docker compose run --rm omnimap
 * live`; never in the default run.
 */
#[Group('live')]
final class LiveTest extends TestCase
{
    private const UA = 'omnimap-tests/1.0 (+https://github.com/glitchr-studio/omnimap)';

    public function testTilesAndNominatim(): void
    {
        $http = HttpClient::create(['timeout' => 20]);
        $provider = (new OpenStreetMapFactory($http, new Psr18Client($http), new ArrayCache()))->create(['user_agent' => self::UA]);
        \assert($provider instanceof OpenStreetMapProvider);

        $image = $provider->image(new MapView(new LatLng(35.0116, 135.7681), 12), new Size(400, 300));
        self::assertSame([400, 300], \array_slice((array) getimagesizefromstring($image->bytes), 0, 2));
        if ($out = getenv('OMNIMAP_LIVE_OUT')) {
            file_put_contents($out, $image->bytes);
        }

        $places = $provider->geocode('Kyoto, Japan', 'en', 1);
        self::assertCount(1, $places);
        self::assertEqualsWithDelta(35.01, $places[0]->position->lat, 0.1);
        self::assertSame('nominatim', $places[0]->providedBy);
    }
}
