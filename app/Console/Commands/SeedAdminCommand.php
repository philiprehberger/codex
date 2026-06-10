<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * One-shot first-deploy command — seeds the single admin user. Password
 * complexity enforced via Password::min(16)->mixedCase()->numbers()
 * ->symbols()->uncompromised(); the user is the entire admin surface so
 * a weak password is the whole attack.
 *
 * 2FA enrolment happens on first login, enforced by the panel's
 * isRequired=true MFA setting. No 2FA setup happens at seed time.
 */
class SeedAdminCommand extends Command
{
    protected $signature = 'codex:seed-admin
        {--email= : admin email}
        {--password= : admin password (prompts if omitted)}';

    protected $description = 'Seeds the single Codex admin user. First-deploy only.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("Admin user with email {$email} already exists. Use codex:reset-admin-password to rotate.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Admin password (min 16, mixed case + numbers + symbols, not pwned)');

        try {
            Validator::make(
                ['password' => $password],
                ['password' => ['required', Password::min(16)->mixedCase()->numbers()->symbols()->uncompromised()]],
            )->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors()['password'] ?? [] as $msg) {
                $this->error($msg);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => 'Codex Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Seeded admin user {$email}. 2FA enrolment will be enforced on first login at /admin.");

        return self::SUCCESS;
    }
}
