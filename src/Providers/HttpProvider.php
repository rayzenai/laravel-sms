<?php

namespace Rayzenai\LaravelSms\Providers;

use Illuminate\Support\Facades\Http;

class HttpProvider implements SmsProviderInterface
{
    protected string $apiBaseUrl;
    protected string $apiKey;
    protected string $sender;
    protected int $timeout;

    public function __construct()
    {
        $this->apiBaseUrl = config('laravel-sms.api_base_url');
        $this->apiKey = config('laravel-sms.api_key');
        $this->sender = config('laravel-sms.default_sender');
        $this->timeout = config('laravel-sms.timeout', 30);
    }

    /**
     * Send SMS to a single recipient.
     *
     * @param string $recipient
     * @param string $message
     * @return array
     */
    public function send(string $recipient, string $message): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->apiBaseUrl . '/send', [
                'recipient' => $recipient,
                'message' => $message,
                'sender' => $this->sender,
            ]);

        return [
            'sid' => $response->json('message_id'),
            'status' => $response->successful() ? 'sent' : 'failed',
            'response' => $response->json(),
        ];
    }

    /**
     * Send SMS to multiple recipients.
     *
     * @param array $recipients
     * @param string $message
     * @return array
     */
    public function sendBulk(array $recipients, string $message): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->apiBaseUrl . '/send-bulk', [
                'recipients' => $recipients,
                'message' => $message,
                'sender' => $this->sender,
            ]);

        return [
            'status' => $response->successful() ? 'sent' : 'failed',
            'batch_id' => $response->json('batch_id'),
            'recipients_count' => count($recipients),
            'response' => $response->json(),
        ];
    }
}
