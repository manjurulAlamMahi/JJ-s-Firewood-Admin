<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::updateOrCreate(
            ['id' => 1],
            [
                'system_title'       => 'My Laravel System',
                'system_short_title' => 'LaravelSys',
                'company_name'       => 'My Company Ltd.',
                'company_address'    => 'Dhaka, Bangladesh',
                'tagline'            => 'Best Laravel App',
                'phone'              => '+880123456789',
                'email'              => 'admin@example.com',
                'timezone'           => 'Asia/Dhaka',
                'language'           => 'en',
                'copyright_text'     => '© 2025 My Laravel System. All rights reserved.',
                'admin_title'        => 'Admin Panel',
                'short_title'        => 'AdminSys',
                'admin_copyright_text' => '© 2025 Admin Panel',
            ]
        );
    }
}
