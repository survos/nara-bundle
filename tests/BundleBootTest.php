<?php

declare(strict_types=1);

namespace Survos\NaraBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\Nara\Client;
use Symfony\Bundle\FrameworkBundle\Console\Application;

final class BundleBootTest extends TestCase
{
    private function boot(array $env): TestKernel
    {
        foreach ($env as $k => $v) {
            $_SERVER[$k] = $_ENV[$k] = $v;
        }
        $kernel = new TestKernel('test'.md5(serialize($env)), true);
        $kernel->boot();

        return $kernel;
    }

    protected function tearDown(): void
    {
        foreach (['NARA_API_KEY', 'NARA_USER_ID', 'NARA_SUPPORT_EMAIL', 'NARA_SUPPORT_EMAIL_CC'] as $k) {
            unset($_SERVER[$k], $_ENV[$k]);
        }
    }

    public function testClientIsConfiguredFromEnvironment(): void
    {
        $kernel = $this->boot(['NARA_API_KEY' => 'k', 'NARA_USER_ID' => 'uuid-9', 'NARA_SUPPORT_EMAIL' => 'me@example.org']);

        $client = $kernel->getContainer()->get('test.service_container')->get(Client::class);

        self::assertInstanceOf(Client::class, $client);
        self::assertSame('uuid-9', $client->getUserId());
    }

    public function testUserIdIsOptionalForReadOnlyUse(): void
    {
        $kernel = $this->boot(['NARA_API_KEY' => 'k']);

        self::assertNull($kernel->getContainer()->get('test.service_container')->get(Client::class)->getUserId());
    }

    public function testCommandsAreRegistered(): void
    {
        $kernel = $this->boot(['NARA_API_KEY' => 'k']);

        $names = array_keys((new Application($kernel))->all());

        foreach (['nara:search', 'nara:record', 'nara:contribute'] as $name) {
            self::assertContains($name, $names);
        }
    }
}
