<?php

namespace Rayzenai\LaravelSms\Providers;

/**
 * Base class for SMS providers.
 *
 * A provider receives its configuration array from the {@see \Rayzenai\LaravelSms\SmsManager}
 * (its own registry entry, merged with the shared `timeout` and `sender`). At
 * minimum a provider must implement {@see send()}; a default {@see sendBulk()} that
 * loops over `send()` is provided so simple providers get bulk for free. Providers
 * with a native batch endpoint should override {@see sendBulk()}.
 */
abstract class AbstractSmsProvider implements SmsProviderInterface
{
    protected array $config;

    protected int $timeout;

    protected ?string $sender;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->sender = $config['sender'] ?? null;
    }

    /**
     * Read a value from the provider's configuration array.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Default bulk implementation: send to each recipient individually.
     *
     * Providers with a native bulk endpoint (Swift, Aakash, HTTP) override this.
     */
    public function sendBulk(array $recipients, string $message): array
    {
        return $this->sendEach($recipients, $message);
    }

    /**
     * Send to each recipient with an individual {@see send()} call and fold the
     * results into the aggregate bulk shape.
     */
    protected function sendEach(array $recipients, string $message): array
    {
        $results = [];
        $anySent = false;

        foreach ($recipients as $recipient) {
            $result = $this->send($recipient, $message);
            $results[] = ['recipient' => $recipient] + $result;
            $anySent = $anySent || ($result['status'] ?? null) === 'sent';
        }

        return [
            'status' => $anySent ? 'sent' : 'failed',
            'batch_id' => null,
            'recipients_count' => count($recipients),
            'response' => $results,
        ];
    }
}
