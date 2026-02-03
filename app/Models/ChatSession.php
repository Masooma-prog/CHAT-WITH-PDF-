<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdf_id',
        'title',
    ];

    protected $casts = [
        'pdf_id' => 'integer',
    ];

    /**
     * Get the PDF associated with this session
     */
    public function pdf(): BelongsTo
    {
        return $this->belongsTo(Pdf::class);
    }

    /**
     * Get all messages in this session
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id')->orderBy('created_at');
    }

    /**
     * Get the latest message in this session
     */
    public function latestMessage(): HasMany
    {
        return $this->messages()->latest();
    }

    /**
     * Add a message to this session
     */
    public function addMessage(string $sender, string $message, array $meta = []): ChatMessage
    {
        return $this->messages()->create([
            'sender' => $sender,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    /**
     * Get message count for this session
     */
    public function getMessageCountAttribute(): int
    {
        return $this->messages()->count();
    }

    /**
     * Get a summary of the session for display
     */
    public function getSummaryAttribute(): string
    {
        $messageCount = $this->message_count;
        $latestMessage = $this->messages()->latest()->first();
        
        if ($messageCount === 0) {
            return 'New conversation';
        }

        return "Messages: {$messageCount} • Last: " . $latestMessage?->created_at?->diffForHumans();
    }
}