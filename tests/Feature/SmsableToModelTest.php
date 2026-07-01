<?php

namespace Rayzenai\LaravelSms\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Rayzenai\LaravelSms\Concerns\Smsable;
use Rayzenai\LaravelSms\Contracts\HasSmsNumber;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Tests\TestCase;

class SmsableToModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'laravel-sms.default' => 'http',
            'laravel-sms.providers.http' => [
                'class' => \Rayzenai\LaravelSms\Providers\HttpProvider::class,
                'api_base_url' => 'https://api.example.com',
                'api_key' => 'k',
            ],
            'laravel-sms.logging.enabled' => false,
        ]);

        Http::fake(['*' => Http::response(['message_id' => 'mid_1'], 200)]);
    }

    public function test_send_to_a_model_resolves_its_number(): void
    {
        $user = new SmsTestUser(['phone' => '+9779801002468']);

        $message = \Rayzenai\LaravelSms\Facades\Sms::to($user)->message('hi')->send();

        $this->assertInstanceOf(SentMessage::class, $message);
        $this->assertEquals('+9779801002468', $message->recipient);
    }

    public function test_send_sms_helper_on_the_model(): void
    {
        $user = new SmsTestUser(['phone' => '+9779801002468']);

        $this->assertInstanceOf(SentMessage::class, $user->sendSMS('hello'));
    }

    public function test_send_sms_helper_returns_null_when_no_number(): void
    {
        $user = new SmsTestUser(['phone' => null]);

        $this->assertNull($user->sendSMS('hello'));
        Http::assertNothingSent();
    }

    public function test_single_send_throws_when_the_model_has_no_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        \Rayzenai\LaravelSms\Facades\Sms::to(new SmsTestUser(['phone' => null]))->message('x')->send();
    }

    public function test_bulk_send_skips_models_without_a_number(): void
    {
        $recipients = [
            new SmsTestUser(['phone' => '+9779801002468']),
            new SmsTestUser(['phone' => null]),        // skipped
            '+9779812345678',                           // plain string still works
        ];

        $sent = \Rayzenai\LaravelSms\Facades\Sms::to($recipients)->message('hi all')->sendBulk();

        $this->assertCount(2, $sent);
        $this->assertEqualsCanonicalizing(
            ['+9779801002468', '+9779812345678'],
            $sent->pluck('recipient')->all()
        );
    }

    public function test_non_recipient_object_throws_a_helpful_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Implement');

        \Rayzenai\LaravelSms\Facades\Sms::to(new \stdClass)->message('x')->send();
    }
}

class SmsTestUser extends Model implements HasSmsNumber
{
    use Smsable;

    protected $guarded = [];

    public $timestamps = false;

    public function smsPhoneNumber(): ?string
    {
        return $this->phone;
    }
}
