#!/bin/bash

# ==============================================================================
# Permetheon Supabase Local Status Script
# ==============================================================================

PROJECT_ROOT="/mnt/d/Permetheon website V3 PHP"
DB_HOST="127.0.0.1"
DB_PORT="54322"
STUDIO_URL="http://127.0.0.1:54323/"
TEST_DB="permetheon_test"

# Detect WSL/Linux
if [[ "$OSTYPE" != "linux-gnu"* ]]; then
    echo "❌ Error: This script must be run inside WSL/Linux."
    exit 1
fi

cd "$PROJECT_ROOT" || exit 1

echo "Checking Supabase Local Health..."
echo "----------------------------------------"

# 1. Docker Check
if docker info > /dev/null 2>&1; then
    echo "Docker:        ✅ OK"
else
    echo "Docker:        ❌ NOT RUNNING"
fi

# 2. Supabase Process Check
if supabase status > /dev/null 2>&1; then
    echo "Supabase:      ✅ RUNNING"
else
    echo "Supabase:      ❌ STOPPED"
fi

# 3. PostgreSQL Port Check
if nc -z "$DB_HOST" "$DB_PORT" > /dev/null 2>&1; then
    echo "PostgreSQL:    ✅ OK ($DB_HOST:$DB_PORT)"
else
    echo "PostgreSQL:    ❌ UNREACHABLE ($DB_HOST:$DB_PORT)"
fi

# 4. Studio Port Check
if curl -s --head "$STUDIO_URL" | grep "200 OK" > /dev/null 2>&1; then
    echo "Studio:        ✅ OK ($STUDIO_URL)"
else
    echo "Studio:        ❌ UNREACHABLE ($STUDIO_URL)"
fi

# 5. Database Existence Check
# Uses psql if available, otherwise relies on the port check.
if command -v psql > /dev/null 2>&1; then
    if psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -d "$TEST_DB" -c "\q" > /dev/null 2>&1; then
        echo "Test Database: ✅ $TEST_DB EXISTS"
    else
        echo "Test Database: ❌ $TEST_DB MISSING/UNREACHABLE"
    fi
else
    echo "Test Database: ⚠️  psql not installed (skipping deep check)"
fi

echo "----------------------------------------"
