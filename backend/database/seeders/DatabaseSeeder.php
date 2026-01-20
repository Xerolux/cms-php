<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Standard-Seeder für Basis-Konfiguration
        $this->call([
            // Admin-User und Basis-Kategorien
            AdminSeeder::class,

            // Beispiel-Content (Posts, Comments, etc.)
            // Uncomment to populate with example data:
            // ExampleContentSeeder::class,
        ]);
    }
}

