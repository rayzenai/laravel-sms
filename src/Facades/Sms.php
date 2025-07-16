<?php

namespace Rayzenai\LaravelSms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rayzenai\LaravelSms\Services\SmsMessageBuilder to(string|array $recipients)
 * @method static \Rayzenai\LaravelSms\Models\SentMessage send(string $to, string $message)
 * @method static \Illuminate\Support\Collection sendBulk(array $recipients, string $message)
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
    
    /**
     * Handle dynamic static calls to create a message builder.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();
        
        // If calling 'to', start a new message builder
        if ($method === 'to') {
            $builder = new \Rayzenai\LaravelSms\Services\SmsMessageBuilder($instance);
            return $builder->to(...$args);
        }
        
        // Otherwise, call the method on the service instance
        return $instance->$method(...$args);
    }
}
