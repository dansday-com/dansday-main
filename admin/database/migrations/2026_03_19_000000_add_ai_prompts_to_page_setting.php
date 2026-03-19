<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->text('ai_terminal_prompt')->nullable()->after('ai_model');
            $table->text('ai_content_prompt')->nullable()->after('ai_terminal_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->dropColumn(['ai_terminal_prompt', 'ai_content_prompt']);
        });
    }
};
