<?php

namespace Rayzenai\LaravelSms\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rayzenai\LaravelSms\Exceptions\UnsupportedFeatureException;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Providers\ReportsBalance;
use Rayzenai\LaravelSms\Providers\SmsProviderInterface;
use Rayzenai\LaravelSms\SmsManager;

class SmsService
{
    /**
     * Provider name to use, or null to use the configured default.
     */
    protected ?string $providerName = null;

    public function __construct(protected SmsManager $manager)
    {
    }

    /**
     * Return a copy of the service pinned to a specific provider by name.
     *
     * Sms::provider('aakash')->send(...)
     */
    public function provider(string $name): static
    {
        $clone = clone $this;
        $clone->providerName = $name;

        return $clone;
    }

    /**
     * Begin a fluent message for one or more recipients.
     *
     * Accepts a phone string, a model implementing HasSmsNumber, or an
     * array/collection mixing the two.
     */
    public function to(mixed $recipients): SmsMessageBuilder
    {
        return (new SmsMessageBuilder($this))->to($recipients);
    }

    /**
     * Send SMS to a single recipient.
     *
     * @throws Exception
     */
    public function send(string $recipient, string $message): SentMessage
    {
        try {
            $result = $this->resolveProvider()->send($recipient, $message);

            $this->log('SMS sent', ['recipient' => $recipient, 'message' => $message, 'response' => $result]);

            return $this->record($recipient, $message, [
                'status' => $result['status'] ?? 'failed',
                'provider_message_id' => $result['sid'] ?? null,
                'provider_response' => $result['response'] ?? $result,
            ]);
        } catch (Exception $e) {
            $this->log('SMS send failed', ['recipient' => $recipient, 'message' => $message, 'error' => $e->getMessage()], 'error');

            $this->record($recipient, $message, [
                'status' => 'failed',
                'provider_response' => ['error' => $e->getMessage(), 'code' => $e->getCode()],
            ]);

            throw $e;
        }
    }

    /**
     * Send SMS to multiple recipients.
     *
     * @return Collection<int, SentMessage>
     *
     * @throws Exception
     */
    public function sendBulk(array $recipients, string $message): Collection
    {
        try {
            $result = $this->resolveProvider()->sendBulk($recipients, $message);

            $this->log('Bulk SMS sent', ['recipients' => $recipients, 'message' => $message, 'response' => $result]);

            $status = $result['status'] ?? 'failed';
            $batchId = $result['batch_id'] ?? null;

            return collect($recipients)->map(fn ($recipient) => $this->record($recipient, $message, [
                'status' => $status,
                'provider_message_id' => $batchId,
                'provider_response' => $result,
            ]));
        } catch (Exception $e) {
            $this->log('Bulk SMS send failed', ['recipients' => $recipients, 'message' => $message, 'error' => $e->getMessage()], 'error');

            collect($recipients)->each(fn ($recipient) => $this->record($recipient, $message, [
                'status' => 'failed',
                'provider_response' => ['error' => $e->getMessage(), 'code' => $e->getCode()],
            ]));

            throw $e;
        }
    }

    /**
     * Report the remaining credit/balance for the active provider.
     *
     * @return array{credit: int|float|null, response: array}
     *
     * @throws UnsupportedFeatureException when the provider can't report balance.
     */
    public function balance(): array
    {
        $provider = $this->resolveProvider();

        if (! $provider instanceof ReportsBalance) {
            throw UnsupportedFeatureException::balance($this->activeProviderName());
        }

        return $provider->balance();
    }

    protected function resolveProvider(): SmsProviderInterface
    {
        return $this->manager->driver($this->providerName);
    }

    protected function activeProviderName(): string
    {
        return $this->providerName ?? $this->manager->getDefaultDriver();
    }

    /**
     * Persist a sent-message record.
     */
    protected function record(string $recipient, string $message, array $attributes): SentMessage
    {
        return SentMessage::create(array_merge([
            'recipient' => $recipient,
            'message' => $message,
            'sender' => config('laravel-sms.default_sender'),
            'provider' => $this->activeProviderName(),
            'sent_at' => now(),
        ], $attributes));
    }

    protected function log(string $message, array $context, string $level = 'info'): void
    {
        if (config('laravel-sms.logging.enabled')) {
            Log::channel(config('laravel-sms.logging.channel', 'stack'))->{$level}($message, $context);
        }
    }
}
