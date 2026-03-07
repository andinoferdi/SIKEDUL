<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    public $timestamps = false;

    const SENDER_USER = 'user';
    const SENDER_ASSISTANT = 'assistant';

    protected $fillable = [
        'thread_id',
        'sender',
        'content',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ChatActionDraft::class, 'message_id');
    }
}

