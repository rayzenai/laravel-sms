<?php

namespace Rayzenai\LaravelSms\Exceptions;

use RuntimeException;

class UnsupportedFeatureException extends RuntimeException
{
    public static function balance(string $provider): self
    {
        return new self("SMS provider [{$provider}] does not support balance/credit reporting.");
    }
}
