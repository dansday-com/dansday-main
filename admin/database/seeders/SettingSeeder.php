<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run()
    {
        DB::table('page_setting')->insert([
            'title'          => 'My Portfolio',
            'description'    => 'Articles, projects and experience, served from a terminal.',
            'analytics_code' => '',
            'ai_url'         => '',
            'ai_key'         => '',
            'ai_model'       => '',
            'social_links'   => '[{"title":"fab fa-linkedin-in","text":"http://#"}]',
        ]);
    }
}
