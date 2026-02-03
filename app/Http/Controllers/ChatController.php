<?php

namespace App\Http\Controllers;

use App\Models\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ChatController extends Controller
{
    protected $pythonClient;
    protected $pythonServiceUrl;
    protected $usePythonBackend;

    public function __construct()
    {
        $this->pythonServiceUrl = env('PYTHON_SERVICE_URL', 'https://sketchily-superabnormal-beryl.ngrok-free.dev');
        $this->usePythonBackend = env('USE_PYTHON_BACKEND', true);
        $this->pythonClient = new Client([
            'timeout' => 60,
            'verify' => false,
            'headers' => [
                'ngrok-skip-browser-warning' => 'true',
                'User-Agent' => 'LaravelApp/1.0'
            ]
        ]);
    }

    /**
     * Handle chat request - uses Python backend if available
     */
    public function ask(Pdf $pdf, Request $request): JsonResponse
    {
        try {
            // Verify ownership
            if ($pdf->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'answer' => 'Unauthorized access to this PDF.'
                ], 403);
            }

            $request->validate([
                'message' => 'required|string|max:1000'
            ]);

            $message = $request->input('message');

            // Try Python backend first if enabled
            if ($this->usePythonBackend) {
                try {
                    $answer = $this->askPythonBackend($pdf, $message);
                    if ($answer) {
                        return response()->json([
                            'success' => true,
                            'answer' => $answer,
                            'source' => 'python_ai'
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning("Python backend failed, falling back to Laravel: " . $e->getMessage());
                }
            }

            // Fallback to Laravel's existing RAG service
            return $this->askLaravelBackend($pdf, $message);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'answer' => 'Invalid message format.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Chat error for PDF {$pdf->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'answer' => 'An error occurred while processing your question.'
            ], 500);
        }
    }

    /**
     * Query Python backend for answer
     */
    protected function askPythonBackend(Pdf $pdf, string $message): ?string
    {
        $meta = $pdf->meta ?? [];
        
        if (!isset($meta['python_pdf_id'])) {
            Log::info("No Python PDF ID found for PDF {$pdf->id}, skipping Python backend");
            return null;
        }

        $pythonPdfId = $meta['python_pdf_id'];

        try {
            $response = $this->pythonClient->post(
                $this->pythonServiceUrl . '/api/chat/' . $pythonPdfId,
                [
                    'json' => [
                        'message' => $message,
                        'pdf_id' => $pythonPdfId
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ]
                ]
            );

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['answer']) && !empty($responseData['answer'])) {
                Log::info("Python backend answered for PDF {$pdf->id}");
                return $responseData['answer'];
            }

            return null;

        } catch (GuzzleException $e) {
            Log::error("Python backend chat error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fallback to Laravel's existing RAG service
     */
    protected function askLaravelBackend(Pdf $pdf, string $message): JsonResponse
    {
        try {
            // Check if RAG service is available
            if (!$pdf->hasExtractedText()) {
                return response()->json([
                    'success' => false,
                    'answer' => 'This PDF is still being processed. Please try again in a moment.',
                    'source' => 'laravel_fallback'
                ]);
            }

            // If you have a RAG service, use it here
            // Example:
            // $ragService = app(\App\Services\RAGService::class);
            // $answer = $ragService->answer($pdf, $message);
            
            // For now, return a basic response
            $answer = "I apologize, but the Laravel AI service is not fully configured yet. The PDF text has been extracted, but I need the RAG service to answer questions about it.";
            
            return response()->json([
                'success' => true,
                'answer' => $answer,
                'source' => 'laravel_fallback'
            ]);

        } catch (\Exception $e) {
            Log::error("Laravel backend chat error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'answer' => 'An error occurred while processing your question.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get chat history for a PDF (if you want to implement this)
     */
    public function history(Pdf $pdf): JsonResponse
    {
        try {
            if ($pdf->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Implement chat history retrieval if needed
            // For now, return empty history
            return response()->json([
                'success' => true,
                'history' => []
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching chat history: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'history' => []
            ], 500);
        }
    }
}