<?php

namespace App\Console\Commands;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Support\Clock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * ADMIN PASSWORD RECOVERY — the supported way to set a new password for an
 * existing admin account (operator action on the host machine; replaces the
 * raw-SQL UPDATE it supersedes). Hashes with bcrypt exactly like the login
 * service stores it, revokes the account's live sessions (a stolen session
 * must not survive a credential rotation) and clears failed-login/lockout
 * state so the account is immediately usable again.
 *
 * The new password can be passed via --password (scripted runs) or is
 * generated cryptographically and printed once when omitted.
 */
final class AdminResetPassword extends Command
{
    protected $signature = 'admin:reset-password
                            {email : Email of the existing admin account}
                            {--password= : New password (min 8 chars); generated if omitted}
                            {--show : Echo the generated password to stdout (default: yes, printed once)}';

    protected $description = 'Set a new password for an existing admin account (revokes their live sessions)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $admin = AdminUser::where('email', $email)->first();
        if (! $admin) {
            $known = AdminUser::orderBy('email')->pluck('email')->all();
            $this->error("No admin account for {$email}.");
            $this->line('Existing accounts:');
            foreach ($known as $e) {
                $this->line("  - {$e}");
            }

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if ($password === '') {
            $password = $this->generatePassword();
            $this->line('Generated password (printed ONCE - store it in Admin.md or a password manager):');
        }

        if (! \App\Support\PasswordPolicy::isValid($password)) {
            $this->error(\App\Support\PasswordPolicy::MESSAGE);

            return self::FAILURE;
        }

        $now = Clock::now();
        $admin->password_hash = Hash::make($password);
        $admin->failed_login_count = 0;
        $admin->locked_until = null;
        $admin->updated_at = $now;
        $admin->save();

        $revoked = AdminSession::where('admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        $this->info("Password updated for {$admin->email} (role {$admin->role}).");
        $this->line("Live sessions revoked: {$revoked}.");
        if ($password === (string) $this->option('password')) {
            $this->line('Sessions were NOT re-created — log in normally at /admin/login.');
        } else {
            $this->line("New password: {$password}");
        }

        return self::SUCCESS;
    }

    private function generatePassword(): string
    {
        // 20 chars over a 64-symbol alphabet ~ 120 bits; avoids lookalikes
        // (0/O, 1/l/I) and shell-hostile characters.
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_';
        $out = '';
        for ($i = 0; $i < 20; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
