<?php

namespace Rayzenai\LaravelSms\Providers;

use Exception;
use Illuminate\Support\Facades\Http;

class SwiftSmsProvider implements SmsProviderInterface
{
    protected string $singleUrl = 'https://smartsms.swifttech.com.np:8083/api/Sms/ExecuteSendSmsV5';
    protected string $bulkUrl = 'https://smartsms.swifttech.com.np:8083/api/Sms/SaveBulkSMSV5';
    protected string $organisationCode;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->organisationCode = config('laravel-sms.providers.swift.organisation_code');
        $this->username = config('laravel-sms.providers.swift.username');
        $this->password = config('laravel-sms.providers.swift.password');
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
        $params = [
            'Message' => $message,
            'ReceiverNo' => $recipient,
            'IsClientLogin' => 'N',
            'Date' => now()->format('Y/m/d H:i:s'),
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'OrganisationCode' => $this->organisationCode,
                ])
                ->post($this->singleUrl, $params);

            $responseData = $response->json();

            if ($response->successful() && ($responseData['responseCode'] ?? null) === 100) {
                return [
                    'sid' => $responseData['messageId'] ?? null,
                    'status' => 'sent',
                    'response' => $responseData,
                ];
            }

            return [
                'sid' => null,
                'status' => 'failed',
                'response' => $responseData,
                'error' => $responseData['responseDescription'] ?? 'Unknown error',
            ];
        } catch (Exception $e) {
            return [
                'sid' => null,
                'status' => 'failed',
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
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
        $smsDetails = collect($recipients)->map(function ($recipient) use ($message) {
            return [
                'Message' => $message,
                'ReceiverNo' => $recipient,
            ];
        })->values()->toArray();

        $params = [
            'SmsDetails' => $smsDetails,
            'BatchId' => uniqid('batch_'), // Generate unique batch ID
            'Date' => now()->format('Y/m/d H:i:s'),
            'IsClientLogin' => 'N',
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'OrganisationCode' => $this->organisationCode,
                ])
                ->post($this->bulkUrl, $params);

            $responseData = $response->json();

            if ($response->successful() && ($responseData['responseCode'] ?? null) === 100) {
                return [
                    'status' => 'sent',
                    'batch_id' => $params['BatchId'],
                    'recipients_count' => count($recipients),
                    'response' => $responseData,
                ];
            }

            return [
                'status' => 'failed',
                'batch_id' => $params['BatchId'],
                'recipients_count' => count($recipients),
                'response' => $responseData,
                'error' => $responseData['responseDescription'] ?? 'Unknown error',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'batch_id' => $params['BatchId'] ?? null,
                'recipients_count' => count($recipients),
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
