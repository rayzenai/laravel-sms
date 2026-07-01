<?php

namespace Rayzenai\LaravelSms\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Providers\HttpProvider;
use Rayzenai\LaravelSms\Services\SmsService;
use Rayzenai\LaravelSms\Tests\TestCase;

class SmsServiceTest extends TestCase
{
    protected SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'laravel-sms.default' => 'http',
            'laravel-sms.providers.http' => [
                'class' => HttpProvider::class,
                'api_base_url' => 'https://api.example.com',
                'api_key' => 'test-api-key',
            ],
            'laravel-sms.default_sender' => 'TestApp',
            'laravel-sms.timeout' => 30,
            'laravel-sms.logging.enabled' => false,
        ]);

        $this->smsService = $this->app->make(SmsService::class);
    }

    public function test_send_single_sms_successfully(): void
    {
        Http::fake([
            'https://api.example.com/send' => Http::response([
                'success' => true,
                'message_id' => 'msg_123456',
                'status' => 'sent',
            ], 200),
        ]);

        $sentMessage = $this->smsService->send('+9779801002468', 'Test message');

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertEquals('+9779801002468', $sentMessage->recipient);
        $this->assertEquals('TestApp', $sentMessage->sender);
        $this->assertEquals('sent', $sentMessage->status);
        $this->assertEquals('http', $sentMessage->provider);
        $this->assertEquals('msg_123456', $sentMessage->provider_message_id);
        $this->assertNotNull($sentMessage->sent_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/send' &&
                   $request['recipient'] === '+9779801002468' &&
                   $request['message'] === 'Test message' &&
                   $request['sender'] === 'TestApp' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    }

    public function test_send_single_sms_handles_failure(): void
    {
        Http::fake([
            'https://api.example.com/send' => Http::response([
                'success' => false,
                'error' => 'Invalid recipient',
            ], 400),
        ]);

        $sentMessage = $this->smsService->send('+9779801002468', 'Test message');

        $this->assertEquals('failed', $sentMessage->status);
        $this->assertArrayHasKey('error', $sentMessage->provider_response);
    }

    public function test_send_bulk_uses_a_single_native_request(): void
    {
        Http::fake([
            'https://api.example.com/send-bulk' => Http::response([
                'success' => true,
                'batch_id' => 'batch_001',
            ], 200),
        ]);

        $recipients = ['+9779801002468', '+9779812345678', '+9779898765432'];
        $sentMessages = $this->smsService->sendBulk($recipients, 'Bulk test message');

        $this->assertCount(3, $sentMessages);

        foreach ($recipients as $index => $recipient) {
            $this->assertEquals($recipient, $sentMessages[$index]->recipient);
            $this->assertEquals('sent', $sentMessages[$index]->status);
            $this->assertEquals('batch_001', $sentMessages[$index]->provider_message_id);
            $this->assertEquals('http', $sentMessages[$index]->provider);
        }

        // Native bulk: one HTTP call, not one per recipient.
        Http::assertSentCount(1);
    }

    public function test_send_bulk_marks_all_failed_on_error_response(): void
    {
        Http::fake([
            'https://api.example.com/send-bulk' => Http::response(['error' => 'gateway down'], 500),
        ]);

        $sentMessages = $this->smsService->sendBulk(['+9779801002468', '+9779812345678'], 'Bulk');

        $this->assertCount(2, $sentMessages);
        $this->assertTrue($sentMessages->every(fn ($m) => $m->status === 'failed'));
    }

    public function test_balance_uses_the_selected_provider(): void
    {
        config(['laravel-sms.providers.aakash' => [
            'class' => \Rayzenai\LaravelSms\Providers\AakashSmsProvider::class,
            'auth_token' => 'tok',
        ]]);

        Http::fake([
            'https://sms.aakashsms.com/sms/v4/available-credit' => Http::response(['available_credit' => 500], 200),
        ]);

        $this->assertEquals(500, $this->smsService->provider('aakash')->balance()['credit']);
    }

    public function test_balance_throws_when_provider_does_not_support_it(): void
    {
        // The default 'http' provider does not implement ReportsBalance.
        $this->expectException(\Rayzenai\LaravelSms\Exceptions\UnsupportedFeatureException::class);

        $this->smsService->balance();
    }

    public function test_send_records_failure_and_rethrows_on_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout', 28);
        });

        try {
            $this->smsService->send('+9779801002468', 'Test message');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Connection timeout', $e->getMessage());
        }

        $sentMessage = SentMessage::first();
        $this->assertEquals('failed', $sentMessage->status);
        $this->assertEquals('Connection timeout', $sentMessage->provider_response['error']);
    }
}
