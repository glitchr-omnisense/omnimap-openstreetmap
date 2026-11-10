<?php

namespace Omnimap\OpenStreetMap\Tests;

use Omnimap\Exception\UsageLimitReachedException;
use Omnimap\GeocoderInterface;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\OpenStreetMap\OpenStreetMapProvider;
use Omnimap\Registry;
use Omnimap\Testing\ArrayCache;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\PolicyExport;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The OpenStreetMap Foundation's tile and Nominatim policies as policy.json declares them, through the registry. */
final class PolicyTest extends TestCase
{
    private const UA = 'omnimap-tests/1.0 (+https://github.com/glitchr-omnisense/omnimap)';

    public function testThePolicyFileReadsAsTheFoundationsTermsSayThem(): void
    {
        $policy = UsagePolicy::fromFile(OpenStreetMapProvider::POLICY);
        $declared = (new PolicyExport())->folders(\dirname(__DIR__))->entries();

        self::assertSame(['package' => 'omnimap/openstreetmap', 'version' => null, 'file' => 'policy.json', 'policy' => $policy->toArray()], $declared[0] ?? null, 'composer.json names it (extra.omnisense.policy)');
        self::assertSame('openstreetmap', $policy->provider);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertCount(1, $policy->limitsFor('geocode'));
        self::assertSame(1, $policy->limitsFor('reverse')[0]->max);
        self::assertSame([], $policy->limitsFor('image'), 'the tile policy sets no rate');
        self::assertTrue($policy->requirements->userAgent);
        self::assertFalse($policy->requirements->key);
        self::assertTrue($policy->rights->attribution->required);
        self::assertSame('© OpenStreetMap contributors', $policy->rights->attribution->text);
        self::assertSame('ODbL-1.0', $policy->rights->licence);
        self::assertSame(Permission::Allowed, $policy->rights->caching->permission);

        $provider = (new OpenStreetMapFactory())->create();
        self::assertEquals($policy, $provider->capabilities()->policy());
    }

    public function testOneNominatimCallASecondTheSecondNeverMade(): void
    {
        $calls = 0;
        $mock = new MockHttpClient(static function () use (&$calls) {
            ++$calls;

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/nominatim-osaka.json'), ['response_headers' => ['content-type' => 'application/json']]);
        });
        $registry = new Registry([new OpenStreetMapFactory(null, new Psr18Client($mock), new ArrayCache())], ['maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => self::UA, 'nominatim_interval' => 0]]]);
        $geocoder = $registry->guarded('maps', GeocoderInterface::class);

        self::assertCount(1, $geocoder->geocode('Osaka, Japan', 'ja', 1));
        try {
            $geocoder->geocode('Kyoto, Japan', 'ja', 1);
            self::fail('A second Nominatim call in the same second.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(1, $e->limit?->max);
        }
        self::assertSame(1, $calls);
    }

    public function testServersOfTheSitesChoiceLeaveTheLimitsUnknownAndTheCreditKept(): void
    {
        $provider = (new OpenStreetMapFactory())->create(['tiles' => 'https://{s}.tiles.example/{z}/{x}/{y}.png', 'nominatim' => 'https://geocode.example']);
        \assert($provider instanceof OpenStreetMapProvider);
        $policy = $provider->usagePolicy();

        self::assertNull($policy->rateLimits);
        self::assertSame('ODbL-1.0', $policy->rights->licence);
        self::assertTrue($policy->rights->attribution->required);

        $osmfTiles = (new OpenStreetMapFactory())->create(['nominatim' => 'https://geocode.example']);
        self::assertNotNull($osmfTiles->capabilities()->policy()->rateLimits, 'the Foundation\'s tile server still called: its policy');
    }

    public function testASiteMayNotLoosenNominatimsLimit(): void
    {
        $this->expectException(InvalidPolicyException::class);
        (new Registry([new OpenStreetMapFactory()], ['maps' => ['factory' => 'openstreetmap', 'policy' => ['rateLimits' => [['max' => 5, 'window' => 'second', 'operations' => ['geocode', 'reverse']]]]]]))->policy('maps');
    }
}
