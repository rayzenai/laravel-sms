<?php

namespace Rayzenai\LaravelSms\Providers;

use Illuminate\Support\Facades\Http;

class HttpProvider extends AbstractSmsProvider
{
    /**
     * Send SMS to a single recipient.
     */
    public function send(string $recipient, string $message): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config('api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post($this->config('api_base_url') . '/send', [
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
     * Send SMS to multiple recipients via the provider's bulk endpoint.
     */
    public function sendBulk(array $recipients, string $message): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config('api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post($this->config('api_base_url') . '/send-bulk', [
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
