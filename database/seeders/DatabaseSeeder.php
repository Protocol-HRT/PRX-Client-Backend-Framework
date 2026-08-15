<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only install-agnostic baseline data belongs here — this runs on every
     * fresh client deployment. Dev/demo content (DevCatalogSeeder,
     * HomePageSeeder) is invoked explicitly, never from here.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(BaseRolesSeeder::class);
    }
}
