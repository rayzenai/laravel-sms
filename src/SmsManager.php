<?php

namespace Rayzenai\LaravelSms;

use Illuminate\Contracts\Container\Container;
use Rayzenai\LaravelSms\Exceptions\ProviderNotConfiguredException;
use Rayzenai\LaravelSms\Providers\SmsProviderInterface;

/**
 * Resolves SMS providers by name from the `laravel-sms.providers` registry.
 *
 * Selecting a provider is entirely config-driven: `laravel-sms.default` names the
 * active provider, and each `laravel-sms.providers.<name>` entry supplies its
 * `class` plus that provider's credentials. Adding a provider means writing a class
 * and adding a registry entry — no code changes here.
 */
class SmsManager
{
    /**
     * Resolved provider instances, keyed by name.
     *
     * @var array<string, SmsProviderInterface>
     */
    protected array $drivers = [];

    public function __construct(protected Container $container)
    {
    }

    /**
     * The name of the default provider (`SMS_PROVIDER`).
     */
    public function getDefaultDriver(): string
    {
        return config('laravel-sms.default', 'http');
    }

    /**
     * Resolve a provider by name (defaults to the configured provider).
     */
    public function driver(?string $name = null): SmsProviderInterface
    {
        $name ??= $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): SmsProviderInterface
    {
        $config = config("laravel-sms.providers.{$name}");

        if (! is_array($config)) {
            throw ProviderNotConfiguredException::missingProvider($name);
        }

        $class = $config['class'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw ProviderNotConfiguredException::missingClass($name);
        }

        // Merge shared defaults the provider base expects.
        $config['timeout'] ??= config('laravel-sms.timeout', 30);
        $config['sender'] ??= config('laravel-sms.default_sender');

        // A provider bound in the container (e.g. a test double, or a custom
        // provider that needs its own dependencies) wins; otherwise construct it
        // directly with its config array.
        $provider = $this->container->bound($class)
            ? $this->container->make($class)
            : new $class($config);

        if (! $provider instanceof SmsProviderInterface) {
            throw ProviderNotConfiguredException::invalidClass($name, $class);
        }

        return $provider;
    }
}
