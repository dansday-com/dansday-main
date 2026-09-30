<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $embeddingColumns = [
        'embedding_url',
        'embedding_key',
        'embedding_model',
    ];

    private array $webColumns = [
        'search_api_url' => 500,
        'search_api_key' => 500,
        'search_model' => 255,
        'fetch_api_url' => 500,
        'fetch_api_key' => 500,
        'fetch_model' => 255,
    ];

    public function up(): void
    {
        Schema::dropIfExists('embeddings');

        Schema::table('page_setting', function (Blueprint $table) {
            foreach ($this->embeddingColumns as $column) {
                if (Schema::hasColumn('page_setting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('page_setting', function (Blueprint $table) {
            foreach ($this->webColumns as $column => $length) {
                if (! Schema::hasColumn('page_setting', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_setting', function (Blueprint $table) {
            foreach (array_keys($this->webColumns) as $column) {
                if (Schema::hasColumn('page_setting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('page_setting', function (Blueprint $table) {
            if (! Schema::hasColumn('page_setting', 'embedding_url')) {
                $table->string('embedding_url', 500)->nullable();
            }
            if (! Schema::hasColumn('page_setting', 'embedding_key')) {
                $table->string('embedding_key', 500)->nullable();
            }
            if (! Schema::hasColumn('page_setting', 'embedding_model')) {
                $table->string('embedding_model', 255)->nullable();
            }
        });

        if (! Schema::hasTable('embeddings')) {
            Schema::create('embeddings', function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 50);
                $table->unsignedBigInteger('row_id');
                $table->unsignedSmallInteger('chunk_index')->default(0);
                $table->string('content_hash', 64);
                $table->json('vector');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['table_name', 'row_id', 'chunk_index']);
                $table->index(['table_name', 'row_id']);
                $table->index('table_name');
            });
        }
    }
};
