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

        Config::set('laravel-sms.default', 'twilio');
        Config::set('laravel-sms.providers.twilio', [
            'class' => TwilioProvider::class,
            'account_sid' => 'test_account_sid',
            'auth_token' => 'test_auth_token',
            'from' => '+9779801002468',
        ]);
    }

    protected function mockProvider(): Mockery\MockInterface
    {
        $mock = Mockery::mock(TwilioProvider::class);
        $this->app->instance(TwilioProvider::class, $mock);

        return $mock;
    }

    /** @test */
    public function single_sms_endpoint_returns_correct_content_type()
    {
        $this->mockProvider()->shouldReceive('send')->once()
            ->andReturn(['sid' => 'SM123', 'status' => 'sent']);

        $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ])->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    }

    /** @test */
    public function bulk_sms_endpoint_returns_correct_content_type()
    {
        $this->mockProvider()->shouldReceive('sendBulk')->once()
            ->andReturn(['status' => 'sent', 'batch_id' => 'B1', 'recipients_count' => 1, 'response' => []]);

        $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['+9779812345678'],
            'message' => 'Test message',
        ])->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    }

    /** @test */
    public function single_sms_endpoint_response_has_consistent_structure()
    {
        $this->mockProvider()->shouldReceive('send')->once()
            ->andReturn(['sid' => 'SM123456789', 'status' => 'sent']);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('id', $json['data']);
        $this->assertArrayHasKey('recipient', $json['data']);
        $this->assertArrayHasKey('status', $json['data']);
        $this->assertArrayHasKey('provider_message_id', $json['data']);
        $this->assertArrayHasKey('sent_at', $json['data']);

        $this->assertIsBool($json['success']);
        $this->assertIsInt($json['data']['id']);
        $this->assertEquals('SM123456789', $json['data']['provider_message_id']);
    }

    /** @test */
    public function bulk_sms_endpoint_response_has_consistent_structure()
    {
        $this->mockProvider()->shouldReceive('sendBulk')->once()
            ->andReturn(['status' => 'sent', 'batch_id' => 'B1', 'recipients_count' => 2, 'response' => []]);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['+9779812345678', '+9779823456789'],
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('total', $json['data']);
        $this->assertArrayHasKey('successful', $json['data']);
        $this->assertArrayHasKey('failed', $json['data']);
        $this->assertArrayHasKey('results', $json['data']);
        $this->assertIsInt($json['data']['total']);
        $this->assertIsArray($json['data']['results']);

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
        $this->mockProvider()->shouldReceive('send')->once()
            ->andThrow(new \Exception('Provider error'));

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ]);

        $response->assertStatus(500);
        $json = $response->json();

        $this->assertFalse($json['success']);
        $this->assertEquals('Failed to send SMS', $json['message']);
        $this->assertEquals('Provider error', $json['error']);
    }

    /** @test */
    public function validation_error_responses_follow_laravel_standard()
    {
        $response = $this->postJson('/api/sms/send', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['recipient', 'message']]);

        $this->assertStringContainsString('recipient field is required', $response->json('message'));
    }

    /** @test */
    public function api_routes_are_prefixed_correctly()
    {
        $this->postJson('/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ])->assertStatus(404);

        $this->postJson('/sms/send-bulk', [
            'recipients' => ['+9779812345678'],
            'message' => 'Test message',
        ])->assertStatus(404);
    }

    /** @test */
    public function sent_at_field_contains_valid_timestamp()
    {
        $this->mockProvider()->shouldReceive('send')->once()
            ->andReturn(['sid' => 'SM123', 'status' => 'sent']);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.sent_at'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
