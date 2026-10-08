<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TccWebsiteSeeder::class,
            LandingCmsPagesSeeder::class,
            ResearchPublicationsSeeder::class,
        ]);
    }
}
