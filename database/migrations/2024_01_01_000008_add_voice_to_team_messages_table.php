<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->string('type', 10)->default('text')->after('recipient_id');
            $table->string('voice_path')->nullable()->after('original_body');
            $table->string('voice_mime')->nullable()->after('voice_path');
            $table->unsignedSmallInteger('voice_duration')->nullable()->after('voice_mime');
        });
    }

    public function down(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'voice_path', 'voice_mime', 'voice_duration']);
        });
    }
};
