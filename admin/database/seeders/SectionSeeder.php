<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionSeeder extends Seeder
{
    public function run()
    {
        DB::table('page_section')->insert([
            'about_enable'              => 1,
            'about_experience_order'    => 1,
            'about_services_order'      => 2,
            'about_skills_order'        => 3,
            'about_testimonial_order'   => 4,
            'experience_enable'         => 1,
            'skills_enable'             => 1,
            'testimonial_enable'        => 1,
            'services_enable'           => 1,
            'projects_enable'           => 1,
            'articles_enable'           => 1,
            'terminal_enable'           => 0,
            'contribute_enable'         => 0,
        ]);
    }
}
