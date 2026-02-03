<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'sender',
        'message',
        'meta',
    ];

    protected $casts = [
        'session_id' => 'integer',
        'meta' => 'array',
    ];

    /**
     * Get the session this message belongs to
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    /**
     * Check if this is a user message
     */
    public function isUser(): bool
    {
        return $this->sender === 'user';
    }

    /**
     * Check if this is a bot message
     */
    public function isBot(): bool
    {
        return $this->sender === 'bot';
    }

    /**
     * Check if this is a system message
     */
    public function isSystem(): bool
    {
        return $this->sender === 'system';
    }

    /**
     * Get source references from meta
     */
    public function getSources(): array
    {
        return $this->meta['sources'] ?? [];
    }

    /**
     * Get confidence score from meta
     */
    public function getConfidence(): ?float
    {
        return $this->meta['confidence'] ?? null;
    }

    /**
     * Get processing time from meta
     */
    public function getProcessingTime(): ?float
    {
        return $this->meta['processing_time'] ?? null;
    }

    /**
     * Set source references in meta
     */
    public function setSources(array $sources): void
    {
        $meta = $this->meta ?? [];
        $meta['sources'] = $sources;
        $this->meta = $meta;
    }

    /**
     * Set confidence score in meta
     */
    public function setConfidence(float $confidence): void
    {
        $meta = $this->meta ?? [];
        $meta['confidence'] = $confidence;
        $this->meta = $meta;
    }

    /**
     * Set processing time in meta
     */
    public function setProcessingTime(float $time): void
    {
        $meta = $this->meta ?? [];
        $meta['processing_time'] = $time;
        $this->meta = $meta;
    }

    /**
     * Get formatted message for display
     */
    public function getFormattedMessageAttribute(): string
    {
        // Convert markdown-like formatting to HTML if needed
        return nl2br(e($this->message));
    }
}