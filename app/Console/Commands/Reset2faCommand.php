<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Last-resort path. Clears app_authentication_secret +
 * app_authentication_recovery_codes on the admin user, forcing
 * re-enrolment at next login (the panel's isRequired=true MFA setting).
 *
 * Required when BOTH 1Password and the sealed paper recovery codes are
 * unrecoverable. Logs to audit_log with action=reset_2fa so the
 * forensic record exists if the compromise vector was 2FA bypass.
 * Documented in infra/RECOVERY.md (Phase 8 deliverable).
 */
class Reset2faCommand extends Command
{
    protected $signature = 'codex:reset-2fa
        {--email= : admin email (asks if omitted)}
        {--confirm : skip the destructive-action confirmation prompt}';

    protected $description = 'Clears the admin user MFA secret + recovery codes. Forces re-enrolment.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin email');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No admin user with email {$email}.");

            return self::FAILURE;
        }

        if (! $this->option('confirm')
            && ! $this->confirm("This clears 2FA for {$email}. They will be forced to re-enrol on next login. Continue?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);

        AuditLog::create([
            'actor_id' => null,
            'action' => 'reset_2fa',
            'subject_type' => 'users',
            'subject_id' => $user->id,
            'reason' => 'MFA cleared via codex:reset-2fa.',
            'diff' => null,
            'created_at' => now(),
        ]);

        $this->info("2FA cleared for {$email}. Re-enrolment will be forced at next /admin login.");

        return self::SUCCESS;
    }
}
