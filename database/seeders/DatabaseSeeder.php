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
        $testUser = User::query()->firstOrNew(['email' => 'test@example.com']);
        $testUser->forceFill([
            'name' => 'Test User',
            'email_verified_at' => $testUser->email_verified_at ?? now(),
            'is_admin' => false,
            ...($testUser->exists ? [] : ['password' => 'password']),
        ])->save();

        $admin = User::query()->firstOrNew(['email' => config('uniqbid.admin.email')]);
        $admin->forceFill([
            'name' => config('uniqbid.admin.name'),
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'is_admin' => true,
            ...($admin->exists ? [] : ['password' => config('uniqbid.admin.password')]),
        ])->save();

        $this->call([
            SiteSettingSeeder::class,
            ContentSeeder::class,
            UniqBidDemoSeeder::class,
        ]);

        if (class_exists(RolePermissionSeeder::class)) {
            $this->call(RolePermissionSeeder::class);
        }
    }
}
