<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_activity', function (Blueprint $table) {
            $table->id();
            $table->string('repo', 255);
            $table->text('title');
            $table->enum('type', ['commit', 'pr', 'review', 'issue'])->default('commit');
            $table->timestamp('committed_at');
            $table->string('oid', 255)->unique();
            $table->boolean('is_private')->default(false);
            $table->integer('additions')->nullable();
            $table->integer('deletions')->nullable();
            $table->index('committed_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_activity');
    }
};
