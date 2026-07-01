<?php

namespace Rayzenai\LaravelSms\Providers;

/**
 * Placeholder Twilio provider.
 *
 * It reuses {@see HttpProvider}'s single-send transport, but — since Twilio has no
 * native bulk endpoint — sends bulk messages one recipient at a time.
 */
class TwilioProvider extends HttpProvider
{
    public function sendBulk(array $recipients, string $message): array
    {
        return $this->sendEach($recipients, $message);
    }
}
