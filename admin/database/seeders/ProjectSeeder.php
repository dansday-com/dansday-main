<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    public function run()
    {
        $description = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec a ligula pellentesque, malesuada orci cursus, lacinia sem.</p>
        <blockquote class="blockquote">Phasellus sit amet nulla quis odio egestas dictum. Aenean accumsan, felis a blandit eleifend.</blockquote>
        <p>Duis sagittis ex lectus, ac tincidunt libero viverra ullamcorper.</p>
        <ul>
        <li>Praesent imperdiet lorem sapien.</li>
        <li>Sed et finibus urna. Integer ante est.</li>
        <li>Placerat ac dui et, vehicula cursus nisi.</li>
        </ul>';
        $short = 'Lorem ipsum dolor sit amet, consectetur adicing elit pellente enim leo ipsum.';

        $projects = [
            ['category_id' => 1, 'image' => 'demo/img/projects/project_image_8708.jpg'],
            ['category_id' => 3, 'image' => 'demo/img/projects/project_image_3833.jpg'],
            ['category_id' => 2, 'image' => 'demo/img/projects/project_image_5551.jpg'],
            ['category_id' => 1, 'image' => 'demo/img/projects/project_image_168.jpg'],
            ['category_id' => 3, 'image' => 'demo/img/projects/project_image_5784.jpg'],
            ['category_id' => 2, 'image' => 'demo/img/projects/project_image_5330.jpg'],
            ['category_id' => 1, 'image' => 'demo/img/projects/project_image_6487.jpg'],
            ['category_id' => 3, 'image' => 'demo/img/projects/project_image_9837.jpg'],
            ['category_id' => 2, 'image' => 'demo/img/projects/project_image_2983.jpg'],
            ['category_id' => 1, 'image' => 'demo/img/projects/project_image_896.jpg'],
            ['category_id' => 3, 'image' => 'demo/img/projects/project_image_9860.jpg'],
        ];

        foreach ($projects as $p) {
            DB::table('project')->insert([
                'enable'      => 1,
                'title'       => 'Lorem ipsum dolor sit amet adipiscing',
                'short_desc'  => $short,
                'description' => $description,
                'image'       => $p['image'],
                'category_id' => $p['category_id'],
            ]);
        }
    }
}
