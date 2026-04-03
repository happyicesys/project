<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // CEO account
        User::factory()->create([
            'name' => 'Brian (CEO)',
            'email' => 'leehongjie91@gmail.com',
        ]);

        // Register all OpenClaw agents
        $this->call(AgentSeeder::class);
    }
}
