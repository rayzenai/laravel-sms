<?php

namespace Rayzenai\LaravelSms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource;

class LaravelSmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'laravel-sms';
    }

    public function register(Panel $panel): void
    {
        // Sending lives on the resource's create screen ("Send SMS");
        // segments are a saved, reusable audience for bulk sends.
        $panel->resources([
            SentMessageResource::class,
            SmsSegmentResource::class,
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
