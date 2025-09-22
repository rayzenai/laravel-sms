<?php

namespace Rayzenai\LaravelSms\Providers;

class TwilioProvider extends HttpProvider
{
    // For now, we'll just extend HttpProvider
    // This allows tests to mock TwilioProvider specifically
    
    /**
     * Send SMS to multiple recipients.
     * Since Twilio doesn't have native bulk sending, we'll use the parent method
     *
     * @param array $recipients
     * @param string $message
     * @return array
     */
    public function sendBulk(array $recipients, string $message): array
    {
        return parent::sendBulk($recipients, $message);
    }
}
