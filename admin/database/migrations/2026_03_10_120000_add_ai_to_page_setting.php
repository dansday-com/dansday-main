<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->string('ai_url', 500)->nullable()->after('image_favicon');
            $table->string('ai_key', 500)->nullable()->after('ai_url');
            $table->string('ai_model', 255)->nullable()->after('ai_key');
        });
    }

    public function down(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->dropColumn(['ai_url', 'ai_key', 'ai_model']);
        });
    }
};
