<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_message_id')->unique();
            $table->string('thread_id')->index();
            $table->string('sender');
            $table->string('receiver')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('date')->nullable();
            $table->boolean('has_attachment')->default(false);
            $table->timestamps();

            // Index for efficient thread queries
            $table->index(['user_id', 'thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
