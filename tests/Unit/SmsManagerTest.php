<?php

namespace Rayzenai\LaravelSms\Tests\Unit;

use Rayzenai\LaravelSms\Exceptions\ProviderNotConfiguredException;
use Rayzenai\LaravelSms\Providers\AakashSmsProvider;
use Rayzenai\LaravelSms\Providers\HttpProvider;
use Rayzenai\LaravelSms\SmsManager;
use Rayzenai\LaravelSms\Tests\TestCase;

class SmsManagerTest extends TestCase
{
    protected function manager(): SmsManager
    {
        return $this->app->make(SmsManager::class);
    }

    public function test_resolves_the_default_provider_by_name(): void
    {
        config(['laravel-sms.default' => 'http']);

        $this->assertInstanceOf(HttpProvider::class, $this->manager()->driver());
    }

    public function test_resolves_a_named_provider(): void
    {
        $this->assertInstanceOf(AakashSmsProvider::class, $this->manager()->driver('aakash'));
    }

    public function test_caches_resolved_instances(): void
    {
        $manager = $this->manager();

        $this->assertSame($manager->driver('aakash'), $manager->driver('aakash'));
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(ProviderNotConfiguredException::class);

        $this->manager()->driver('does-not-exist');
    }

    public function test_provider_without_a_valid_class_throws(): void
    {
        config(['laravel-sms.providers.broken' => ['class' => 'App\\Nope\\Missing']]);

        $this->expectException(ProviderNotConfiguredException::class);

        $this->manager()->driver('broken');
    }

    public function test_merges_shared_timeout_and_sender_into_provider_config(): void
    {
        config([
            'laravel-sms.timeout' => 45,
            'laravel-sms.default_sender' => 'Acme',
            'laravel-sms.providers.probe' => ['class' => ConfigProbeProvider::class],
        ]);

        /** @var ConfigProbeProvider $provider */
        $provider = $this->manager()->driver('probe');

        $this->assertEquals(45, $provider->seenConfig['timeout']);
        $this->assertEquals('Acme', $provider->seenConfig['sender']);
    }
}

class ConfigProbeProvider extends \Rayzenai\LaravelSms\Providers\AbstractSmsProvider
{
    public array $seenConfig = [];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->seenConfig = $config;
    }

    public function send(string $recipient, string $message): array
    {
        return ['sid' => null, 'status' => 'sent', 'response' => []];
    }
}
