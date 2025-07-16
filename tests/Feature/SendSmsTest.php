<?php

namespace Rayzenai\LaravelSms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Providers\TwilioProvider;
use Rayzenai\LaravelSms\Services\SmsService;
use Rayzenai\LaravelSms\Tests\TestCase;

class SendSmsTest extends TestCase
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
            'from' => '+1234567890',
        ]);
    }

    /** @test */
    public function it_can_send_single_sms_and_store_record()
    {
        // Mock the provider
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->with('+9876543210', 'Test message')
            ->andReturn([
                'sid' => 'SM123456789',
                'status' => 'sent',
                'to' => '+9876543210',
                'body' => 'Test message',
            ]);

        // Bind the mock to the container
        $this->app->instance(TwilioProvider::class, $mockProvider);

        // Make the API request
        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        // Assert response structure and status
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'recipient',
                    'status',
                    'provider_message_id',
                    'sent_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'recipient' => '+9876543210',
                    'status' => 'sent',
                    'provider_message_id' => 'SM123456789',
                ]
            ]);

        // Assert database record was created
        $this->assertDatabaseCount('sent_messages', 1);
        $this->assertDatabaseHas('sent_messages', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
            'status' => 'sent',
            'provider' => TwilioProvider::class,
            'provider_message_id' => 'SM123456789',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_single_sms()
    {
        // Test missing recipient
        $response = $this->postJson('/api/sms/send', [
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient']);

        // Test missing message
        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        // Test empty request
        $response = $this->postJson('/api/sms/send', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient', 'message']);
    }

    /** @test */
    public function it_validates_message_length()
    {
        $longMessage = str_repeat('a', 1601); // 1601 characters

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => $longMessage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function it_handles_provider_failure_gracefully()
    {
        // Mock the provider to throw an exception
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('Provider error: Invalid phone number'));

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to send SMS',
                'error' => 'Provider error: Invalid phone number',
            ]);

        // Assert that failed message is still recorded
        $this->assertDatabaseCount('sent_messages', 1);
        $this->assertDatabaseHas('sent_messages', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function it_stores_provider_response_in_database()
    {
        $providerResponse = [
            'sid' => 'SM123456789',
            'status' => 'sent',
            'to' => '+9876543210',
            'body' => 'Test message',
            'date_created' => '2023-12-01T10:00:00Z',
            'price' => '0.0075',
            'price_unit' => 'USD',
        ];

        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andReturn($providerResponse);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);

        $sentMessage = SentMessage::first();
        $this->assertNotNull($sentMessage);
        $this->assertEquals($providerResponse, $sentMessage->provider_response);
    }

    /** @test */
    public function it_returns_correct_json_structure_on_success()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andReturn([
                'sid' => 'SM123456789',
                'status' => 'sent',
            ]);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'recipient' => '+9876543210',
                    'status' => 'sent',
                    'provider_message_id' => 'SM123456789',
                ]
            ]);
        
        // Check that sent_at is present and valid
        $responseData = $response->json('data');
        $this->assertArrayHasKey('sent_at', $responseData);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals(SentMessage::first()->id, $responseData['id']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
