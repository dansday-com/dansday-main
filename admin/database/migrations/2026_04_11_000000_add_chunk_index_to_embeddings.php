<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropUnique(['table_name', 'row_id']);
            $table->unsignedSmallInteger('chunk_index')->default(0)->after('row_id');
            $table->unique(['table_name', 'row_id', 'chunk_index']);
            $table->index(['table_name', 'row_id']);
        });
    }

    public function down(): void
    {
        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropUnique(['table_name', 'row_id', 'chunk_index']);
            $table->dropIndex(['table_name', 'row_id']);
            $table->dropColumn('chunk_index');
            $table->unique(['table_name', 'row_id']);
        });
    }
};
