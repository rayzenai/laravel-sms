<?php

namespace Rayzenai\LaravelSms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Rayzenai\LaravelSms\Providers\TwilioProvider;
use Rayzenai\LaravelSms\Tests\TestCase;

class SmsApiEndpointsTest extends TestCase
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
    public function single_sms_endpoint_returns_correct_content_type()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andReturn(['sid' => 'SM123', 'status' => 'sent']);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    /** @test */
    public function bulk_sms_endpoint_returns_correct_content_type()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andReturn(['sid' => 'SM123', 'status' => 'sent']);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['+9876543210'],
            'message' => 'Test message',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }


    /** @test */
    public function single_sms_endpoint_response_has_consistent_structure()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andReturn([
                'sid' => 'SM123456789',
                'status' => 'sent',
                'additional_data' => 'some value',
            ]);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        
        $json = $response->json();
        
        // Assert root structure
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);
        
        // Assert data structure
        $this->assertArrayHasKey('id', $json['data']);
        $this->assertArrayHasKey('recipient', $json['data']);
        $this->assertArrayHasKey('status', $json['data']);
        $this->assertArrayHasKey('provider_message_id', $json['data']);
        $this->assertArrayHasKey('sent_at', $json['data']);
        
        // Assert data types
        $this->assertIsBool($json['success']);
        $this->assertIsString($json['message']);
        $this->assertIsArray($json['data']);
        $this->assertIsInt($json['data']['id']);
        $this->assertIsString($json['data']['recipient']);
        $this->assertIsString($json['data']['status']);
        $this->assertIsString($json['data']['provider_message_id']);
    }

    /** @test */
    public function bulk_sms_endpoint_response_has_consistent_structure()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->twice()
            ->andReturn(['sid' => 'SM123', 'status' => 'sent']);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['+9876543210', '+9876543211'],
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        
        $json = $response->json();
        
        // Assert root structure
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);
        
        // Assert data structure
        $this->assertArrayHasKey('total', $json['data']);
        $this->assertArrayHasKey('successful', $json['data']);
        $this->assertArrayHasKey('failed', $json['data']);
        $this->assertArrayHasKey('results', $json['data']);
        
        // Assert data types
        $this->assertIsBool($json['success']);
        $this->assertIsString($json['message']);
        $this->assertIsArray($json['data']);
        $this->assertIsInt($json['data']['total']);
        $this->assertIsInt($json['data']['successful']);
        $this->assertIsInt($json['data']['failed']);
        $this->assertIsArray($json['data']['results']);
        
        // Assert each result structure
        foreach ($json['data']['results'] as $result) {
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('recipient', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('provider_message_id', $result);
            $this->assertArrayHasKey('sent_at', $result);
        }
    }

    /** @test */
    public function error_responses_have_consistent_structure()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('Provider error'));

        $this->app->instance(TwilioProvider::class, $mockProvider);

        // Test single SMS error response
        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(500);
        
        $json = $response->json();
        
        // Assert error response structure
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('error', $json);
        
        // Assert values
        $this->assertFalse($json['success']);
        $this->assertEquals('Failed to send SMS', $json['message']);
        $this->assertEquals('Provider error', $json['error']);
    }

    /** @test */
    public function validation_error_responses_follow_laravel_standard()
    {
        $response = $this->postJson('/api/sms/send', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'recipient',
                    'message',
                ]
            ]);
        
        $json = $response->json();
        // Laravel 11 includes error count in the message
        $this->assertStringContainsString('The recipient field is required', $json['message']);
        $this->assertIsArray($json['errors']['recipient']);
        $this->assertIsArray($json['errors']['message']);
    }

    /** @test */
    public function api_routes_are_prefixed_correctly()
    {
        // Ensure routes without /api prefix don't work
        $response = $this->postJson('/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(404);

        $response = $this->postJson('/sms/send-bulk', [
            'recipients' => ['+9876543210'],
            'message' => 'Test message',
        ]);

        $response->assertStatus(404);
    }


    /** @test */
    public function sent_at_field_contains_valid_timestamp()
    {
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')
            ->once()
            ->andReturn(['sid' => 'SM123', 'status' => 'sent']);

        $this->app->instance(TwilioProvider::class, $mockProvider);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9876543210',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        
        $sentAt = $response->json('data.sent_at');
        
        // Verify it's a valid timestamp
        $this->assertNotNull($sentAt);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d{6}Z$/',
            $sentAt
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
