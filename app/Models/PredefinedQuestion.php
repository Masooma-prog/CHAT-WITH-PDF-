<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredefinedQuestion extends Model
{
    
    use HasFactory;

    protected $fillable = [
        'pdf_id',
        'title',
        'question',
        'source'
    ];

    /**
     * Get the PDF that owns this question
     */
    public function pdf()
    {
        return $this->belongsTo(Pdf::class);
    }

    /**
     * Helper method to save generated questions
     */
    public static function saveGeneratedQuestions(int $pdfId, array $questions): void
    {
        foreach ($questions as $question) {
            self::create([
                'pdf_id' => $pdfId,
                'title' => $question['title'] ?? 'Question',
                'question' => $question['question'],
                'source' => $question['source'] ?? 'laravel_ai'
            ]);
        }
    }
}