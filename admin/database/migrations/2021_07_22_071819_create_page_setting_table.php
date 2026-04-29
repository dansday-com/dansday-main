<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePageSettingTable extends Migration
{
    public function up()
    {
        Schema::create('page_setting', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('analytics_code')->nullable();
            $table->text('social_links');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_setting');
    }
}
