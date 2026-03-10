<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->string('openai_url', 500)->nullable()->after('image_favicon');
            $table->string('openai_key', 500)->nullable()->after('openai_url');
        });
    }

    public function down(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->dropColumn(['openai_url', 'openai_key']);
        });
    }
};
