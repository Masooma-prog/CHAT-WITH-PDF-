<?php

namespace App\Http\Controllers;

use App\Models\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class ChatController extends Controller
{
    public function ask(Pdf $pdf, Request $request): JsonResponse
    {
        // ALWAYS return JSON, no matter what
        try {
            // Check ownership
            if ($pdf->user_id !== Auth::id()) {
                return $this->jsonResponse(false, 'Unauthorized');
            }

            // Get message
            $message = $request->input('message');
            if (empty($message)) {
                return $this->jsonResponse(false, 'Please enter a message');
            }

            // Get Python PDF ID
            $pythonPdfId = $pdf->meta['python_pdf_id'] ?? null;
            if (!$pythonPdfId) {
                return $this->jsonResponse(false, 'PDF not processed yet. Please wait and try again.');
            }

            // Call Python
            $client = new Client(['timeout' => 30, 'verify' => false]);
            $response = $client->post('http://localhost:8001/api/chat', [
                'json' => [
                    'question' => $message,
                    'pdf_id' => (string)$pythonPdfId,
                    'chat_history' => []
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            
            if ($data['success'] ?? false) {
                return $this->jsonResponse(true, $data['answer']);
            }

            return $this->jsonResponse(false, 'No response from AI');

        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    private function jsonResponse(bool $success, string $answer): JsonResponse
    {
        return response()->json(['success' => $success, 'answer' => $answer]);
    }

    // Stub methods
    public function createSession(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function getMessages($session): JsonResponse
    {
        return response()->json(['success' => true, 'messages' => []]);
    }

    public function postMessage($session, Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function history(Pdf $pdf): JsonResponse
    {
        return response()->json(['success' => true, 'history' => []]);
    }
}
