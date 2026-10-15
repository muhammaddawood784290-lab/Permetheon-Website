-- =============================================================================
-- FILE: supabase_schema.sql
-- PURPOSE: Base schema for Permetheon Website V3.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. EXTENSIONS
-- -----------------------------------------------------------------------------
-- Required for UUIDs (though auth.users handles this, custom IDs use it)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- -----------------------------------------------------------------------------
-- 2. ENUMS
-- -----------------------------------------------------------------------------
-- Meeting Statuses
CREATE TYPE meeting_status AS ENUM ('BOOKED', 'COMPLETED', 'CANCELLED', 'NO_SHOW');

-- Inquiry Statuses
CREATE TYPE inquiry_status AS ENUM ('NEW', 'REVIEWING', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'WON', 'LOST');

-- Inquiry Priority
CREATE TYPE inquiry_priority AS ENUM ('LOW', 'MEDIUM', 'HIGH', 'URGENT');

-- User Roles
CREATE TYPE user_role AS ENUM ('SUPER_ADMIN', 'MANAGER', 'ADMIN');

-- -----------------------------------------------------------------------------
-- 3. TABLES
-- -----------------------------------------------------------------------------

-- User Profiles (Extends auth.users)
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    role user_role NOT NULL DEFAULT 'ADMIN',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Business Inquiries
CREATE TABLE IF NOT EXISTS public.business_inquiries (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    contact_number VARCHAR(16) NOT NULL,
    project_type TEXT NOT NULL,
    message TEXT NOT NULL,
    company TEXT,
    budget TEXT,
    timeline TEXT,
    city TEXT,
    country TEXT,
    status inquiry_status NOT NULL DEFAULT 'NEW',
    priority inquiry_priority NOT NULL DEFAULT 'MEDIUM',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- Canonical Contact Number Validation: ^\+[1-9]\d{1,14}$
    CONSTRAINT chk_inquiries_contact_e164 CHECK (contact_number ~ '^\+[1-9][0-9]{1,14}$'),
    -- Validation from Laravel Reference
    CONSTRAINT chk_inquiries_name_length CHECK (CHAR_LENGTH(name) BETWEEN 2 AND 100),
    CONSTRAINT chk_inquiries_email_length CHECK (CHAR_LENGTH(email) <= 254),
    CONSTRAINT chk_inquiries_message_length CHECK (CHAR_LENGTH(message) BETWEEN 20 AND 5000)
);

-- Inquiry Notes
CREATE TABLE IF NOT EXISTS public.inquiry_notes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    inquiry_id BIGINT NOT NULL REFERENCES public.business_inquiries(id) ON DELETE CASCADE,
    admin_id UUID NOT NULL REFERENCES public.profiles(id),
    body TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Meeting Settings (Strict Singleton)
CREATE TABLE IF NOT EXISTS public.meeting_settings (
    id BIGINT PRIMARY KEY DEFAULT 1,
    business_timezone TEXT NOT NULL DEFAULT 'UTC',
    working_days TEXT NOT NULL DEFAULT '1,2,3,4,5',
    day_start_minutes SMALLINT NOT NULL DEFAULT 540,
    day_end_minutes SMALLINT NOT NULL DEFAULT 1200,
    slot_interval_minutes SMALLINT NOT NULL DEFAULT 60,
    meeting_duration_minutes SMALLINT NOT NULL DEFAULT 30,
    booking_window_days SMALLINT NOT NULL DEFAULT 30,
    min_lead_time_minutes SMALLINT NOT NULL DEFAULT 120,
    enabled BOOLEAN NOT NULL DEFAULT true,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_ms_days CHECK (working_days ~ '^[0-6](,[0-6])*$'),
    CONSTRAINT chk_ms_window CHECK (day_start_minutes < day_end_minutes AND day_end_minutes <= 1440),
    -- Strict Singleton Constraint: ensures id is always 1
    CONSTRAINT chk_settings_singleton CHECK (id = 1)
);

-- Meeting Blocks
CREATE TABLE IF NOT EXISTS public.meeting_blocks (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    blocked_date DATE,
    starts_at TIMESTAMPTZ,
    reason TEXT DEFAULT '',
    blocker_id UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- Must be either a date block or a slot block, not both or neither
    CONSTRAINT chk_blocks_target CHECK (
        (blocked_date IS NOT NULL AND starts_at IS NULL) OR
        (blocked_date IS NULL AND starts_at IS NOT NULL)
    )
);

-- Allow an admin to block an individual meeting slot by its starts_at.
-- Blocks are independent of meetings; cancelling a meeting does not remove
-- its block.
CREATE OR REPLACE FUNCTION public.block_meeting_slot(
    p_starts_at TIMESTAMPTZ,
    p_reason TEXT DEFAULT ''
)
RETURNS BIGINT
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_id BIGINT;
BEGIN
    INSERT INTO public.meeting_blocks (blocked_date, starts_at, reason, created_at)
    VALUES (NULL, p_starts_at, p_reason, NOW())
    RETURNING id INTO v_id;
    RETURN v_id;
END;
$$;

REVOKE EXECUTE ON FUNCTION public.block_meeting_slot(TIMESTAMPTZ, TEXT) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.block_meeting_slot(TIMESTAMPTZ, TEXT) TO authenticated;

-- Meetings
CREATE TABLE IF NOT EXISTS public.meetings (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    inquiry_id BIGINT NOT NULL REFERENCES public.business_inquiries(id) ON DELETE RESTRICT,
    starts_at TIMESTAMPTZ NOT NULL,
    duration_minutes SMALLINT NOT NULL DEFAULT 30,
    status meeting_status NOT NULL DEFAULT 'BOOKED',
    cancelled_reason TEXT DEFAULT '',
    cancelled_by UUID REFERENCES public.profiles(id),
    cancelled_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_meetings_duration CHECK (duration_minutes BETWEEN 10 AND 240),
    CONSTRAINT chk_meetings_cancel CHECK (
        (status IN ('CANCELLED', 'NO_SHOW')) OR
        (cancelled_at IS NULL AND cancelled_by IS NULL)
    )
);

-- Admin Audit Log
CREATE TABLE IF NOT EXISTS public.admin_audit_log (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    admin_id UUID NOT NULL REFERENCES public.profiles(id),
    admin_email TEXT,
    action TEXT NOT NULL,
    resource_type TEXT,
    resource_id TEXT,
    summary JSONB,
    ip TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
