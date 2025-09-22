<?php

namespace Rayzenai\LaravelSms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Rayzenai\LaravelSms\Tests\TestCase;

class NepaliPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up minimal config for validation testing
        config([
            'laravel-sms.api_base_url' => 'https://api.example.com',
            'laravel-sms.api_key' => 'test-key',
            'laravel-sms.default_sender' => 'TestApp',
            'laravel-sms.default_provider' => \Rayzenai\LaravelSms\Providers\HttpProvider::class,
        ]);
    }

    /** @test */
    public function it_rejects_invalid_nepali_phone_numbers_for_single_sms()
    {
        $invalidNumbers = [
            '9801002468',           // Missing country code
            '+977 801002468',       // Missing 9 prefix for mobile
            '+977 980100246',       // Too few digits
            '+977 98010024689',     // Too many digits
            '+1 9801002468',        // Wrong country code
            '+91 9801002468',       // Indian country code
            '977 9801002468',       // Missing + sign
            'invalid',              // Not a number
            '',                     // Empty string
        ];

        foreach ($invalidNumbers as $number) {
            $response = $this->postJson('/api/sms/send', [
                'recipient' => $number,
                'message' => 'Test message',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['recipient']);
        }
    }

    /** @test */
    public function it_accepts_valid_nepali_phone_numbers_for_single_sms()
    {
        // Mock HTTP response to prevent actual API calls
        Http::fake(['*' => Http::response(['message_id' => 'test123', 'status' => 'sent'], 200)]);

        $validNumbers = [
            '+977 9801002468',      // With space
            '+9779801002468',       // Without space
            '+977 9812345678',      // Another valid number
            '+977-980-100-2468',    // With dashes
            '+977.980.100.2468',    // With dots
        ];

        foreach ($validNumbers as $number) {
            $response = $this->postJson('/api/sms/send', [
                'recipient' => $number,
                'message' => 'Test message',
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'SMS sent successfully',
                ]);
        }
    }

    /** @test */
    public function it_validates_nepali_phone_numbers_in_bulk_sms()
    {
        // Test with mixed valid and invalid numbers
        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => [
                '+9779801002468',    // Valid
                '9801002468',        // Invalid - missing country code
                '+91 9801002468',    // Invalid - wrong country code
                '+9779812345678',    // Valid
            ],
            'message' => 'Test bulk message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipients.1', 'recipients.2'])
            ->assertJsonMissingValidationErrors(['recipients.0', 'recipients.3']);
    }

    /** @test */
    public function it_accepts_all_valid_nepali_numbers_in_bulk()
    {
        // Mock HTTP response to prevent actual API calls
        Http::fake(['*' => Http::response(['message_id' => 'test123', 'status' => 'sent'], 200)]);

        $response = $this->postJson('/api/sms/send-bulk', [
            'recipients' => [
                '+9779801002468',
                '+9779812345678',
                '+9779823456789',
            ],
            'message' => 'Test bulk message',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bulk SMS processed',
                'data' => [
                    'total' => 3,
                ],
            ]);
    }

    /** @test */
    public function it_provides_helpful_error_message_for_invalid_numbers()
    {
        $response = $this->postJson('/api/sms/send', [
            'recipient' => '+1234567890',
            'message' => 'Test message',
        ]);

        $response->assertStatus(422);
        
        $errors = $response->json('errors.recipient');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Nepali mobile phone number', $errors[0]);
        $this->assertStringContainsString('+977', $errors[0]);
    }
}
