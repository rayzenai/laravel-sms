<?php

namespace Rayzenai\LaravelSms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Providers\TwilioProvider;
use Rayzenai\LaravelSms\Tests\TestCase;

class SendBulkSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up default config
        Config::set('laravel-sms.default_provider', TwilioProvider::class);
        Config::set('laravel-sms.providers.twilio', [
            'class' => TwilioProvider::class,
            'account_sid' => 'test_account_sid',
            'auth_token' => 'test_auth_token',
            'from' => '+9779801002468',
        ]);
    }

    /** @test */
    public function it_can_send_bulk_sms_and_store_multiple_records()
    {
        $recipients = ['+9779812345678', '+9779823456789', '+9779834567890'];
        $message = 'Bulk test message';

        // Mock the provider without sending
        $mockProvider = Mockery::mock(TwilioProvider::class)->makePartial();
        $this->app->instance(TwilioProvider::class, $mockProvider);

        // Skip actual sending in tests
        $mockProvider->shouldReceive('send')->andReturn(true);
        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => $message,
        ]);

        // Assert response structure and status
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total',
                    'successful',
                    'failed',
                    'results' => [
                        '*' => [
                            'id',
                            'recipient',
                            'status',
                            'provider_message_id',
                            'sent_at',
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Bulk SMS processed',
                'data' => [
                    'total' => 3,
                    'successful' => 3,
                    'failed' => 0,
                ]
            ]);

        // Assert database records were created
        $this->assertDatabaseCount('sent_messages', 3);
        
        foreach ($recipients as $index => $recipient) {
            $this->assertDatabaseHas('sent_messages', [
                'recipient' => $recipient,
                'message' => $message,
                'status' => 'sent',
                'provider' => TwilioProvider::class,
                'provider_message_id' => 'SM12345678' . $index,
            ]);
        }
    }

    /** @test */
    public function it_validates_required_fields_for_bulk_sms()
    {
        // Test missing recipients
        $response = $this->postJson('/api/sms/send-bulk', [
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipients']);

        // Test missing message
        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['+9779812345678'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        // Test empty recipients array
        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => [],
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipients']);
    }

    /** @test */
    public function it_validates_recipient_format_in_bulk()
    {
        // Test invalid recipient format
        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['', null, '+9779812345678'],
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipients.0', 'recipients.1']);
    }

    /** @test */
    public function it_handles_partial_failures_in_bulk_send()
    {
        $recipients = ['+9779812345678', '+9779823456789', '+9779834567890'];
        $message = 'Bulk test message';

        // Mock the provider with mixed results
        $mockProvider = Mockery::mock(TwilioProvider::class);
        
        // First recipient succeeds
        $mockProvider->shouldReceive('send')
            ->once()
            ->with($recipients[0], $message)
            ->andReturn([
                'sid' => 'SM123456780',
                'status' => 'sent',
            ]);

        // Second recipient fails
        $mockProvider->shouldReceive('send')
            ->once()
            ->with($recipients[1], $message)
            ->andThrow(new \Exception('Invalid phone number'));

        // Third recipient succeeds
        $mockProvider->shouldReceive('send')
            ->once()
            ->with($recipients[2], $message)
            ->andReturn([
                'sid' => 'SM123456782',
                'status' => 'sent',
            ]);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => $message,
        ]);

        // Should still return 200 with partial success
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bulk SMS processed',
                'data' => [
                    'total' => 3,
                    'successful' => 2,
                    'failed' => 1,
                ]
            ]);

        // Assert all records are created (including failed ones)
        $this->assertDatabaseCount('sent_messages', 3);
        
        // Check successful messages
        $this->assertDatabaseHas('sent_messages', [
            'recipient' => $recipients[0],
            'status' => 'sent',
        ]);
        
        $this->assertDatabaseHas('sent_messages', [
            'recipient' => $recipients[2],
            'status' => 'sent',
        ]);
        
        // Check failed message
        $this->assertDatabaseHas('sent_messages', [
            'recipient' => $recipients[1],
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function it_returns_individual_results_for_each_recipient()
    {
        $recipients = ['+9876543210', '+9876543211'];
        $message = 'Test message';

        $mockProvider = Mockery::mock(TwilioProvider::class);
        
        foreach ($recipients as $index => $recipient) {
            $mockProvider->shouldReceive('send')
                ->once()
                ->with($recipient, $message)
                ->andReturn([
                    'sid' => 'SM12345678' . $index,
                    'status' => 'sent',
                ]);
        }

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => $message,
        ]);

        $response->assertStatus(200);
        
        $responseData = $response->json('data.results');
        $this->assertCount(2, $responseData);
        
        // Check each result has the correct structure
        foreach ($responseData as $index => $result) {
            $this->assertEquals($recipients[$index], $result['recipient']);
            $this->assertEquals('sent', $result['status']);
            $this->assertEquals('SM12345678' . $index, $result['provider_message_id']);
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('sent_at', $result);
        }
    }

    /** @test */
    public function it_handles_complete_failure_gracefully()
    {
        $recipients = ['+9876543210', '+9876543211'];
        $message = 'Test message';

        // Mock provider to fail for all recipients
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->twice()
            ->andThrow(new \Exception('Service unavailable'));

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => $message,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bulk SMS processed',
                'data' => [
                    'total' => 2,
                    'successful' => 0,
                    'failed' => 2,
                ]
            ]);

        // All messages should be recorded as failed
        $this->assertDatabaseCount('sent_messages', 2);
        $this->assertEquals(2, SentMessage::where('status', 'failed')->count());
    }

    /** @test */
    public function it_processes_large_bulk_requests()
    {
        // Create 50 recipients
        $recipients = [];
        for ($i = 0; $i < 50; $i++) {
            $recipients[] = '+987654' . str_pad($i, 4, '0', STR_PAD_LEFT);
        }
        
        $message = 'Large bulk test';

        $mockProvider = Mockery::mock(TwilioProvider::class);
        
        foreach ($recipients as $index => $recipient) {
            $mockProvider->shouldReceive('send')
                ->once()
                ->with($recipient, $message)
                ->andReturn([
                    'sid' => 'SM' . str_pad($index, 10, '0', STR_PAD_LEFT),
                    'status' => 'sent',
                ]);
        }

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => $message,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 50,
                    'successful' => 50,
                    'failed' => 0,
                ]
            ]);

        $this->assertDatabaseCount('sent_messages', 50);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
