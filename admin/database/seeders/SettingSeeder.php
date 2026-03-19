<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run()
    {
        DB::table('page_setting')->insert([
            'title'          => 'Dansday Portfolio',
            'description'    => 'A great portfolio to show your work created with Laravel',
            'analytics_code' => '',
            'ai_url'         => '',
            'ai_key'         => '',
            'ai_model'       => '',
            'social_links'   => '[{"title":"fab fa-linkedin-in","text":"http://#"}]',
            'image_favicon'  => 'uploads/img/general/favicon/favicon.png',
        ]);
    }
}
