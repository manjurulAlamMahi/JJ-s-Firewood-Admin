<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\NotificationPreference;

class NotificationPreferencesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ['transactional', 'helpful', 'updates'];

        // Get all users (use cursor for large tables)
        User::cursor()->each(function ($user) use ($categories) {
            foreach ($categories as $category) {
                NotificationPreference::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'category' => $category,
                    ],
                    [
                        'email' => true,
                        'push' => true,
                    ]
                );
            }
        });

        $this->command->info('Notification preferences seeded successfully!');
    }
}
