<?php

namespace Rayzenai\LaravelSms\Providers;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SwiftTech SmartSMS gateway (Nepal). Uses HTTP Basic auth plus an
 * `OrganisationCode` header, and has a native bulk endpoint.
 */
class SwiftSmsProvider extends AbstractSmsProvider
{
    protected const SINGLE_URL = 'https://smartsms.swifttech.com.np:8083/api/Sms/ExecuteSendSmsV5';

    protected const BULK_URL = 'https://smartsms.swifttech.com.np:8083/api/Sms/SaveBulkSMSV5';

    public function send(string $recipient, string $message): array
    {
        try {
            $response = $this->request()->post(self::SINGLE_URL, [
                'Message' => $message,
                'ReceiverNo' => $recipient,
                'IsClientLogin' => 'N',
                'Date' => now()->format('Y/m/d H:i:s'),
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['responseCode'] ?? null) === 100) {
                return [
                    'sid' => $data['messageId'] ?? null,
                    'status' => 'sent',
                    'response' => $data,
                ];
            }

            return [
                'sid' => null,
                'status' => 'failed',
                'response' => $data,
                'error' => $data['responseDescription'] ?? 'Unknown error',
            ];
        } catch (Throwable $e) {
            return [
                'sid' => null,
                'status' => 'failed',
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendBulk(array $recipients, string $message): array
    {
        $smsDetails = collect($recipients)
            ->map(fn ($recipient) => ['Message' => $message, 'ReceiverNo' => $recipient])
            ->values()
            ->all();

        $batchId = uniqid('batch_');

        try {
            $response = $this->request()->post(self::BULK_URL, [
                'SmsDetails' => $smsDetails,
                'BatchId' => $batchId,
                'Date' => now()->format('Y/m/d H:i:s'),
                'IsClientLogin' => 'N',
            ]);

            $data = $response->json();
            $sent = $response->successful() && ($data['responseCode'] ?? null) === 100;

            return [
                'status' => $sent ? 'sent' : 'failed',
                'batch_id' => $batchId,
                'recipients_count' => count($recipients),
                'response' => $data,
                'error' => $sent ? null : ($data['responseDescription'] ?? 'Unknown error'),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'batch_id' => $batchId,
                'recipients_count' => count($recipients),
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * A pending request pre-configured with Swift's auth.
     */
    protected function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withBasicAuth($this->config('username'), $this->config('password'))
            ->withHeaders(['OrganisationCode' => $this->config('organisation_code')]);
    }
}
