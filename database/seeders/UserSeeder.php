<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create admin role
        $role = Role::firstOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'web'
            ],
            [
                'is_active' => 1
            ]
        );

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'phone' => '01700000000',
                'is_admin' => true,
                'password' => Hash::make('12345678'),
            ]
        );

        // Assign role
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
