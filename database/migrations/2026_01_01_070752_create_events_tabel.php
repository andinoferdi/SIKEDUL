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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('event_categories')->onDelete('set null');
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->dateTime('start_at_utc');
            $table->dateTime('end_at_utc');
            $table->enum('status', ['planned', 'done', 'canceled'])->default('planned');
            $table->timestamps();

            $table->index(['user_id', 'start_at_utc']);
            $table->index(['user_id', 'end_at_utc']);
            $table->index(['user_id', 'start_at_utc', 'end_at_utc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
