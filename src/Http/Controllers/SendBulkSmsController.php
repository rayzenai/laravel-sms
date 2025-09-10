<?php

namespace Rayzenai\LaravelSms\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Rayzenai\LaravelSms\Rules\NepaliPhoneNumber;
use Rayzenai\LaravelSms\Services\SmsService;

class SendBulkSmsController extends Controller
{
    /**
     * Handle the incoming request to send bulk SMS.
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
            'recipients' => 'required|array|min:1',
            'recipients.*' => ['required', 'string', new NepaliPhoneNumber()],
            'message' => 'required|string|max:1600',
        ]);

        try {
            // Send bulk SMS
            $sentMessages = $smsService->sendBulk(
                $validated['recipients'],
                $validated['message']
            );

            // Prepare response data
            $results = $sentMessages->map(function ($sentMessage) {
                return [
                    'id' => $sentMessage->id,
                    'recipient' => $sentMessage->recipient,
                    'status' => $sentMessage->status,
                    'provider_message_id' => $sentMessage->provider_message_id,
                    'sent_at' => $sentMessage->sent_at,
                ];
            });

            // Count successful and failed messages
            $successCount = $sentMessages->where('status', 'sent')->count();
            $failedCount = $sentMessages->where('status', 'failed')->count();

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Bulk SMS processed',
                'data' => [
                    'total' => $sentMessages->count(),
                    'successful' => $successCount,
                    'failed' => $failedCount,
                    'results' => $results,
                ]
            ], 200);

        } catch (Exception $e) {
            // Return error response
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk SMS',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
