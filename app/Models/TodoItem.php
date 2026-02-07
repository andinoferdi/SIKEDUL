<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoItem extends Model
{
    protected $fillable = [
        'todo_list_id',
        'title',
        'is_done',
        'due_date',
        'start_date',
        'end_date',
        'order_index',
        'completed_at_utc',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'due_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at_utc' => 'datetime',
        ];
    }

    public function todoList(): BelongsTo
    {
        return $this->belongsTo(TodoList::class);
    }
}
