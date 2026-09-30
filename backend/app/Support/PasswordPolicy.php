<?php

namespace App\Support;

/**
 * PASSWORD POLICY (F7) — single source of truth for every site that SETS an
 * admin password (bootstrap login, admin:create, admin:reset-password, the
 * users API).
 *
 * Length over composition (NIST SP 800-63B): no forced character classes, no
 * rotation. The minimum was raised from 8 to 12 for NEWLY SET passwords;
 * existing hashes are grandfathered (login verifies the stored hash and never
 * re-checks the policy). Rotate the current admin passwords at deploy (see
 * the pre-deployment checklist) and they will be re-set under this rule.
 */
final class PasswordPolicy
{
    /** Minimum length for passwords that SET or CHANGE an admin password. */
    public const MIN_LENGTH = 12;

    /** Contract message — literal is part of the API/tests. */
    public const MESSAGE = 'Password must be at least 12 characters.';

    public static function isValid(string $password): bool
    {
        return strlen($password) >= self::MIN_LENGTH;
    }
}
