<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pdf extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
   protected $fillable = [
    'user_id',
    'title',
    'original_name',
    'file_path',
    'pages',
    'text',
    'meta',  // <-- ADD THIS
];

    protected $casts = [
        'meta' => 'array',
        'pages' => 'integer',
    ];

    /**
     * Get the user that owns the PDF.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all chat sessions for this PDF
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * Get predefined questions for this PDF
     */
    public function predefinedQuestions(): HasMany
    {
        return $this->hasMany(PredefinedQuestion::class);
    }

    /**
     * Get the full file URL for public access
     */
    public function getFileUrlAttribute(): string
    {
        return asset('storage/pdfs/' . basename($this->file_path));
    }

    /**
     * Check if this PDF has extracted text
     */
    public function hasExtractedText(): bool
    {
        return !empty($this->text);
    }

    /**
     * Get page text from meta if available
     */
    public function getPageText(int $pageNumber): ?string
    {
        $pageTexts = $this->meta['page_texts'] ?? [];
        return $pageTexts[$pageNumber] ?? null;
    }

    /**
     * Set page text in meta
     */
    public function setPageText(int $pageNumber, string $text): void
    {
        $meta = $this->meta ?? [];
        $meta['page_texts'][$pageNumber] = $text;
        $this->meta = $meta;
    }

    /**
     * Check if this PDF was processed with OCR
     */
    public function isOcrProcessed(): bool
    {
        return ($this->meta['ocr_processed'] ?? false) === true;
    }

    /**
     * Mark as OCR processed
     */
    public function markAsOcrProcessed(): void
    {
        $meta = $this->meta ?? [];
        $meta['ocr_processed'] = true;
        $this->meta = $meta;
    }
    /**
 * Get Python PDF ID from meta
 */
public function getPythonPdfId(): ?string
{
    return $this->meta['python_pdf_id'] ?? null;
}

/**
 * Set Python PDF ID in meta
 */
public function setPythonPdfId(string $pythonPdfId): void
{
    $meta = $this->meta ?? [];
    $meta['python_pdf_id'] = $pythonPdfId;
    $this->update(['meta' => $meta]);
}
}

