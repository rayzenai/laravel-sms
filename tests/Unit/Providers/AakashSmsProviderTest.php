<?php

namespace Rayzenai\LaravelSms\Tests\Unit\Providers;

use Illuminate\Support\Facades\Http;
use Rayzenai\LaravelSms\Providers\AakashSmsProvider;
use Rayzenai\LaravelSms\Providers\ReportsBalance;
use Rayzenai\LaravelSms\Tests\TestCase;

class AakashSmsProviderTest extends TestCase
{
    protected function provider(): AakashSmsProvider
    {
        return new AakashSmsProvider(['auth_token' => 'test-token', 'timeout' => 30]);
    }

    public function test_it_reports_balance_capability(): void
    {
        $this->assertInstanceOf(ReportsBalance::class, $this->provider());
    }

    public function test_send_posts_form_and_strips_country_code(): void
    {
        Http::fake([
            'https://sms.aakashsms.com/sms/v3/send' => Http::response([
                'error' => false,
                'message' => '1 messages has been queued for delivery.',
                'data' => ['valid' => [['id' => 2673160, 'mobile' => '9779818000000']], 'invalid' => []],
            ], 200),
        ]);

        $result = $this->provider()->send('+9779818000000', 'Hello');

        $this->assertEquals('sent', $result['status']);
        $this->assertEquals(2673160, $result['sid']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.aakashsms.com/sms/v3/send'
                && $request['auth_token'] === 'test-token'
                && $request['to'] === '9818000000'          // +977 stripped to 10 digits
                && $request['text'] === 'Hello'
                && str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded');
        });
    }

    public function test_send_marks_failure_when_api_returns_error(): void
    {
        Http::fake([
            'https://sms.aakashsms.com/sms/v3/send' => Http::response([
                'error' => true,
                'message' => 'Invalid token.',
            ], 200),
        ]);

        $result = $this->provider()->send('+9779818000000', 'Hello');

        $this->assertEquals('failed', $result['status']);
        $this->assertNull($result['sid']);
        $this->assertEquals('Invalid token.', $result['error']);
    }

    public function test_bulk_posts_json_with_auth_header(): void
    {
        Http::fake([
            'https://sms.aakashsms.com/sms/v4/send-user' => Http::response([
                'responses' => [['error' => false, 'data' => ['valid' => [['id' => '8967_17', 'mobile' => '9818000000']]]]],
                'errors' => [],
            ], 200),
        ]);

        $result = $this->provider()->sendBulk(['+9779818000000', '9812345678'], 'Hi all');

        $this->assertEquals('sent', $result['status']);
        $this->assertEquals(2, $result['recipients_count']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.aakashsms.com/sms/v4/send-user'
                && $request->hasHeader('auth-token', 'test-token')
                && $request['to'] === ['9818000000', '9812345678']
                && $request['text'] === ['Hi all'];
        });
    }

    public function test_bulk_marks_failure_when_errors_present(): void
    {
        Http::fake([
            'https://sms.aakashsms.com/sms/v4/send-user' => Http::response([
                'responses' => [],
                'errors' => [['message' => 'Insufficient balance']],
            ], 200),
        ]);

        $result = $this->provider()->sendBulk(['+9779818000000'], 'Hi');

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Insufficient balance', $result['error']);
    }

    public function test_balance_reads_available_credit(): void
    {
        Http::fake([
            'https://sms.aakashsms.com/sms/v4/available-credit' => Http::response([
                'available_credit' => 1234,
                'response_code' => 200,
            ], 200),
        ]);

        $result = $this->provider()->balance();

        $this->assertEquals(1234, $result['credit']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.aakashsms.com/sms/v4/available-credit'
                && $request->method() === 'GET'
                && $request->hasHeader('auth-token', 'test-token');
        });
    }
}
