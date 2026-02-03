<?php

namespace App\Http\Controllers;

use App\Models\Pdf;
use App\Models\PredefinedQuestion;
use App\Services\PdfExtractorService;
use App\Services\RAGService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PdfController extends Controller
{
    protected $pdfExtractor;
    protected $ragService;
    protected $pythonClient;
    protected $pythonServiceUrl;
    protected $usePythonBackend;

    public function __construct(PdfExtractorService $pdfExtractor, RAGService $ragService)
    {
        $this->pdfExtractor = $pdfExtractor;
        $this->ragService = $ragService;
        $this->pythonServiceUrl = env('PYTHON_SERVICE_URL', 'https://sketchily-superabnormal-beryl.ngrok-free.dev');
        $this->usePythonBackend = env('USE_PYTHON_BACKEND', true);
        $this->pythonClient = new Client([
            'timeout' => 300, // 5 minutes for upload
            'verify' => false,
            'headers' => [
                'ngrok-skip-browser-warning' => 'true',
                'User-Agent' => 'LaravelApp/1.0'
            ]
        ]);
    }

    public function index()
    {
        $pdfs = Auth::user()->pdfs()->latest()->take(20)->get();
        return view('pdf.show', compact('pdfs'));
    }

    public function show(Pdf $pdf)
    {
        if ($pdf->user_id !== Auth::id()) {
            abort(403, 'Unauthorized Access');
        }
        $pdfs = Auth::user()->pdfs()->latest()->take(20)->get();
        return view('pdf.show', compact('pdf', 'pdfs'));
    }

    public function upload(Request $request): JsonResponse
    {
        try {
            // Phase 2: Validate PDF file
            $maxSize = env('MAX_PDF_SIZE', 10485760); // 10MB default
            $maxSizeInKB = $maxSize / 1024;
            
            $request->validate([
                'pdf' => [
                    'required',
                    'file',
                    'mimes:pdf',
                    'max:' . $maxSizeInKB
                ],
            ]);

            $uploadedFile = $request->file('pdf');
            
            // Phase 2: Generate filename and store PDF
            $originalName = $uploadedFile->getClientOriginalName();
            $title = Str::replaceFirst('.pdf', '', $originalName);
            $fileSize = $uploadedFile->getSize();
            
            // Store in storage/app/public/pdfs
            $filePath = $uploadedFile->store('pdfs', 'public');

            // Phase 2: Save metadata to database
            $pdf = Pdf::create([
                'user_id' => Auth::id(),
                'title' => $title,
                'original_name' => $originalName,
                'file_path' => $filePath,
                'size' => $fileSize,
                'pages' => 0, // Will be updated in Phase 3
                'status' => 'uploaded', // Phase 2 status
                'meta' => [
                    'uploaded_at' => now()->toISOString(),
                    'phase' => 2
                ]
            ]);

            Log::info("✅ Phase 2: PDF {$pdf->id} uploaded successfully");
            Log::info("   - File: {$originalName}");
            Log::info("   - Size: " . number_format($fileSize / 1024, 2) . " KB");
            Log::info("   - Path: {$filePath}");

            // Phase 3: Extract text from PDF
            try {
                Log::info("🔍 Phase 3: Starting text extraction for PDF {$pdf->id}");
                $this->pdfExtractor->extractText($pdf);
                $pdf->refresh(); // Reload from database
                
                if (!empty($pdf->text)) {
                    $pdf->update(['status' => 'text_extracted']);
                    Log::info("✅ Phase 3: Text extracted successfully");
                    Log::info("   - Pages: {$pdf->pages}");
                    Log::info("   - Text length: " . strlen($pdf->text) . " characters");
                } else {
                    Log::warning("⚠️ Phase 3: No text extracted (might be scanned PDF)");
                }
            } catch (\Exception $e) {
                Log::error("❌ Phase 3: Text extraction failed: " . $e->getMessage());
                // Don't fail the upload, just log the error
            }

            // Phase 2: Return success response
            return response()->json([
                'success' => true,
                'message' => 'PDF uploaded successfully',
                'pdf_id' => $pdf->id,
                'redirect_url' => route('pdf.show', $pdf),
                'pdf' => [
                    'id' => $pdf->id,
                    'title' => $pdf->title,
                    'size' => $fileSize,
                    'status' => $pdf->status
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('PDF validation failed: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('PDF upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload to Python and return the Python PDF ID
     */
    protected function uploadToPythonAndGetId(Pdf $pdf): ?string
    {
        try {
            $filePath = storage_path('app/public/' . $pdf->file_path);
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            Log::info("📤 Uploading to: {$this->pythonServiceUrl}/api/upload");

            $response = $this->pythonClient->post($this->pythonServiceUrl . '/api/upload', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => $pdf->original_name
                    ]
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            Log::info("✅ Python response: " . json_encode($responseData));

            return $responseData['pdf_id'] ?? null;

        } catch (GuzzleException $e) {
            Log::error("❌ Guzzle error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Auto-fetch questions after upload (called from frontend)
     * NO TIMEOUT - frontend will handle polling as long as needed
     */
    public function autoFetchQuestions(Pdf $pdf): JsonResponse
    {
        try {
            if ($pdf->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Refresh from database
            $pdf->refresh();
            $pythonPdfId = $pdf->meta['python_pdf_id'] ?? null;
            
            if (!$pythonPdfId) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Python PDF ID not found',
                    'questions' => []
                ]);
            }

            try {
                // Use shorter timeout for polling requests
                $pollClient = new Client([
                    'timeout' => 15,
                    'verify' => false,
                    'headers' => [
                        'ngrok-skip-browser-warning' => 'true',
                        'User-Agent' => 'LaravelApp/1.0'
                    ]
                ]);

                $response = $pollClient->get(
                    $this->pythonServiceUrl . '/api/pdfs/' . $pythonPdfId . '/questions'
                );

                $responseData = json_decode($response->getBody()->getContents(), true);
                $status = $responseData['status'] ?? 'unknown';
                
                Log::info("Question poll status for PDF {$pdf->id}: {$status}");
                
                if ($status === 'ready' && isset($responseData['questions']) && count($responseData['questions']) > 0) {
                    // Delete old questions and save new ones
                    PredefinedQuestion::where('pdf_id', $pdf->id)
                        ->where('source', 'python_ai')
                        ->delete();
                    
                    $savedCount = 0;
                    foreach ($responseData['questions'] as $question) {
                        if (!empty($question['question'] ?? null)) {
                            PredefinedQuestion::create([
                                'pdf_id' => $pdf->id,
                                'title' => $question['title'] ?? 'Question',
                                'question' => $question['question'],
                                'source' => 'python_ai'
                            ]);
                            $savedCount++;
                        }
                    }
                    
                    // Update meta
                    $meta = $pdf->meta ?? [];
                    $meta['questions_generated'] = true;
                    $meta['questions_count'] = $savedCount;
                    $meta['questions_status'] = 'completed';
                    $meta['questions_generated_at'] = now()->toISOString();
                    $pdf->update(['meta' => $meta]);
                    
                    $questions = PredefinedQuestion::where('pdf_id', $pdf->id)->get();
                    
                    return response()->json([
                        'success' => true,
                        'status' => 'ready',
                        'questions' => $questions,
                        'count' => $savedCount
                    ]);
                }
                
                // Still processing
                return response()->json([
                    'success' => true,
                    'status' => $status,
                    'questions' => [],
                    'message' => 'Questions are being generated...'
                ]);
                
            } catch (\Exception $e) {
                Log::error("Auto-fetch failed: " . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'questions' => [],
                    'message' => 'Temporary error, will retry'
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Auto-fetch error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'questions' => []
            ], 500);
        }
    }

    public function getPredefinedQuestions(Pdf $pdf): JsonResponse
    {
        try {
            if ($pdf->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $questions = PredefinedQuestion::where('pdf_id', $pdf->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $meta = $pdf->meta ?? [];
            $questionsStatus = $meta['questions_status'] ?? 'unknown';
            
            return response()->json([
                'success' => true,
                'questions' => $questions,
                'status' => $questionsStatus,
                'count' => $questions->count()
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching questions: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch questions',
                'questions' => []
            ], 500);
        }
    }

    public function getProcessingStatus(Pdf $pdf): JsonResponse
    {
        if ($pdf->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $meta = $pdf->meta ?? [];
        
        return response()->json([
            'success' => true,
            'pdf_id' => $pdf->id,
            'title' => $pdf->title,
            'processing_status' => $meta['processing_status'] ?? 'unknown',
            'questions_status' => $meta['questions_status'] ?? 'unknown',
            'python_pdf_id' => $meta['python_pdf_id'] ?? null,
            'python_upload_time' => $meta['python_upload_time'] ?? null,
            'questions_generated' => $meta['questions_generated'] ?? false,
            'questions_count' => $meta['questions_count'] ?? 0,
        ]);
    }
}