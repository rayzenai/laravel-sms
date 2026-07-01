<?php

namespace Rayzenai\LaravelSms\Concerns;

use Rayzenai\LaravelSms\Facades\Sms;
use Rayzenai\LaravelSms\Models\SentMessage;

/**
 * Gives a model a `sendSMS()` helper.
 *
 * The model must define {@see smsPhoneNumber()} (declare it via the
 * {@see \Rayzenai\LaravelSms\Contracts\HasSmsNumber} contract) so the package knows
 * how to reach it. Recommended usage:
 *
 *     class User extends Authenticatable implements HasSmsNumber
 *     {
 *         use Smsable;
 *
 *         public function smsPhoneNumber(): ?string
 *         {
 *             return $this->phone; // or concat country_code + phone, etc.
 *         }
 *     }
 */
trait Smsable
{
    /**
     * How to reach this model by SMS. Return null when there is no usable number.
     */
    abstract public function smsPhoneNumber(): ?string;

    /**
     * Send an SMS to this model. Returns null (a no-op) when the model has no
     * resolvable phone number, so callers can fire-and-forget safely.
     *
     * @param  string|null  $provider  Override the default provider for this send.
     */
    public function sendSMS(string $message, ?string $provider = null): ?SentMessage
    {
        if (blank($this->smsPhoneNumber())) {
            return null;
        }

        $service = $provider ? Sms::provider($provider) : app('sms');

        return $service->to($this)->message($message)->send();
    }
}
