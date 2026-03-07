<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatActionDraft extends Model
{
    const STATUS_NEEDS_CONFIRM = 'needs_confirm';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_EXECUTED = 'executed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'thread_id',
        'message_id',
        'action_type',
        'payload_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}

