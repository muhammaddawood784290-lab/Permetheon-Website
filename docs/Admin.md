# Admin Panel — Access Credentials

> ⚠️ **Keep this file out of version control and out of screenshots.** It holds
> real passwords. It is intentionally placed in the project root for the local
> development environment (it is listed in `.gitignore`).

## Login

| | |
|---|---|
| **URL** | `/admin/login` — http://localhost:5174/admin/login (Vite dev) · http://127.0.0.1:8000/admin/login (Laravel production build, same-origin) |
| **Email** | `admin@permetheon.com` |
| **Password** | `18Y0IvNwBsaC-mmT0R` |
| **Role** | `SUPER_ADMIN` (all permissions: inquiries, meetings, availability, system, user administration) |
| **Account name** | Administrator |

## Abdullah — Manager

| | |
|---|---|
| **URL** | same as above |
| **Email** | `abdullahshahzad6@gmail.com` |
| **Password** | `cQpWAay6GJVyfvwg_tvQ` |
| **Role** | `MANAGER` — full operational control, **no user administration** |
| **Account name** | ABDULLAH |

**Abdullah CAN:** view/change/delete business inquiries, read & create notes,
see stats, view the meetings calendar, update/cancel/delete meetings, write
availability settings and blocks, and see the system auth/security views
(14 permissions — everything except user administration).

**Abdullah CANNOT:** create admin users, delete admin users, or change anyone's
role. `admins.create`, `admins.delete` and `admins.role.update` are denied at
the server-side permission gate (`backend/app/Services/Permissions.php`) — not
just hidden in the UI. He can still *view* the admin account list
(`admins.read`).

The `MANAGER` role was added 2026-09-30: backend gate in
`backend/app/Services/Permissions.php` (everything except `MANAGER_EXCLUDED`),
schema whitelist widened by migration
`backend/database/migrations/2026_09_30_000001_add_manager_role.php`
(`chk_admins_role` now allows `SUPER_ADMIN`,`MANAGER`,`ADMIN`), and the
frontend role type in `AdminDashboard.tsx` accepts it. Any future
user-management API that routes `admins.create/delete/role.update` through
`can:` middleware is denied for MANAGER automatically.

## Operator commands (no raw SQL needed)

Run from `backend/`:

```bash
php artisan admin:list                                  # accounts, roles, lockouts, live sessions
php artisan admin:reset-password <email>                # generates + prints a new password once
php artisan admin:reset-password <email> --password="…" # sets a specific password (min 8 chars)
php artisan admin:create <email> --role=MANAGER         # mint an account (SUPER_ADMIN | MANAGER | ADMIN)
```

`admin:reset-password` hashes with bcrypt exactly like the login service,
**revokes the account's live sessions** (a stolen session does not survive a
rotation), and clears failed-login/lockout counters. `admin:create` validates
the role against the same whitelist the `chk_admins_role` DB CHECK enforces and
never touches the one-time bootstrap credential. All three are covered by
`tests/Feature/AdminConsoleCommandsTest.php` (8 tests).

## Security properties (by design, do not weaken)

- Passwords are stored as **bcrypt** hashes — this file is the only plaintext copy.
- The bootstrap credential is **one-time** (`G5`): the marker file
  `backend/storage/app/admin-bootstrap.completed` makes a second bootstrap
  impossible even if `ADMIN_BOOTSTRAP_PASSWORD` is set again.
- Sessions are HttpOnly + SameSite=Strict cookies (`admin_session`) with
  double-submit CSRF (`admin_csrf`); login is rate-limited.
- `backend/.env` contains **no** admin secrets.
- Role values are constrained by a DB CHECK constraint (`chk_admins_role`), so
  an out-of-band INSERT cannot mint an unknown role.

## If a password is lost (local dev recovery)

```bash
cd backend
php artisan admin:list                       # find the exact account email
php artisan admin:reset-password <email>     # new password printed once
```

Then log in at `/admin/login` with the printed password (all previous sessions
of that account are revoked). To create an entirely new account, use
`php artisan admin:create <email> --role=...`.

**Never reuse these passwords anywhere. Rotate before any real deployment.**
