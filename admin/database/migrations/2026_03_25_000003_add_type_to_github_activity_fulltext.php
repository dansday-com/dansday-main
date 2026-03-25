<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_activity', function (Blueprint $table) {
            $table->dropFullText('github_activity_fulltext');
            $table->fullText(['repo', 'title', 'type'], 'github_activity_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('github_activity', function (Blueprint $table) {
            $table->dropFullText('github_activity_fulltext');
            $table->fullText(['repo', 'title'], 'github_activity_fulltext');
        });
    }
};
