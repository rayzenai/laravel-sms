<?php

namespace Rayzenai\LaravelSms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Rayzenai\LaravelSms\Services\SmsService;

class SendSmsController extends Controller
{
    /**
     * Handle the incoming request to send a single SMS.
     *
     * @param Request $request
     * @param SmsService $smsService
     * @return JsonResponse
     * @throws ValidationException
     */
    public function __invoke(Request $request, SmsService $smsService): JsonResponse
    {
        // Validate the request
        $validated = $request->validate([
            'recipient' => 'required|string',
            'message' => 'required|string|max:1600',
        ]);

        try {
            // Send the SMS
            $sentMessage = $smsService->send(
                $validated['recipient'],
                $validated['message']
            );

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'id' => $sentMessage->id,
                    'recipient' => $sentMessage->recipient,
                    'status' => $sentMessage->status,
                    'provider_message_id' => $sentMessage->provider_message_id,
                    'sent_at' => $sentMessage->sent_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            // Return error response
            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
