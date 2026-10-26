<?php

declare(strict_types=1);

namespace Survos\NaraBundle;

use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\Nara\Client;
use Survos\Nara\ClientFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

#[RequiredBundle(SurvosKitBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosNaraBundle extends AbstractSurvosBundle
{
    protected string $extensionAlias = 'survos_nara';

    public function configure(DefinitionConfigurator $definition): void
    {
        // The defaults name the env vars, so an app only has to define NARA_API_KEY
        // (and NARA_USER_ID to write) and needs no config file at all.
        $definition->rootNode()->children()
            ->stringNode('api_key')->info('Catalog API key (email Catalog_API@nara.gov)')->defaultValue('%env(NARA_API_KEY)%')->end()
            ->stringNode('user_id')->info('Catalog user UUID, required only to contribute')->defaultValue('%env(default::NARA_USER_ID)%')->end()
            ->stringNode('support_email')->info('Sent as the From header so NARA can contact heavy users')->defaultValue('%env(default::NARA_SUPPORT_EMAIL)%')->end()
            ->stringNode('support_email_cc')->info('Sent as the CC header')->defaultValue('%env(default::NARA_SUPPORT_EMAIL_CC)%')->end()
            ->stringNode('base_uri')->defaultValue(Client::BASE_URI)->end()
        ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);
        $services = $container->services()->defaults()->autowire()->autoconfigure();

        // Dedicated transport: the API key travels in a header, keep it out of the shared client's profiler/log traffic.
        $services->set('survos_nara.http_client', HttpClientInterface::class)->factory([HttpClient::class, 'create']);
        $services->set(Client::class)
            ->factory([ClientFactory::class, 'create'])
            ->args([
                $config['api_key'],
                service('survos_nara.http_client'),
                $config['user_id'],
                $config['support_email'],
                $config['support_email_cc'],
                $config['base_uri'],
            ]);
    }
}
