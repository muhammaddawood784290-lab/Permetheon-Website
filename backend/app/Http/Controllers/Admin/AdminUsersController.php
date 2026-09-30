<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Services\ApiResponse;
use App\Services\Permissions;
use App\Support\Clock;

/**
 * ADMIN USER MANAGEMENT — list, create, delete and re-role admin accounts.
 *
 * AUTHORITY: every route requires admins.read (list) or the matching write
 * permission (admins.create / admins.delete / admins.role.update) — the
 * MANAGER role is denied the write routes at the gate, before this controller
 * runs. Self-protection rules below are enforced here in addition to the gate:
 *
 *   - nobody deletes or demotes their own account (brings the API back to a
 *     safe state instead of a session that outlives its authority);
 *   - the LAST active SUPER_ADMIN cannot be deleted or demoted (guarantees
 *     the system always retains full user-management authority — otherwise
 *     user administration could be lost entirely);
 *   - role values are validated against the SAME whitelist the
 *     `chk_admins_role` DB CHECK enforces (controller and schema cannot drift);
 *   - every write revokes the target's live sessions where the change
 *     invalidates them (role change, deactivate-by-delete), so a demoted or
 *     deleted admin cannot ride an existing session.
 */
class AdminUsersController extends Controller
{
    /** Mirrors the `chk_admins_role` CHECK constraint (and Permissions::forRole). */
    public const ROLES = ['SUPER_ADMIN', 'MANAGER', 'ADMIN'];

    public const MIN_PASSWORD_LENGTH = 8;
    public const MAX_NAME_LENGTH = 100;

    public function index(): JsonResponse
    {
        $admins = AdminUser::query()
            ->orderBy('email')
            ->get()
            ->map(fn (AdminUser $a) => $this->present($a))
            ->all();

        return ApiResponse::ok(['admins' => $admins]);
    }

    public function store(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }

        $fields = $this->validate($body);
        if ($fields instanceof JsonResponse) {
            return $fields;
        }

        if (AdminUser::where('email', $fields['email'])->exists()) {
            return ApiResponse::validation(['email' => 'An account with this email already exists.']);
        }

        $now = Clock::now();
        $admin = AdminUser::create([
            'id'                 => (string) Str::uuid(),
            'email'              => $fields['email'],
            'name'               => $fields['name'],
            'password_hash'      => \Illuminate\Support\Facades\Hash::make($fields['password']),
            'role'               => $fields['role'],
            'is_active'          => 1,
            'failed_login_count' => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        \App\Services\AuditLog::record($request, 'admin_user.create', 'admin_user', $admin->id, [
            'email' => $admin->email, 'role' => $admin->role,
        ]);

        return ApiResponse::ok(['admin' => $this->present($admin)], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $target = AdminUser::find($id);
        if (! $target) {
            return ApiResponse::notFound('Admin account not found.');
        }

        $actor = $request->attributes->get('admin');
        if ($target->id === $actor->id) {
            return ApiResponse::conflict('You cannot delete your own account.');
        }
        if ($this->isLastActiveSuperAdmin($target)) {
            return ApiResponse::conflict('The last active SUPER_ADMIN account cannot be deleted.');
        }

        $liveSessions = AdminSession::where('admin_id', $target->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Clock::now()]);

        $deletedEmail = $target->email;
        $target->delete();

        \App\Services\AuditLog::record($request, 'admin_user.delete', 'admin_user', $id, [
            'email' => $deletedEmail, 'revokedSessions' => (int) $liveSessions,
        ]);

        return ApiResponse::ok(['deleted' => true, 'revokedSessions' => $liveSessions]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }

        $role = is_string($body['role'] ?? null) ? strtoupper(trim($body['role'])) : '';
        if (! in_array($role, self::ROLES, true)) {
            return ApiResponse::validation([
                'role' => 'Role must be one of: ' . implode(', ', self::ROLES) . '.',
            ]);
        }

        $target = AdminUser::find($id);
        if (! $target) {
            return ApiResponse::notFound('Admin account not found.');
        }

        $actor = $request->attributes->get('admin');
        if ($target->id === $actor->id) {
            return ApiResponse::conflict('You cannot change your own role.');
        }
        if ($target->role === 'SUPER_ADMIN' && $role !== 'SUPER_ADMIN'
            && $this->isLastActiveSuperAdmin($target)) {
            return ApiResponse::conflict('The last active SUPER_ADMIN cannot be demoted.');
        }

        $previous = $target->role;
        $target->role = $role;
        $target->updated_at = Clock::now();
        $target->save();

        // A role change rewrites what live sessions may do — end them.
        AdminSession::where('admin_id', $target->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Clock::now()]);

        \App\Services\AuditLog::record($request, 'admin_user.role.update', 'admin_user', $target->id, [
            'email' => $target->email, 'from' => $previous, 'to' => $role,
        ]);

        return ApiResponse::ok([
            'admin'        => $this->present($target),
            'previousRole' => $previous,
        ]);
    }

    /** Guard helper: is this account the only active SUPER_ADMIN? */
    private function isLastActiveSuperAdmin(AdminUser $target): bool
    {
        if ($target->role !== 'SUPER_ADMIN' || ! $target->isActive()) {
            return false;
        }

        $activeSuperAdmins = AdminUser::where('role', 'SUPER_ADMIN')
            ->where('is_active', 1)
            ->count();

        return $activeSuperAdmins <= 1;
    }

    /** @return array<string, mixed>|JsonResponse */
    private function validate(array $body): array|JsonResponse
    {
        $errors = [];

        $email = strtolower(trim(is_string($body['email'] ?? null) ? $body['email'] : ''));
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (strlen($email) > 254) {
            $errors['email'] = 'Email is too long.';
        }

        $name = trim(is_string($body['name'] ?? null) ? $body['name'] : '');
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Name must be at most ' . self::MAX_NAME_LENGTH . ' characters.';
        }

        $role = strtoupper(trim(is_string($body['role'] ?? null) ? $body['role'] : ''));
        if (! in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Role must be one of: ' . implode(', ', self::ROLES) . '.';
        }

        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        if (! \App\Support\PasswordPolicy::isValid($password)) {
            $errors['password'] = \App\Support\PasswordPolicy::MESSAGE;
        }

        if ($errors !== []) {
            return ApiResponse::validation($errors);
        }

        return ['email' => $email, 'name' => $name, 'role' => $role, 'password' => $password];
    }

    /** Wire format — never the password hash, includes live-session count. */
    private function present(AdminUser $a): array
    {
        return [
            'id'           => $a->id,
            'email'        => $a->email,
            'name'         => $a->name,
            'role'         => $a->role,
            'isActive'     => (bool) $a->is_active,
            'liveSessions' => AdminSession::where('admin_id', $a->id)
                ->whereNull('revoked_at')
                ->where('absolute_expires_at', '>', Clock::now())
                ->count(),
            'createdAt'    => $a->created_at,
        ];
    }
}
