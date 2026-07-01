<?php

namespace Rayzenai\LaravelSms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rayzenai\LaravelSms\Services\SmsMessageBuilder to(string|array|\Illuminate\Support\Collection|\Rayzenai\LaravelSms\Contracts\HasSmsNumber $recipients)
 * @method static \Rayzenai\LaravelSms\Services\SmsService provider(string $name)
 * @method static \Rayzenai\LaravelSms\Models\SentMessage send(string $recipient, string $message)
 * @method static \Illuminate\Support\Collection sendBulk(array $recipients, string $message)
 * @method static array balance()
 *
 * @see \Rayzenai\LaravelSms\Services\SmsService
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms';
    }
}
