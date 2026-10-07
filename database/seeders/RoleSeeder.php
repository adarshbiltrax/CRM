<?php

namespace Database\Seeders;

use App\Models\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Roles::query()->updateOrCreate(
            ['id' => 1],
            ['role' => 'Super Admin']
        );

        Roles::query()->updateOrCreate(
            ['id' => 2],
            ['role' => 'Client Admin']
        );

        Roles::query()->updateOrCreate(
            ['id' => 3],
            ['role' => 'Sales Manager']
        );

        Roles::query()->updateOrCreate(
            ['id' => 4],
            ['role' => 'Sales Executive']
        );
    }
}
