<?php

namespace Rayzenai\LaravelSms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;

class LaravelSmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'laravel-sms';
    }

    public function register(Panel $panel): void
    {
        // Sending lives on the resource's create screen ("Send SMS").
        $panel->resources([
            SentMessageResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
