<?php

declare(strict_types=1);

namespace Survos\NaraBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\Nara\Client;
use Survos\NaraBundle\Command\NaraService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PersonSearchTest extends TestCase
{
    public function testPersonOptionPreservesFilters(): void
    {
        $params = [];
        $http = new MockHttpClient(function ($method, $url) use (&$params) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);

            return new MockResponse('{"body":{"hits":{"total":{"value":0},"hits":[]}}}');
        });
        $service = new NaraService(new Client($http, 'test'));
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);
        self::assertSame(0, $service->search($io, 'Fred Wesley Goodenough', recordGroup: '147', online: true, json: true, person: true));
        self::assertSame('"Fred Wesley Goodenough"', $params['q']);
        self::assertSame('147', $params['recordGroup']);
        self::assertSame('true', $params['availableOnline']);
        self::assertSame([], json_decode(trim($output->fetch()), true));
        self::assertSame(2, $service->search($io, person: true));
    }
}
