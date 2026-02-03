<?php

namespace App\Services;

use App\Models\Pdf;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class RAGService
{
    protected $pythonServiceUrl;
    protected $openaiApiKey;
    protected $openaiClient;
    protected $ragModel = 'gpt-3.5-turbo';

    public function __construct()
    {
        $this->pythonServiceUrl = config('app.python_service_url', env('PYTHON_SERVICE_URL', 'http://localhost:8001'));
        $this->openaiApiKey = config('openai.api_key', env('OPENAI_API_KEY'));

        if (empty($this->openaiApiKey)) {
            Log::error("⚠️ Missing OpenAI API Key. Please add OPENAI_API_KEY in your .env file.");
        }

        $this->openaiClient = app(\OpenAI\Client::class);
    }

    public function generateResponse(?int $pdfId, string $query, ChatSession $session): array
    {
        Log::info("Generating RAG response for query: {$query}");

        try {
            if ($this->isPythonServiceAvailable()) {
                return $this->generateWithPythonRAG($pdfId, $query, $session);
            } else {
                Log::warning("Python service unavailable, using direct OpenAI fallback");
                return $this->generateWithDirectOpenAI($pdfId, $query, $session);
            }
        } catch (\Exception $e) {
            Log::error("RAG generation failed: " . $e->getMessage());
            return $this->handleRagError($e);
        }
    }

    protected function generateWithPythonRAG(?int $pdfId, string $query, ChatSession $session): array
    {
        $pdf = Pdf::find($pdfId);
        $sessionHistory = $session->messages()->latest()->take(10)->get()
            ->sortBy('created_at')
            ->map(fn($m) => [
                'sender' => $m->sender,
                'message' => $m->message
            ])
            ->values() // Ensure it's a simple array
            ->toArray();

        $payload = [
            'pdf_id' => $pdfId,
            'query' => $query,
            'chat_history' => $sessionHistory,
            'pdf_meta' => [
                'title' => $pdf->title ?? 'Unknown',
                'pages' => $pdf->pages ?? 'N/A',
            ],
        ];

        Log::info('Calling Python RAG Service at /ask', ['payload' => $payload]);

        // --- UPDATED: Changed endpoint from /chat to /ask ---
        $response = Http::timeout(60)->post("{$this->pythonServiceUrl}/ask", $payload);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'response' => $data['response'] ?? 'No response generated.',
                'sources' => $data['sources'] ?? [],
            ];
        }

        throw new \Exception("Python RAG service error: " . $response->status() . " - " . $response->body());
    }
    
    // All other methods (generateWithDirectOpenAI, handleRagError, createEmbeddings, etc.) remain the same...

    public function generateQuestionsFromText(string $text): array
    {
        if (!$this->isPythonServiceAvailable()) {
            Log::error("Python service is unavailable. Cannot generate questions.");
            return [];
        }
        try {
            $response = Http::timeout(90)->post("{$this->pythonServiceUrl}/generate-questions", ['text' => $text]);
            if ($response->successful()) {
                return $response->json('questions', []);
            }
            Log::error("Failed to generate questions from Python service: " . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error("Exception while generating questions: " . $e->getMessage());
            return [];
        }
    }
    
    public function createEmbeddings(Pdf $pdf): array
    {
        if (!$pdf->hasExtractedText()) {
            throw new \Exception("Cannot create embeddings for PDF {$pdf->id}. No extracted text found.");
        }
        if (!$this->isPythonServiceAvailable()) {
            throw new \Exception("The Python embedding service is not available.");
        }
        $chunks = $this->splitText($pdf->text);
        $payload = [
            'pdf_id' => $pdf->id,
            'chunks' => $chunks,
            'pdf_meta' => [
                'title' => $pdf->title,
                'original_name' => $pdf->original_name,
                'pages' => $pdf->pages,
            ],
        ];
        $response = Http::timeout(120)->post("{$this->pythonServiceUrl}/embed", $payload);
        if ($response->successful()) {
            $data = $response->json();
            $meta = $pdf->meta ?? [];
            $meta['embeddings_created'] = true;
            $meta['embeddings_created_at'] = now()->toISOString();
            $meta['embedding_chunks'] = $data['chunk_count'] ?? 0;
            $pdf->update(['meta' => $meta]);
            return ['success' => true, 'chunk_count' => $data['chunk_count'] ?? 0];
        }
        throw new \Exception("Python embedding service error: " . $response->status() . " - " . $response->body());
    }

    private function splitText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $chunks = [];
        $length = strlen($text);
        for ($start = 0; $start < $length; $start += $chunkSize - $overlap) {
            $end = min($start + $chunkSize, $length);
            $chunks[] = substr($text, $start, $end - $start);
        }
        return $chunks;
    }

    protected function generateWithDirectOpenAI(?int $pdfId, string $query, ChatSession $session): array
    {
        // This is a fallback and can remain as is.
        return ['success' => false, 'message' => 'Direct OpenAI fallback not fully implemented.'];
    }

    protected function handleRagError(\Exception $e): array
    {
        return ['success' => false, 'message' => $e->getMessage()];
    }
    
    public function isPythonServiceAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->pythonServiceUrl}/status");
            return $response->successful() && $response->json('status') === 'ok';
        } catch (\Exception $e) {
            Log::error("Python RAG service health check failed: " . $e->getMessage());
            return false;
        }
    }
}

