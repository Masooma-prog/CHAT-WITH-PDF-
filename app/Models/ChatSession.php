<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = [
        'user_id',
        'pdf_id',
        'title',
        'last_message_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pdf(): BelongsTo
    {
        return $this->belongsTo(Pdf::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    /**
     * Get the latest messages
     */
    public function latestMessages(int $limit = 10)
    {
        return $this->messages()->latest()->limit($limit)->get()->reverse()->values();
    }

    /**
     * Auto-generate title from first question
     */
    public function generateTitle(): void
    {
        if ($this->title) {
            return; // Already has a title
        }

        $firstMessage = $this->messages()->where('role', 'user')->first();
        if ($firstMessage) {
            // Use first 50 chars of first question as title
            $this->title = substr($firstMessage->message, 0, 50) . (strlen($firstMessage->message) > 50 ? '...' : '');
            $this->save();
        }
    }
}
