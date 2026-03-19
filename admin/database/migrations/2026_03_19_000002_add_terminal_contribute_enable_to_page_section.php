<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_section', function (Blueprint $table) {
            $table->boolean('terminal_enable')->default(0)->after('articles_enable');
            $table->boolean('contribute_enable')->default(0)->after('terminal_enable');
        });
    }

    public function down(): void
    {
        Schema::table('page_section', function (Blueprint $table) {
            $table->dropColumn(['terminal_enable', 'contribute_enable']);
        });
    }
};
