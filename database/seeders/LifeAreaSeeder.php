<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LifeAreaSeeder extends Seeder {
    public function run(): void {
        DB::table('life_areas')->insert([
            [
                'name' => 'Social',
                'status' => 'active',
                'color' => '#E91E63',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional',
                'status' => 'active',
                'color' => '#2196F3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Personal',
                'status' => 'active',
                'color' => '#4CAF50',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Academic',
                'status' => 'active',
                'color' => '#9C27B0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Hobbies',
                'status' => 'active',
                'color' => '#FF9800',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
