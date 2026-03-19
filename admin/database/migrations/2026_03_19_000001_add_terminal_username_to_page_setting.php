<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->string('terminal_username', 100)->nullable()->after('ai_content_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            $table->dropColumn('terminal_username');
        });
    }
};
