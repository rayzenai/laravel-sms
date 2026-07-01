<?php

namespace Rayzenai\LaravelSms\Providers;

/**
 * Optional capability: a provider that can report the remaining SMS credit /
 * balance on the account. Providers that cannot are simply left without it, and
 * {@see \Rayzenai\LaravelSms\Services\SmsService::balance()} will throw an
 * {@see \Rayzenai\LaravelSms\Exceptions\UnsupportedFeatureException}.
 */
interface ReportsBalance
{
    /**
     * @return array{credit: int|float|null, response: array}
     */
    public function balance(): array;
}
