<?php

use Rayzenai\LaravelSms\Services\SmsService;

// Example 1: Send a single SMS
$smsService = app(SmsService::class);

try {
    $sentMessage = $smsService->send('+1234567890', 'Hello from Laravel SMS!');
    
    echo "SMS sent successfully!\n";
    echo "Message ID: " . $sentMessage->provider_message_id . "\n";
    echo "Status: " . $sentMessage->status . "\n";
} catch (\Exception $e) {
    echo "Failed to send SMS: " . $e->getMessage() . "\n";
}

// Example 2: Send bulk SMS
$recipients = [
    '+1234567890',
    '+0987654321',
    '+1111111111'
];

try {
    $sentMessages = $smsService->sendBulk($recipients, 'Bulk message to all recipients!');
    
    echo "\nBulk SMS Results:\n";
    foreach ($sentMessages as $message) {
        echo "Recipient: " . $message->recipient . " - Status: " . $message->status . "\n";
    }
} catch (\Exception $e) {
    echo "Failed to send bulk SMS: " . $e->getMessage() . "\n";
}

// Example 3: Using dependency injection in a controller
class SmsController extends Controller
{
    private SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    public function sendWelcomeSms(Request $request)
    {
        $phoneNumber = $request->input('phone');
        $name = $request->input('name');
        
        try {
            $message = "Welcome {$name}! Thank you for signing up.";
            $sentMessage = $this->smsService->send($phoneNumber, $message);
            
            return response()->json([
                'success' => true,
                'message_id' => $sentMessage->provider_message_id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send SMS'
            ], 500);
        }
    }
}

// Example 4: Using the facade (if you create one)
// SMS::send('+1234567890', 'Hello from the facade!');
