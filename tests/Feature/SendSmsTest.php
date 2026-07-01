<?php

namespace Rayzenai\LaravelSms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Providers\TwilioProvider;
use Rayzenai\LaravelSms\Tests\TestCase;

class SendSmsTest extends TestCase
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
    public function it_can_send_single_sms_and_store_record()
    {
        $this->mockProvider()
            ->shouldReceive('send')
            ->once()
            ->andReturn(['sid' => 'SM123456789', 'status' => 'sent']);

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'recipient' => '+9779812345678',
                    'status' => 'sent',
                    'provider_message_id' => 'SM123456789',
                ],
            ]);

        $this->assertDatabaseCount('sent_messages', 1);
        $this->assertDatabaseHas('sent_messages', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
            'status' => 'sent',
            'provider' => 'twilio',
            'provider_message_id' => 'SM123456789',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_single_sms()
    {
        $this->postJson('/api/sms/send', ['message' => 'Test message'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient']);

        $this->postJson('/api/sms/send', ['recipient' => '+9779812345678'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->postJson('/api/sms/send', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient', 'message']);
    }

    /** @test */
    public function it_validates_message_length()
    {
        $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => str_repeat('a', 1601),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function it_handles_provider_failure_gracefully()
    {
        $this->mockProvider()
            ->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('Provider error: Invalid phone number'));

        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to send SMS',
                'error' => 'Provider error: Invalid phone number',
            ]);

        $this->assertDatabaseHas('sent_messages', [
            'recipient' => '+9779812345678',
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function it_stores_provider_response_in_database()
    {
        $providerResponse = [
            'sid' => 'SM123456789',
            'status' => 'sent',
            'to' => '+9779812345678',
        ];

        $this->mockProvider()
            ->shouldReceive('send')
            ->once()
            ->andReturn(['sid' => 'SM123456789', 'status' => 'sent', 'response' => $providerResponse]);

        $this->postJson('/api/sms/send', [
            'recipient' => '+9779812345678',
            'message' => 'Test message',
        ])->assertStatus(200);

        $this->assertEquals($providerResponse, SentMessage::first()->provider_response);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
