<?php

namespace App\Services;

use App\Models\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class PdfExtractorService
{
    protected $parser;
    protected $pythonServiceUrl;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
        $this->pythonServiceUrl = config('app.python_service_url');
    }

    public function extractText(Pdf $pdf): void
    {
        Log::info("Starting text extraction for PDF: {$pdf->id}");
        // --- FINAL FIX 1: Use the Storage facade to get the absolute, correct file path ---
        $fullPath = Storage::disk('public')->path($pdf->file_path);

        if (!file_exists($fullPath)) {
            Log::error("PDF file does not exist at path: " . $fullPath);
            return;
        }

        // --- ATTEMPT 1: Use PHP-based text extraction first ---
        try {
            $pdfDocument = $this->parser->parseFile($fullPath);
            $text = $pdfDocument->getText();
            $pages = count($pdfDocument->getPages());

            if (!empty(trim($text))) {
                $pdf->update(['text' => $text, 'pages' => $pages]);
                Log::info("Successfully extracted text using PHP parser for PDF: {$pdf->id}");
                return; // Exit if successful
            }
        } catch (\Exception $e) {
            Log::error("PHP PDF extraction failed for PDF {$pdf->id}: " . $e->getMessage());
        }

        // --- ATTEMPT 2: Fallback to Python OCR service ---
        Log::info("PHP extraction failed or returned empty, trying Python OCR for PDF: {$pdf->id}");
        try {
            $fileContents = file_get_contents($fullPath);
            if ($fileContents === false) {
                throw new \Exception("Could not read file contents from path: " . $fullPath);
            }

            $response = Http::timeout(120)->attach(
                'file',
                $fileContents,
                $pdf->original_name
            )->post("{$this->pythonServiceUrl}/extract");

            if ($response->successful()) {
                $data = $response->json();
                // --- FINAL FIX 2: The Python service returns 'text', not 'full_text' ---
                $pdf->update([
                    'text' => $data['text'] ?? '',
                    'pages' => $data['page_count'] ?? 0,
                    'meta' => array_merge($pdf->meta ?? [], ['ocr_used' => true])
                ]);
                Log::info("Successfully extracted text using Python OCR for PDF: {$pdf->id}");
            } else {
                throw new \Exception("Python service returned status: " . $response->status() . " Body: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed to call Python OCR service for PDF {$pdf->id}: " . $e->getMessage());
        } finally {
            if (empty($pdf->fresh()->text)) {
                 Log::warning("No text could be extracted from PDF: {$pdf->id}");
            }
        }
    }
}

