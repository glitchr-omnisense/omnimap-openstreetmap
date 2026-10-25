<?php

namespace Omnimap\OpenStreetMap\Tests;

use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\ProviderFactoryInterface;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\ProviderContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** omnimap's conformance suite on OpenStreetMap: tiles from the recorded PNG, Nominatim from its recorded answer. */
final class OpenStreetMapContractTest extends TestCase
{
    use ProviderContractTests;

    protected function contractFactory(?HttpClientInterface $http): ProviderFactoryInterface
    {
        return new OpenStreetMapFactory($http, null === $http ? null : new Psr18Client($http), new ArrayCache());
    }

    /** @return array<string, mixed> */
    protected function contractOptions(): array
    {
        return ['user_agent' => 'omnimap-contract/1.0 (+https://github.com/glitchr-studio/omnimap-openstreetmap)', 'nominatim_interval' => 0];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{0: int, 1: string, 2: array<string, string|list<string>>}
     */
    protected function contractAnswer(string $method, string $url, array $options): array
    {
        if (str_starts_with($url, OpenStreetMapFactory::NOMINATIM.'/reverse')) {
            // Written in the format of Nominatim's reverse answer (format=jsonv2), from the search answer recorded on 2026-10-23.
            return [200, (string) file_get_contents(__DIR__.'/Fixtures/nominatim-reverse-osaka.json'), ['content-type' => 'application/json']];
        }
        if (str_starts_with($url, OpenStreetMapFactory::NOMINATIM)) {
            return [200, (string) file_get_contents(__DIR__.'/Fixtures/nominatim-osaka.json'), ['content-type' => 'application/json']];
        }

        return [200, PlainTiles::png(1, 0, 0), ['content-type' => 'image/png', 'cache-control' => 'max-age=3600']];
    }
}
