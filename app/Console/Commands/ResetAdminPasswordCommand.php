<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Rotates the admin password. Same complexity rule as the seed command.
 * Logs to audit_log with action=admin_password_reset so a compromise
 * after rotation has a forensic trail.
 */
class ResetAdminPasswordCommand extends Command
{
    protected $signature = 'codex:reset-admin-password
        {--email= : admin email (asks if omitted)}
        {--password= : new password (prompts if omitted)}';

    protected $description = 'Rotates the admin password. Asks if --password omitted.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin email');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No admin user with email {$email}.");
            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('New password (min 16, mixed case + numbers + symbols, not pwned)');

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

        $user->password = Hash::make($password);
        $user->save();

        AuditLog::create([
            'actor_id' => null, // CLI invocation; no actor session
            'action' => 'admin_password_reset',
            'subject_type' => 'users',
            'subject_id' => $user->id,
            'reason' => 'Password rotated via codex:reset-admin-password.',
            'diff' => null,
            'created_at' => now(),
        ]);

        $this->info("Password rotated for {$email}.");

        return self::SUCCESS;
    }
}
