# Supabase Local Development Guide

This project uses **Supabase Local** running within **WSL2** and **Docker Engine** to provide a consistent, isolated PostgreSQL environment for local development and testing.

## 🛠 Requirements

- **OS**: Windows 11 with WSL2 (Ubuntu recommended).
- **Runtime**: Docker Desktop (with WSL2 integration enabled for the distribution).
- **CLI**: Supabase CLI installed in the WSL distribution.
- **Project Path**: `/mnt/d/Permetheon website V3 PHP`

## 🌐 Connection Details

| Component | URL / Host | Port |
| :--- | :--- | :--- |
| **Supabase Studio** | `http://127.0.0.1:54323/` | 54323 |
| **PostgreSQL** | `127.0.0.1` | 54322 |
| **Test Database** | `permetheon_test` | - |

## 🚀 Quick Start

### 1. Start Supabase
To ensure the environment is ready, run the startup script from the project root:
\`\`\`bash
./scripts/dev/start-supabase.sh
\`\`\`

### 2. Check Health Status
To verify the state of Docker and Supabase without making changes:
\`\`\`bash
./scripts/dev/status-supabase.sh
\`\`\`

### 3. Run Backend Tests
To run Laravel/Pest tests against the local Supabase instance:
\`\`\`bash
# Run full suite
./scripts/dev/test-backend.sh

# Run specific test file
./scripts/dev/test-backend.sh tests/Feature/AdminApiTest.php

# Run specific test with filter
./scripts/dev/test-backend.sh tests/Feature/AdminApiTest.php --filter="it sets HttpOnly"
\`\`\`

## 🔄 Recovery after WSL Restart
If you perform a `wsl --shutdown` or restart your machine:
1. Launch Docker Desktop.
2. Open your WSL terminal.
3. Execute `./scripts/dev/start-supabase.sh`.

The script will detect that Supabase is stopped and automatically run `supabase start` to restore the environment.

## 🛡 Database Safety Rules

- **No Destructive Commands**: Never run `migrate:fresh`, `db:wipe`, or `DROP DATABASE` unless explicitly instructed for a full reset.
- **Strict Routing**: Always use port **54322** for database connections. Port **54323** is exclusively for the Studio management UI.
- **Environment Isolation**: Tests are forced to use `DB_DATABASE=permetheon_test`. Do not redirect them to production or other environment databases.
