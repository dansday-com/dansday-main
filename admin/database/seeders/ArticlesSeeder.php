<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticlesSeeder extends Seeder
{
    public function run()
    {
        DB::table('articles')->insert([
            'enable'        => 1,
            'title'         => 'Lorem ipsum dolor sit amet consectetur adipiscing',
            'short_desc'    => 'Suspendisse libero odio, vulputate non pellentesque eu, interdum et purus. Integer sodales magna non nibh porta ultricies.',
            'description'   => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec a ligula pellentesque, malesuada orci cursus, lacinia sem.</p>',
            'image'         => 'demo/img/articles/post_image_6429.jpg',
            'author'        => 'Creabox',
            'category_id'   => 1,
        ]);

        DB::table('articles')->insert([
            'enable'        => 1,
            'title'         => 'Lorem ipsum dolor sit amet consectetur adipiscing',
            'short_desc'    => 'Suspendisse libero odio, vulputate non pellentesque eu, interdum et purus. Integer sodales magna non nibh porta ultricies.',
            'description'   => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec a ligula pellentesque, malesuada orci cursus, lacinia sem.</p>',
            'image'         => 'demo/img/articles/post_image_8644.jpg',
            'author'        => 'Creabox',
            'category_id'   => 2,
        ]);

        DB::table('articles')->insert([
            'enable'        => 1,
            'title'         => 'Lorem ipsum dolor sit amet consectetur adipiscing',
            'short_desc'    => 'Suspendisse libero odio, vulputate non pellentesque eu, interdum et purus. Integer sodales magna non nibh porta ultricies.',
            'description'   => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec a ligula pellentesque, malesuada orci cursus, lacinia sem.</p>',
            'image'         => 'demo/img/articles/post_image_6429.jpg',
            'author'        => 'Creabox',
            'category_id'   => 1,
        ]);

        DB::table('articles')->insert([
            'enable'        => 1,
            'title'         => 'Lorem ipsum dolor sit amet consectetur adipiscing',
            'short_desc'    => 'Suspendisse libero odio, vulputate non pellentesque eu, interdum et purus. Integer sodales magna non nibh porta ultricies.',
            'description'   => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec a ligula pellentesque, malesuada orci cursus, lacinia sem.</p>',
            'image'         => 'demo/img/articles/post_image_8644.jpg',
            'author'        => 'Creabox',
            'category_id'   => 2,
        ]);

        DB::table('articles')->insert([
            'enable'        => 1,
            'title'         => 'Lorem ipsum dolor sit amet consectetur adipiscing',
            'short_desc'    => 'Suspendisse libero odio, vulputate non pellentesque eu, interdum et purus. Integer sodales magna non nibh porta ultricies.',
            'description'   => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec a ligula pellentesque, malesuada orci cursus, lacinia sem.</p>',
            'image'         => 'demo/img/articles/post_image_6429.jpg',
            'author'        => 'Creabox',
            'category_id'   => 1,
        ]);
    }
}
