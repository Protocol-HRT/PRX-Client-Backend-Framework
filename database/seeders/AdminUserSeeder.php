<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the install's first super-admin and the full Shield permission set.
 *
 * Credentials come from .env (ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD via
 * config/app.php) so nothing operator-specific lives in code. When
 * ADMIN_PASSWORD is unset, a random password is generated and printed once.
 *
 * Idempotent: re-running refreshes permissions and updates the admin user.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--no-interaction' => true,
        ]);
        $this->command?->info('Shield permissions generated.');

        $email = config('app.admin_email');

        if (blank($email)) {
            $this->command?->warn('ADMIN_EMAIL is not set — skipping admin user creation. Set it in .env and re-run: php artisan db:seed --class=AdminUserSeeder');

            return;
        }

        $password = config('app.admin_password');

        if (blank($password)) {
            $password = Str::password(20);
            $this->command?->warn("ADMIN_PASSWORD not set — generated one-time password for {$email}: {$password}");
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('app.admin_name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        Artisan::call('shield:super-admin', [
            '--user' => $user->id,
            '--panel' => 'admin',
        ]);
        $this->command?->info("Super admin ready: {$email}");
    }
}
