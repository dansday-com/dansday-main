<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectCategorySeeder extends Seeder
{
    public function run()
    {
        DB::table('project_categories')->insert([
            'name'  => 'design',
        ]);
        DB::table('project_categories')->insert([
            'name'  => 'video',
        ]);
        DB::table('project_categories')->insert([
            'name'  => 'photography',
        ]);
    }
}

