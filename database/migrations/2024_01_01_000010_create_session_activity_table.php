<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which name/identity is behind each live browser session, so an
     * admin can see (and end) every operator and client session by name
     * without having to decode Laravel's raw session payloads.
     */
    public function up(): void
    {
        Schema::create('session_activity', function (Blueprint $table) {
            $table->string('session_id')->primary();
            $table->string('kind', 10); // 'operator' | 'client'
            $table->unsignedBigInteger('subject_id'); // users.id or conversations.id
            $table->string('name');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_seen_at');

            $table->index(['kind', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_activity');
    }
};
