<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ChatController;
use App\Models\Pdf;

// --- Authentication Routes ---
Auth::routes(['reset' => false, 'verify' => false]);

// Test endpoint (outside auth)
Route::get('/test-json', function() {
    return response()->json([
        'success' => true,
        'message' => 'JSON works!',
        'timestamp' => now()
    ]);
});

// --- Protected Routes ---
Route::middleware(['auth'])->group(function () {
    // PDF viewer and history page
    Route::get('/', [PdfController::class, 'index'])->name('pdf.index');
    Route::get('/pdfs/{pdf}', [PdfController::class, 'show'])->name('pdf.show');

    // PDF functionality routes
    Route::post('/pdfs/upload', [PdfController::class, 'upload'])->name('pdf.upload');
    Route::get('/pdfs/{pdf}/questions', [PdfController::class, 'getPredefinedQuestions'])->name('pdf.questions');
    
    // Regenerate questions endpoint
    Route::post('/pdfs/{pdf}/regenerate-questions', [PdfController::class, 'regenerateQuestions'])->name('pdf.regenerate-questions');

    // Chat functionality
    Route::post('/chat/{pdf}', [ChatController::class, 'ask'])->name('chat.ask');
    Route::get('/chat/{pdf}/history', [ChatController::class, 'history'])->name('chat.history');
    Route::get('/chat/sessions', [ChatController::class, 'sessions'])->name('chat.sessions');
    
    // Test endpoint
    Route::get('/test-chat', function() {
        return response()->json([
            'success' => true,
            'answer' => 'Test response - JSON is working!',
            'timestamp' => now()->toISOString()
        ]);
    });
    
    // Home route redirects to the main PDF page
    Route::get('/home', function() {
        return redirect()->route('pdf.index');
    })->name('home');

    // ========== DEBUG/STATUS ROUTES ==========
    
    // Check PDF processing status
    Route::get('/pdfs/{pdf}/status', [PdfController::class, 'getProcessingStatus'])->name('pdf.status');
    
    // Check if questions exist for a PDF
    Route::get('/pdfs/{pdf}/questions-status', [PdfController::class, 'getQuestionsStatus'])->name('pdf.questions-status');
    
    // Force retry sending to Python backend
    Route::post('/pdfs/{pdf}/retry-python', [PdfController::class, 'retryPythonProcessing'])->name('pdf.retry-python');
    
    // Auto-fetch questions after upload
    Route::get('/pdfs/{pdf}/auto-fetch-questions', [PdfController::class, 'autoFetchQuestions'])->name('pdf.auto-fetch-questions');
    
    // Force manual upload test
    Route::get('/force-upload/{pdf}', function (Pdf $pdf) {
        if ($pdf->user_id !== Auth::id()) {
            abort(403);
        }
        
        $controller = app(App\Http\Controllers\PdfController::class);
        
        try {
            // Call the protected method using reflection
            $reflection = new ReflectionClass($controller);
            $method = $reflection->getMethod('uploadToPythonAndGetId');
            $method->setAccessible(true);
            
            $pythonPdfId = $method->invoke($controller, $pdf);
            
            if ($pythonPdfId) {
                $meta = $pdf->meta ?? [];
                $meta['python_pdf_id'] = $pythonPdfId;
                $meta['python_upload_time'] = now()->toISOString();
                $meta['questions_status'] = 'processing';
                $pdf->update(['meta' => $meta]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Manually uploaded to Python',
                    'python_pdf_id' => $pythonPdfId
                ], 200, [], JSON_PRETTY_PRINT);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Upload succeeded but no PDF ID returned'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500, [], JSON_PRETTY_PRINT);
        }
    })->name('force.upload');
    
    // Check raw database value
    Route::get('/check-pdf-meta/{pdf}', function (Pdf $pdf) {
        if ($pdf->user_id !== Auth::id()) {
            abort(403);
        }
        
        return response()->json([
            'pdf_id' => $pdf->id,
            'meta_raw' => $pdf->meta,
            'meta_json' => json_encode($pdf->meta),
            'python_pdf_id' => $pdf->meta['python_pdf_id'] ?? null,
            'from_db' => \DB::table('pdfs')->where('id', $pdf->id)->first()
        ], 200, [], JSON_PRETTY_PRINT);
    })->name('check.meta');
    
    // ========== NEW DIAGNOSTIC ROUTES ==========
    
    // Test file paths and existence
    Route::get('/test-upload/{pdf}', function (Pdf $pdf) {
        $storagePath = storage_path('app/public/' . $pdf->file_path);
        $publicPath = public_path('storage/' . $pdf->file_path);
        
        $info = [
            'pdf_id' => $pdf->id,
            'file_path_in_db' => $pdf->file_path,
            'storage_path_full' => $storagePath,
            'storage_path_exists' => file_exists($storagePath),
            'public_path_full' => $publicPath,
            'public_path_exists' => file_exists($publicPath),
            'python_url' => env('PYTHON_SERVICE_URL'),
            'storage_app_public_dir_exists' => is_dir(storage_path('app/public')),
            'storage_app_public_pdfs_dir_exists' => is_dir(storage_path('app/public/pdfs')),
            'list_files' => file_exists(storage_path('app/public/pdfs')) 
                ? scandir(storage_path('app/public/pdfs')) 
                : 'Directory not found',
        ];
        
        return response()->json($info, 200, [], JSON_PRETTY_PRINT);
    })->name('test.upload');
    
    // Test actual Python upload
    Route::get('/test-python-upload/{pdf}', function (Pdf $pdf) {
        $filePath = storage_path('app/public/' . $pdf->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'error' => 'File not found',
                'path' => $filePath,
                'file_path_in_db' => $pdf->file_path,
                'tried_paths' => [
                    'storage_path' => $filePath,
                    'public_path' => public_path('storage/' . $pdf->file_path),
                ]
            ], 404, [], JSON_PRETTY_PRINT);
        }
        
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 60,
                'verify' => false,
                'headers' => [
                    'ngrok-skip-browser-warning' => 'true',
                    'User-Agent' => 'LaravelApp/1.0'
                ]
            ]);
            
            $pythonUrl = env('PYTHON_SERVICE_URL');
            
            \Log::info("Testing upload to: {$pythonUrl}/api/upload");
            \Log::info("File path: {$filePath}");
            \Log::info("File size: " . filesize($filePath) . " bytes");
            
            $response = $client->post($pythonUrl . '/api/upload', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => $pdf->original_name
                    ]
                ]
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            return response()->json([
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'python_response' => $responseData,
                'file_info' => [
                    'path' => $filePath,
                    'exists' => true,
                    'size' => filesize($filePath),
                    'readable' => is_readable($filePath)
                ]
            ], 200, [], JSON_PRETTY_PRINT);
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return response()->json([
                'error' => 'HTTP Request failed',
                'message' => $e->getMessage(),
                'file_exists' => file_exists($filePath),
                'file_path' => $filePath,
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ], 500, [], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception',
                'message' => $e->getMessage(),
                'file_exists' => file_exists($filePath),
                'file_path' => $filePath,
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode to see trace'
            ], 500, [], JSON_PRETTY_PRINT);
        }
    })->name('test.python-upload');
});

// Debug routes (can be accessed without auth for testing)
Route::prefix('debug')->group(function () {
    Route::get('/python-connection', [PdfController::class, 'testPythonConnection'])->name('debug.python');
    Route::get('/python-pdfs', [PdfController::class, 'listPythonPdfs'])->name('debug.python-pdfs');
});