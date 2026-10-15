#!/bin/bash

# ==============================================================================
# Permetheon Supabase Local Startup Script
# ==============================================================================

PROJECT_ROOT="/mnt/d/Permetheon website V3 PHP"
DB_HOST="127.0.0.1"
DB_PORT="54322"
STUDIO_URL="http://127.0.0.1:54323/"
TEST_DB="permetheon_test"

# 1. Detect WSL/Linux
if [[ "$OSTYPE" != "linux-gnu"* ]]; then
    echo "❌ Error: This script must be run inside WSL/Linux."
    exit 1
fi

# 2. Navigate to project root
cd "$PROJECT_ROOT" || { echo "❌ Error: Could not navigate to $PROJECT_ROOT"; exit 1; }

# 3. Verify Docker Engine
if ! docker info > /dev/null 2>&1; then
    echo "❌ Error: Docker Engine is not running."
    echo "   Action: Please ensure the Docker daemon is started within your WSL2 distribution."
    exit 1
fi

# 4. Check if Supabase is already running
# 'supabase status' returns 0 if running, non-zero otherwise
if supabase status > /dev/null 2>&1; then
    SUPABASE_STATE="RUNNING"
else
    echo "Starting Supabase Local... (this may take a moment)"
    if ! supabase start; then
        echo "❌ Error: 'supabase start' failed."
        exit 1
    fi
    SUPABASE_STATE="STARTED"
fi

# 5. Verify PostgreSQL reachability
# Using nc (netcat) to check the port
if nc -z "$DB_HOST" "$DB_PORT" > /dev/null 2>&1; then
    PG_STATUS="OK"
else
    PG_STATUS="FAILED"
fi

# 6. Verify Studio reachability
if curl -s --head "$STUDIO_URL" | grep "200 OK" > /dev/null 2>&1; then
    STUDIO_STATUS="OK"
else
    STUDIO_STATUS="FAILED (non-blocking)"
fi

# 7. Print summary
echo "========================================"
echo "Permetheon Supabase Local"
echo "========================================"
echo "Docker:        OK"
echo "Supabase:      $SUPABASE_STATE"
echo "PostgreSQL:    $DB_HOST:$DB_PORT $PG_STATUS"
echo "Studio:        $STUDIO_URL $STUDIO_STATUS"
echo ""
echo "Test database:"
echo "$TEST_DB"
echo ""
echo "Ready for Laravel tests."
echo "========================================"

if [[ "$PG_STATUS" == "FAILED" ]]; then
    exit 1
fi
