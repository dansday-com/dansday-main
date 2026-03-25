<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = DB::select("SHOW INDEX FROM github_activity WHERE Key_name = 'github_activity_fulltext'");
        if (count($indexes) > 0) {
            Schema::table('github_activity', function (Blueprint $table) {
                $table->dropFullText('github_activity_fulltext');
            });
        }

        Schema::table('github_activity', function (Blueprint $table) {
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
