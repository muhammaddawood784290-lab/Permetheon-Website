<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Support\Clock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ADMIN ACCOUNT CREATION — mints an additional admin account without touching
 * the one-time bootstrap credential (which stays permanently locked after
 * first use). Role is validated against the same whitelist the
 * `chk_admins_role` DB CHECK enforces, so the command and the schema can never
 * drift. Passwords are stored as bcrypt exactly like the login service.
 */
final class AdminCreate extends Command
{
    private const ROLES = ['SUPER_ADMIN', 'MANAGER', 'ADMIN'];

    protected $signature = 'admin:create
                            {email : Login email for the new account}
                            {--name= : Display name (defaults to the email local part)}
                            {--role=MANAGER : One of: SUPER_ADMIN, MANAGER, ADMIN}
                            {--password= : Password (min 8 chars); generated if omitted}';

    protected $description = 'Create an additional admin account (no raw SQL, bootstrap stays locked)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        $role = strtoupper(trim((string) $this->option('role')));
        if (! in_array($role, self::ROLES, true)) {
            $this->error('Role must be one of: '.implode(', ', self::ROLES).'.');
            $this->line('  SUPER_ADMIN — everything, including user administration.');
            $this->line('  MANAGER     — everything except user administration.');
            $this->line('  ADMIN       — read-mostly (inquiries, notes, calendar view).');

            return self::FAILURE;
        }

        if (AdminUser::where('email', $email)->exists()) {
            $this->error("An account for {$email} already exists (use admin:reset-password).");

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        $generated = $password === '';
        if ($generated) {
            // Same alphabet as admin:reset-password — unambiguous, 120 bits.
            $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_';
            for ($i = 0; $i < 20; $i++) {
                $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $this->line('Generated password (printed ONCE — store it in Admin.md or a password manager):');
        } elseif (! \App\Support\PasswordPolicy::isValid($password)) {
            $this->error(\App\Support\PasswordPolicy::MESSAGE);

            return self::FAILURE;
        }

        $now = Clock::now();
        AdminUser::create([
            'id'                 => (string) Str::uuid(),
            'email'              => $email,
            'name'               => (string) $this->option('name') !== '' ? (string) $this->option('name') : strtok($email, '@'),
            'password_hash'      => Hash::make($password),
            'role'               => $role,
            'is_active'          => 1,
            'failed_login_count' => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $this->info("Created {$email} as {$role}.");
        if ($generated) {
            $this->line("Password: {$password}");
        }

        return self::SUCCESS;
    }
}
