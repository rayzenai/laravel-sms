<?php

namespace Rayzenai\LaravelSms\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Rayzenai\LaravelSms\Services\SmsService;
use Rayzenai\LaravelSms\Providers\HttpProvider;
use Rayzenai\LaravelSms\Tests\TestCase;

class SmsServiceTest extends TestCase
{
    protected SmsService $smsService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up config for testing
        config([
            'laravel-sms.api_base_url' => 'https://api.example.com',
            'laravel-sms.api_key' => 'test-api-key',
            'laravel-sms.default_sender' => 'TestApp',
            'laravel-sms.default' => 'test',
            'laravel-sms.default_provider' => 'http',
            'laravel-sms.timeout' => 30,
            'laravel-sms.logging.enabled' => false,
        ]);
        
        // Create HttpProvider and inject it into SmsService
        $provider = new HttpProvider();
        $this->smsService = new SmsService($provider);
    }
    
    public function test_send_single_sms_successfully()
    {
        // Mock successful API response
        Http::fake([
            'https://api.example.com/send' => Http::response([
                'success' => true,
                'message_id' => 'msg_123456',
                'status' => 'sent',
            ], 200),
        ]);
        
        $sentMessage = $this->smsService->send('+1234567890', 'Test message');
        
        $this->assertEquals('+1234567890', $sentMessage->recipient);
        $this->assertEquals('Test message', $sentMessage->message);
        $this->assertEquals('TestApp', $sentMessage->sender);
        $this->assertEquals('sent', $sentMessage->status);
        $this->assertEquals('http', $sentMessage->provider);
        $this->assertEquals('msg_123456', $sentMessage->provider_message_id);
        $this->assertNotNull($sentMessage->sent_at);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/send' &&
                   $request['recipient'] === '+1234567890' &&
                   $request['message'] === 'Test message' &&
                   $request['sender'] === 'TestApp' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    }
    
    public function test_send_single_sms_handles_failure()
    {
        // Mock failed API response
        Http::fake([
            'https://api.example.com/send' => Http::response([
                'success' => false,
                'error' => 'Invalid recipient',
            ], 400),
        ]);
        
        $sentMessage = $this->smsService->send('+1234567890', 'Test message');
        
        $this->assertEquals('failed', $sentMessage->status);
        $this->assertArrayHasKey('error', $sentMessage->provider_response);
    }
    
    public function test_send_bulk_sms_successfully()
    {
        // Mock individual API responses for bulk send
        Http::fake([
            'https://api.example.com/send' => Http::sequence()
                ->push(['success' => true, 'message_id' => 'msg_001', 'status' => 'sent'], 200)
                ->push(['success' => true, 'message_id' => 'msg_002', 'status' => 'sent'], 200)
                ->push(['success' => false, 'error' => 'Invalid number'], 400),
        ]);
        
        $recipients = ['+1234567890', '+0987654321', '+1111111111'];
        $sentMessages = $this->smsService->sendBulk($recipients, 'Bulk test message');
        
        $this->assertCount(3, $sentMessages);
        
        // Check first message
        $this->assertEquals('+1234567890', $sentMessages[0]->recipient);
        $this->assertEquals('sent', $sentMessages[0]->status);
        $this->assertEquals('msg_001', $sentMessages[0]->provider_message_id);
        
        // Check second message
        $this->assertEquals('+0987654321', $sentMessages[1]->recipient);
        $this->assertEquals('sent', $sentMessages[1]->status);
        $this->assertEquals('msg_002', $sentMessages[1]->provider_message_id);
        
        // Check third message (failed)
        $this->assertEquals('+1111111111', $sentMessages[2]->recipient);
        $this->assertEquals('failed', $sentMessages[2]->status);
        $this->assertArrayHasKey('error', $sentMessages[2]->provider_response);
        
        // Assert that three individual send requests were made
        Http::assertSentCount(3);
    }
    
    public function test_send_bulk_sms_without_individual_results()
    {
        // Mock successful individual API responses
        Http::fake([
            'https://api.example.com/send' => Http::sequence()
                ->push(['success' => true, 'message_id' => 'msg_001', 'status' => 'sent'], 200)
                ->push(['success' => true, 'message_id' => 'msg_002', 'status' => 'sent'], 200),
        ]);
        
        $recipients = ['+1234567890', '+0987654321'];
        $sentMessages = $this->smsService->sendBulk($recipients, 'Bulk test message');
        
        $this->assertCount(2, $sentMessages);
        
        // Check first message
        $this->assertEquals('+1234567890', $sentMessages[0]->recipient);
        $this->assertEquals('sent', $sentMessages[0]->status);
        $this->assertEquals('msg_001', $sentMessages[0]->provider_message_id);
        
        // Check second message
        $this->assertEquals('+0987654321', $sentMessages[1]->recipient);
        $this->assertEquals('sent', $sentMessages[1]->status);
        $this->assertEquals('msg_002', $sentMessages[1]->provider_message_id);
    }
    
    public function test_send_handles_http_exception()
    {
        // Mock HTTP exception
        Http::fake(function () {
            throw new \Exception('Connection timeout', 28);
        });
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Connection timeout');
        
        $this->smsService->send('+1234567890', 'Test message');
    }
    
    public function test_send_bulk_handles_http_exception()
    {
        // Mock HTTP exception
        Http::fake(function () {
            throw new \Exception('Connection timeout', 28);
        });
        
        $recipients = ['+1234567890', '+0987654321'];
        $sentMessages = $this->smsService->sendBulk($recipients, 'Bulk test message');
        
        $this->assertCount(2, $sentMessages);
        
        foreach ($sentMessages as $sentMessage) {
            $this->assertEquals('failed', $sentMessage->status);
            $this->assertEquals('Connection timeout', $sentMessage->provider_response['error']);
            $this->assertEquals(28, $sentMessage->provider_response['code']);
        }
    }
}
