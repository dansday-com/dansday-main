<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_prs', function (Blueprint $table) {
            $table->id();
            $table->string('repo', 255);
            $table->integer('pr_number');
            $table->string('title', 500);
            $table->integer('additions')->default(0);
            $table->integer('deletions')->default(0);
            $table->timestamp('merged_at');
            $table->boolean('is_private')->default(false);
            $table->unique(['repo', 'pr_number']);
            $table->index('merged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_prs');
    }
};
