<?php

namespace Rayzenai\LaravelSms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rayzenai\LaravelSms\Models\SentMessage send(string $to, string $message, ?string $from = null)
 * @method static array sendBulk(array $recipients, string $message, ?string $from = null)
 * 
 * @see \Rayzenai\LaravelSms\Services\SmsService
 */
class Sms extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sms';
    }
}
