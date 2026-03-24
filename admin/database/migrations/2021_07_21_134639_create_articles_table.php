<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticlesTable extends Migration
{
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->boolean('enable')->default(1);
            $table->string('title');
            $table->text('short_desc')->nullable();
            $table->text('description');
            $table->text('image')->nullable();
            $table->string('author');

            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('article_categories');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('articles');
    }
}
