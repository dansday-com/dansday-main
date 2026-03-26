<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->string('ai_content_model', 255)->nullable()->after('ai_model');
            $table->string('ai_content_reasoning', 20)->nullable()->after('ai_content_model');
            $table->dropColumn(['ai_article_reasoning', 'ai_project_reasoning']);
        });
    }

    public function down(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->dropColumn(['ai_content_model', 'ai_content_reasoning']);
            $table->string('ai_article_reasoning', 20)->nullable();
            $table->string('ai_project_reasoning', 20)->nullable();
        });
    }
};
