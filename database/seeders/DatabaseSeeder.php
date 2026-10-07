<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'orgnization_id' => null,
                'role_id' => 1,
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'status' => 1,
            ]
        );
    }
}