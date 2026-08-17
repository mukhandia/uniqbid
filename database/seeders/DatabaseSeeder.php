<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => config('uniqbid.admin.name'),
            'email' => config('uniqbid.admin.email'),
            'password' => config('uniqbid.admin.password'),
            'is_admin' => true,
        ]);

        $this->call(UniqBidDemoSeeder::class);
    }
}
