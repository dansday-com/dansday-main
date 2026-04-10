<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `embeddings`"))->pluck('Key_name')->unique()->toArray();
        $hasChunkCol = \Illuminate\Support\Facades\Schema::hasColumn('embeddings', 'chunk_index');

        if (!$hasChunkCol) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `embeddings` ADD COLUMN `chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `row_id`");
        }

        if (in_array('embeddings_table_name_row_id_unique', $indexes)) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `embeddings` DROP INDEX `embeddings_table_name_row_id_unique`");
        }

        if (in_array('embeddings_table_name_row_id_chunk_index_unique', $indexes)) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `embeddings` DROP INDEX `embeddings_table_name_row_id_chunk_index_unique`");
        }
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `embeddings` ADD UNIQUE KEY `embeddings_table_name_row_id_chunk_index_unique` (`table_name`, `row_id`, `chunk_index`)");

        if (!in_array('embeddings_table_name_row_id_index', $indexes)) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `embeddings` ADD INDEX `embeddings_table_name_row_id_index` (`table_name`, `row_id`)");
        }
    }

    public function down(): void
    {
        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropUnique(['table_name', 'row_id', 'chunk_index']);
            $table->dropIndex(['table_name', 'row_id']);
            $table->dropColumn('chunk_index');
        });
    }
};
