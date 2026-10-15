#!/bin/bash

# ==============================================================================
# Permetheon Backend Test Runner
# ==============================================================================

# Navigate to project root
PROJECT_ROOT="/mnt/d/Permetheon website V3 PHP"
cd "$PROJECT_ROOT" || exit 1

# 1. Ensure Supabase is running
echo "Ensuring Supabase Local is active..."
./scripts/dev/start-supabase.sh
if [ $? -ne 0 ]; then
    echo "❌ Error: Supabase failed to start. Aborting tests."
    exit 1
fi

# 2. Verify DB Target Safety
# Check .env.testing or phpunit.xml for the correct DB settings
# We enforce these via environment variables to avoid accidental production hits.
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=54322
export DB_DATABASE=permetheon_test

# 3. Navigate to backend
cd backend || { echo "❌ Error: backend directory not found"; exit 1; }

# 4. Run the test command
if [ $# -eq 0 ]; then
    echo "Running complete test suite..."
    ./vendor/bin/pest
else
    echo "Running specific tests: $@"
    ./vendor/bin/pest "$@"
fi
