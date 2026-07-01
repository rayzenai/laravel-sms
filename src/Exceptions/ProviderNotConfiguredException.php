<?php

namespace Rayzenai\LaravelSms\Exceptions;

use InvalidArgumentException;

class ProviderNotConfiguredException extends InvalidArgumentException
{
    public static function missingProvider(string $name): self
    {
        return new self(
            "SMS provider [{$name}] is not configured. Add it to the 'providers' array in config/laravel-sms.php."
        );
    }

    public static function missingClass(string $name): self
    {
        return new self(
            "SMS provider [{$name}] does not define a valid 'class'. Point it at a class that exists."
        );
    }

    public static function invalidClass(string $name, string $class): self
    {
        return new self(
            "SMS provider [{$name}] class [{$class}] must implement ".
            \Rayzenai\LaravelSms\Providers\SmsProviderInterface::class.'.'
        );
    }
}
