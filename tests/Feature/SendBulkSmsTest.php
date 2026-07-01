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
    public function it_can_send_bulk_sms_and_store_multiple_records()
    {
        $recipients = ['+9779812345678', '+9779823456789', '+9779834567890'];

        $this->mockProvider()
            ->shouldReceive('sendBulk')
            ->once()
            ->andReturn(['status' => 'sent', 'batch_id' => 'BATCH1', 'recipients_count' => 3, 'response' => []]);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => 'Bulk test message',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bulk SMS processed',
                'data' => ['total' => 3, 'successful' => 3, 'failed' => 0],
            ]);

        $this->assertDatabaseCount('sent_messages', 3);
        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('sent_messages', [
                'recipient' => $recipient,
                'status' => 'sent',
                'provider' => 'twilio',
                'provider_message_id' => 'BATCH1',
            ]);
        }
    }

    /** @test */
    public function it_validates_required_fields_for_bulk_sms()
    {
        $this->postJson('/api/sms/send-bulk', ['message' => 'Test message'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipients']);

        $this->postJson('/api/sms/send-bulk', ['recipients' => ['+9779812345678']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->postJson('/api/sms/send-bulk', ['recipients' => [], 'message' => 'Test message'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipients']);
    }

    /** @test */
    public function it_validates_recipient_format_in_bulk()
    {
        $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['', null, '+9779812345678'],
            'message' => 'Test message',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipients.0', 'recipients.1']);
    }

    /** @test */
    public function it_records_all_recipients_as_failed_when_the_batch_fails()
    {
        $recipients = ['+9779812345678', '+9779823456789'];

        $this->mockProvider()
            ->shouldReceive('sendBulk')
            ->once()
            ->andReturn(['status' => 'failed', 'batch_id' => null, 'recipients_count' => 2, 'response' => []]);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => 'Bulk test message',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['total' => 2, 'successful' => 0, 'failed' => 2],
            ]);

        $this->assertEquals(2, SentMessage::where('status', 'failed')->count());
    }

    /** @test */
    public function it_returns_individual_results_for_each_recipient()
    {
        $recipients = ['+9779812345678', '+9779823456789'];

        $this->mockProvider()
            ->shouldReceive('sendBulk')
            ->once()
            ->andReturn(['status' => 'sent', 'batch_id' => 'BATCH1', 'recipients_count' => 2, 'response' => []]);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => $recipients,
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);

        $results = $response->json('data.results');
        $this->assertCount(2, $results);

        foreach ($results as $index => $result) {
            $this->assertEquals($recipients[$index], $result['recipient']);
            $this->assertEquals('sent', $result['status']);
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('sent_at', $result);
        }
    }

    /** @test */
    public function it_handles_provider_exception_as_a_server_error()
    {
        $this->mockProvider()
            ->shouldReceive('sendBulk')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => ['+9779812345678', '+9779823456789'],
            'message' => 'Test message',
        ]);

        $response->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'Failed to send bulk SMS']);

        // Records are still written (as failed) before the exception surfaces.
        $this->assertEquals(2, SentMessage::where('status', 'failed')->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
