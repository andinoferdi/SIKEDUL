<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_action_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->onDelete('cascade');
            $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->enum('action_type', [
                'create_event',
                'update_event',
                'delete_event',
                'create_todo_list',
                'add_todo_items',
                'toggle_todo_item',
                'delete_todo_item',
            ]);
            $table->json('payload_json');
            $table->enum('status', ['needs_confirm', 'confirmed', 'canceled', 'executed', 'failed'])->default('needs_confirm');
            $table->timestamps();

            $table->index(['thread_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_action_drafts');
    }
};
