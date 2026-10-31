<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed reference data only. Demo accounts and listings live in
     * DemoContentSeeder and must be run explicitly in local environments.
     */
    public function run(): void
    {
        $this->call(AreaSeeder::class);
    }
}
