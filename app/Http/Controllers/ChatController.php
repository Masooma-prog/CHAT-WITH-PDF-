<?php

namespace App\Http\Controllers;

use App\Models\Pdf;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class ChatController extends Controller
{
    /**
     * Phase 9: Chat with history tracking
     */
    public function ask(Pdf $pdf, Request $request): JsonResponse
    {
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

            // Get or create chat session
            $session = ChatSession::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'pdf_id' => $pdf->id
                ],
                [
                    'last_message_at' => now()
                ]
            );

            // Save user message
            $userMessage = ChatMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'message' => $message
            ]);

            // Get Python PDF ID
            $pythonPdfId = $pdf->meta['python_pdf_id'] ?? null;
            if (!$pythonPdfId) {
                $errorAnswer = 'PDF not processed yet. Please wait and try again.';
                
                // Save error as assistant message
                ChatMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'message' => $errorAnswer
                ]);
                
                return $this->jsonResponse(false, $errorAnswer);
            }

            // Call Python RAG service
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
                $answer = $data['answer'];
                
                // Save assistant response
                ChatMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'message' => $answer,
                    'metadata' => [
                        'model' => $data['model'] ?? 'local_rag',
                        'tokens_used' => $data['tokens_used'] ?? 0,
                        'sources_count' => count($data['sources'] ?? [])
                    ]
                ]);

                // Update session
                $session->update(['last_message_at' => now()]);
                $session->generateTitle();

                return $this->jsonResponse(true, $answer);
            }

            $errorAnswer = 'No response from AI';
            ChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'message' => $errorAnswer
            ]);

            return $this->jsonResponse(false, $errorAnswer);

        } catch (\Exception $e) {
            Log::error("Chat error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Get chat history for a PDF
     */
    public function history(Pdf $pdf): JsonResponse
    {
        try {
            if ($pdf->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $session = ChatSession::where('user_id', Auth::id())
                ->where('pdf_id', $pdf->id)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => true,
                    'messages' => [],
                    'session_id' => null
                ]);
            }

            $messages = $session->messages()->orderBy('created_at', 'asc')->get();

            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'messages' => $messages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'role' => $msg->role,
                        'message' => $msg->message,
                        'created_at' => $msg->created_at->toISOString()
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching history: " . $e->getMessage());
            return response()->json(['success' => false, 'messages' => []], 500);
        }
    }

    /**
     * Clear chat history for a PDF
     */
    public function clearHistory(Pdf $pdf): JsonResponse
    {
        try {
            if ($pdf->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $session = ChatSession::where('user_id', Auth::id())
                ->where('pdf_id', $pdf->id)
                ->first();

            if ($session) {
                // Delete all messages in this session
                $session->messages()->delete();
                // Delete the session itself
                $session->delete();
                
                Log::info("Cleared chat history for PDF {$pdf->id}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Chat history cleared'
            ]);

        } catch (\Exception $e) {
            Log::error("Error clearing history: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error clearing history'], 500);
        }
    }

    /**
     * Get all chat sessions for current user
     */
    public function sessions(): JsonResponse
    {
        try {
            $sessions = ChatSession::where('user_id', Auth::id())
                ->with('pdf:id,title')
                ->orderBy('last_message_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'sessions' => $sessions->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'pdf_id' => $session->pdf_id,
                        'pdf_title' => $session->pdf->title ?? 'Unknown',
                        'title' => $session->title ?? 'New conversation',
                        'last_message_at' => $session->last_message_at?->toISOString(),
                        'messages_count' => $session->messages()->count()
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching sessions: " . $e->getMessage());
            return response()->json(['success' => false, 'sessions' => []], 500);
        }
    }

    /**
     * Compare multiple PDFs - chat with multiple documents
     * Works exactly like regular chat but with 2 PDFs
     */
    public function compareAsk(Request $request): JsonResponse
    {
        try {
            // Get PDF IDs from request
            $pdfIds = $request->input('pdf_ids', []);
            $message = $request->input('message');
            
            if (empty($message)) {
                return $this->jsonResponse(false, 'Please enter a message');
            }
            
            if (count($pdfIds) !== 2) {
                return $this->jsonResponse(false, 'Please select exactly 2 PDFs to compare');
            }
            
            // Verify all PDFs belong to user
            $pdfs = Pdf::whereIn('id', $pdfIds)
                ->where('user_id', Auth::id())
                ->get();
            
            if ($pdfs->count() !== 2) {
                return $this->jsonResponse(false, 'Some PDFs not found or unauthorized');
            }
            
            // Sort PDF IDs for consistent session
            sort($pdfIds);
            
            // Get or create comparison session (simplified - no meta column needed)
            $session = ChatSession::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'pdf_id' => $pdfs->first()->id,
                    'title' => "Comparing: {$pdfs[0]->title} vs {$pdfs[1]->title}"
                ],
                [
                    'last_message_at' => now()
                ]
            );

            // Save user message
            ChatMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'message' => $message
            ]);
            
            // Get Python PDF IDs
            $pythonPdfIds = [];
            foreach ($pdfs as $pdf) {
                $pythonId = $pdf->meta['python_pdf_id'] ?? null;
                if ($pythonId) {
                    $pythonPdfIds[] = (string)$pythonId;
                }
            }
            
            if (count($pythonPdfIds) !== 2) {
                $errorAnswer = 'PDFs not processed yet. Please wait and try again.';
                
                // Save error as assistant message
                ChatMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'message' => $errorAnswer
                ]);
                
                return $this->jsonResponse(false, $errorAnswer);
            }
            
            // Call Python comparison service
            $client = new Client(['timeout' => 45, 'verify' => false]);
            $response = $client->post('http://localhost:8001/api/compare-chat', [
                'json' => [
                    'question' => $message,
                    'pdf_ids' => $pythonPdfIds,
                    'chat_history' => []
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if ($data['success'] ?? false) {
                $answer = $data['answer'];
                
                // Save assistant response
                ChatMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'message' => $answer,
                    'metadata' => [
                        'model' => $data['model'] ?? 'comparison_rag',
                        'tokens_used' => $data['tokens_used'] ?? 0,
                        'pdfs_compared' => 2,
                        'sources_count' => count($data['sources'] ?? [])
                    ]
                ]);
                
                // Update session
                $session->update(['last_message_at' => now()]);
                
                return $this->jsonResponse(true, $answer);
            }
            
            $errorAnswer = 'No response from AI';
            ChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'message' => $errorAnswer
            ]);
            
            return $this->jsonResponse(false, $errorAnswer);
            
        } catch (\Exception $e) {
            Log::error("Comparison chat error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return $this->jsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    private function jsonResponse(bool $success, string $answer): JsonResponse
    {
        return response()->json(['success' => $success, 'answer' => $answer]);
    }

    // Stub methods for API routes
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
}
