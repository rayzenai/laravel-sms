<?php

namespace Rayzenai\LaravelSms\Providers;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * AakashSMS (https://aakashsms.com) — Nepal SMS gateway.
 *
 * - Single: POST v3/send, form-encoded, `auth_token` field.
 * - Bulk:   POST v4/send-user, JSON body, `auth-token` header.
 * - Balance: GET v4/available-credit, `auth-token` header.
 *
 * AakashSMS expects local 10-digit mobile numbers, so `+977`/`977` prefixes are
 * stripped before sending.
 */
class AakashSmsProvider extends AbstractSmsProvider implements ReportsBalance
{
    protected const SINGLE_URL = 'https://sms.aakashsms.com/sms/v3/send';

    protected const BULK_URL = 'https://sms.aakashsms.com/sms/v4/send-user';

    protected const CREDIT_URL = 'https://sms.aakashsms.com/sms/v4/available-credit';

    public function send(string $recipient, string $message): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post(self::SINGLE_URL, [
                    'auth_token' => $this->config('auth_token'),
                    'to' => $this->normalize($recipient),
                    'text' => $message,
                ]);

            $data = $response->json() ?? [];

            if ($response->successful() && ($data['error'] ?? true) === false) {
                return [
                    'sid' => data_get($data, 'data.valid.0.id'),
                    'status' => 'sent',
                    'response' => $data,
                ];
            }

            return [
                'sid' => null,
                'status' => 'failed',
                'response' => $data,
                'error' => $data['message'] ?? 'Unknown error',
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
        $to = array_values(array_map(fn ($recipient) => $this->normalize($recipient), $recipients));

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['auth-token' => $this->config('auth_token')])
                ->post(self::BULK_URL, [
                    'to' => $to,
                    'text' => [$message],
                ]);

            $data = $response->json() ?? [];
            $errors = $data['errors'] ?? [];
            $failed = ! $response->successful() || ! empty($errors);

            return [
                'status' => $failed ? 'failed' : 'sent',
                'batch_id' => null,
                'recipients_count' => count($recipients),
                'response' => $data,
                'error' => $failed ? ($errors[0]['message'] ?? 'Unknown error') : null,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'batch_id' => null,
                'recipients_count' => count($recipients),
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function balance(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['auth-token' => $this->config('auth_token')])
                ->get(self::CREDIT_URL);

            $data = $response->json() ?? [];

            return [
                'credit' => $data['available_credit'] ?? null,
                'response' => $data,
            ];
        } catch (Throwable $e) {
            return [
                'credit' => null,
                'response' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Reduce a phone number to the local 10-digit form AakashSMS expects.
     */
    protected function normalize(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '977')) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }
}
