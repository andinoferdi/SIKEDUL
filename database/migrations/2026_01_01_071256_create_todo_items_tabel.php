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
        Schema::create('todo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_list_id')->constrained('todo_lists')->onDelete('cascade');
            $table->string('title', 200);
            $table->tinyInteger('is_done')->default(0);
            $table->date('due_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('order_index')->unsigned()->default(0);
            $table->dateTime('completed_at_utc')->nullable();
            $table->timestamps();

            $table->index(['todo_list_id', 'is_done']);
            $table->index(['todo_list_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('todo_items');
    }
};
