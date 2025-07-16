<?php

namespace Rayzenai\LaravelSms\Providers;

interface SmsProviderInterface
{
    /**
     * Send SMS to a single recipient.
     *
     * @param string $recipient
     * @param string $message
     * @return array
     */
    public function send(string $recipient, string $message): array;

    /**
     * Send SMS to multiple recipients.
     *
     * @param array $recipients
     * @param string $message
     * @return array
     */
    public function sendBulk(array $recipients, string $message): array;
}
