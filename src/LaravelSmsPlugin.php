<?php

namespace Rayzenai\LaravelSms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;
use Rayzenai\LaravelSms\Filament\Pages\SendSms;

class LaravelSmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'laravel-sms';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                SentMessageResource::class,
            ])
            ->pages([
                SendSms::class,
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
