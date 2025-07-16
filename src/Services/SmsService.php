<?php

namespace Rayzenai\LaravelSms\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rayzenai\LaravelSms\Models\SentMessage;
use Rayzenai\LaravelSms\Providers\SmsProviderInterface;

class SmsService
{
    protected SmsProviderInterface $provider;

    public function __construct(SmsProviderInterface $provider)
    {
        $this->provider = $provider;
    }
    /**
     * Send SMS to a single recipient.
     *
     * @param string $recipient The recipient's phone number
     * @param string $message The message content
     * @return SentMessage
     * @throws \Exception
     */
    public function send(string $recipient, string $message): SentMessage
    {
        try {
            // Use the provider to send the message
            $result = $this->provider->send($recipient, $message);
            
            // Log the request if logging is enabled
            if (config('laravel-sms.logging.enabled')) {
                Log::channel(config('laravel-sms.logging.channel', 'stack'))
                    ->info('SMS sent', [
                        'recipient' => $recipient,
                        'message' => $message,
                        'response' => $result,
                    ]);
            }
            
            // Create and save the sent message record
            $sentMessage = new SentMessage();
            $sentMessage->recipient = $recipient;
            $sentMessage->message = $message;
            $sentMessage->sender = config('laravel-sms.default_sender');
            $sentMessage->status = $result['status'];
            $sentMessage->provider = config('laravel-sms.default_provider', 'http');
            $sentMessage->provider_message_id = $result['sid'] ?? null;
            $sentMessage->provider_response = $result['response'] ?? $result;
            $sentMessage->sent_at = now();
            $sentMessage->save();
            
            return $sentMessage;
            
        } catch (\Exception $e) {
            // Log the error if logging is enabled
            if (config('laravel-sms.logging.enabled')) {
                Log::channel(config('laravel-sms.logging.channel', 'stack'))
                    ->error('SMS send failed', [
                        'recipient' => $recipient,
                        'message' => $message,
                        'error' => $e->getMessage(),
                    ]);
            }
            
            // Create a failed message record
            $sentMessage = new SentMessage();
            $sentMessage->recipient = $recipient;
            $sentMessage->message = $message;
            $sentMessage->sender = config('laravel-sms.default_sender');
            $sentMessage->status = 'failed';
            $sentMessage->provider = config('laravel-sms.default_provider', 'http');
            $sentMessage->provider_response = [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
            $sentMessage->sent_at = now();
            $sentMessage->save();
            
            throw $e;
        }
    }
    
    /**
     * Send SMS to multiple recipients.
     *
     * @param array $recipients Array of recipient phone numbers
     * @param string $message The message content
     * @return Collection Collection of SentMessage models
     */
    public function sendBulk(array $recipients, string $message): Collection
    {
        try {
            // Use the provider's bulk send method
            $result = $this->provider->sendBulk($recipients, $message);
            
            // Log the request if logging is enabled
            if (config('laravel-sms.logging.enabled')) {
                Log::channel(config('laravel-sms.logging.channel', 'stack'))
                    ->info('Bulk SMS sent', [
                        'recipients' => $recipients,
                        'message' => $message,
                        'response' => $result,
                    ]);
            }
            
            $sentMessages = collect();
            $status = $result['status'] ?? 'failed';
            $batchId = $result['batch_id'] ?? null;
            
            // Create sent message records for each recipient
            foreach ($recipients as $recipient) {
                $sentMessage = new SentMessage();
                $sentMessage->recipient = $recipient;
                $sentMessage->message = $message;
                $sentMessage->sender = config('laravel-sms.default_sender');
                $sentMessage->status = $status;
                $sentMessage->provider = config('laravel-sms.default_provider', 'http');
                $sentMessage->provider_message_id = $batchId;
                $sentMessage->provider_response = $result;
                $sentMessage->sent_at = now();
                $sentMessage->save();
                
                $sentMessages->push($sentMessage);
            }
            
            return $sentMessages;
            
        } catch (\Exception $e) {
            // Log the error if logging is enabled
            if (config('laravel-sms.logging.enabled')) {
                Log::channel(config('laravel-sms.logging.channel', 'stack'))
                    ->error('Bulk SMS send failed', [
                        'recipients' => $recipients,
                        'message' => $message,
                        'error' => $e->getMessage(),
                    ]);
            }
            
            // Create failed message records
            $sentMessages = collect();
            foreach ($recipients as $recipient) {
                $sentMessage = new SentMessage();
                $sentMessage->recipient = $recipient;
                $sentMessage->message = $message;
                $sentMessage->sender = config('laravel-sms.default_sender');
                $sentMessage->status = 'failed';
                $sentMessage->provider = config('laravel-sms.default_provider', 'http');
                $sentMessage->provider_response = [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ];
                $sentMessage->sent_at = now();
                $sentMessage->save();
                
                $sentMessages->push($sentMessage);
            }
            
            throw $e;
        }
    }
}
