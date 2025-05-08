<?php

namespace Database\Seeders;

use App\Models\ThemeCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ThemeCategory::create([
            'name' => 'Basic',
        ]);

        ThemeCategory::create([
            'name' => 'Premium',
        ]);
    }
}
