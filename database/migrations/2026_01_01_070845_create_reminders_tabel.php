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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->dateTime('send_at_utc');
            $table->enum('channel', ['email'])->default('email');
            $table->enum('status', ['pending', 'sent', 'failed', 'canceled'])->default('pending');
            $table->tinyInteger('attempt_count')->unsigned()->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('sent_at_utc')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at_utc']);
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
