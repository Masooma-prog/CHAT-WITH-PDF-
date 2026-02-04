<?php

namespace App\Http\Controllers;

use App\Models\Pdf;
use App\Models\PredefinedQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PredefinedQuestionController extends Controller
{
    /**
     * Get all predefined questions
     */
    public function index(): JsonResponse
    {
        try {
            $questions = PredefinedQuestion::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'questions' => $questions
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching predefined questions: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'questions' => []
            ], 500);
        }
    }

    /**
     * Generate questions for a PDF (stub - not implemented yet)
     */
    public function generateQuestions(Pdf $pdf): JsonResponse
    {
        try {
            // This would call Python service to generate questions
            // For now, return empty
            return response()->json([
                'success' => true,
                'message' => 'Question generation not implemented yet',
                'questions' => []
            ]);
        } catch (\Exception $e) {
            Log::error("Error generating questions: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate questions'
            ], 500);
        }
    }
}
