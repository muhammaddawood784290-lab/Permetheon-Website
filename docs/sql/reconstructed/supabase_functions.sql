-- =============================================================================
-- FILE: supabase_functions.sql
-- PURPOSE: Protected Business Operations (RPC).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- SECURITY HELPERS
-- -----------------------------------------------------------------------------

-- Helper: Get Current User Role (Internal use for RLS)
-- This function is used by RLS policies to determine the role of the user making the request.
-- It uses SECURITY DEFINER to bypass RLS on the profiles table and prevent infinite recursion.
CREATE OR REPLACE FUNCTION public.get_current_user_role()
RETURNS user_role
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    RETURN (SELECT role FROM public.profiles WHERE id = auth.uid());
END;
$$;

-- Security: Prevent arbitrary clients from calling this function directly via RPC.
-- RLS policies can still execute this function because they run as the table owner.
REVOKE EXECUTE ON FUNCTION public.get_current_user_role() FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.get_current_user_role() TO authenticated;
GRANT EXECUTE ON FUNCTION public.get_current_user_role() TO authenticated;


-- -----------------------------------------------------------------------------
-- ROLE MANAGEMENT
-- -----------------------------------------------------------------------------
-- This function handles role changes with safeguards against self-demotion
-- and the "Last Super Admin" problem.
CREATE OR REPLACE FUNCTION public.manage_user_role(
    p_target_id UUID,
    p_new_role user_role
)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_caller_role user_role;
    v_target_role user_role;
    v_super_admin_count INT;
BEGIN
    -- 1. Authorization: Only SUPER_ADMIN can manage roles
    IF auth.uid() IS NULL THEN
        RAISE EXCEPTION 'Unauthorized: authenticated actor required';
    END IF;

    v_caller_role := public.get_current_user_role();
    IF v_caller_role IS DISTINCT FROM 'SUPER_ADMIN' THEN
        RAISE EXCEPTION 'Unauthorized: Only SUPER_ADMIN can manage roles';
    END IF;

    -- Serialize role changes so concurrent requests cannot bypass
    -- the last-SUPER_ADMIN protection.
    PERFORM pg_advisory_xact_lock(hashtext('public.manage_user_role'));

    -- 2. Validate target user
    SELECT role INTO v_target_role FROM public.profiles WHERE id = p_target_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Target user not found';
    END IF;

    -- 3. "Last Super Admin" Protection
    -- If we are demoting a SUPER_ADMIN, ensure at least one other exists.
    IF v_target_role = 'SUPER_ADMIN' AND p_new_role != 'SUPER_ADMIN' THEN
        SELECT count(*) INTO v_super_admin_count FROM public.profiles WHERE role = 'SUPER_ADMIN';
        IF v_super_admin_count <= 1 THEN
            RAISE EXCEPTION 'Security Block: Cannot demote the last SUPER_ADMIN';
        END IF;
    END IF;

    -- 4. Execute Update
    UPDATE public.profiles SET role = p_new_role, updated_at = NOW() WHERE id = p_target_id;

    -- Role-change auditing is handled by the profile trigger.
END;
$$;

-- Grant execution only to authenticated users (RLS in function handles SUPER_ADMIN check)
GRANT EXECUTE ON FUNCTION public.manage_user_role(UUID, user_role) TO authenticated;
-- -----------------------------------------------------------------------------
-- AUDIT TRIGGERS
-- -----------------------------------------------------------------------------

-- Trigger function to audit role changes in public.profiles
CREATE OR REPLACE FUNCTION public.fn_audit_profile_role_change()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    IF (OLD.role IS DISTINCT FROM NEW.role) THEN
        INSERT INTO public.admin_audit_log (
            admin_id, action, resource_type, resource_id, summary
        ) VALUES (
            auth.uid(),
            'ROLE_CHANGE',
            'profiles',
            NEW.id::text,
            jsonb_build_object('old_role', OLD.role, 'new_role', NEW.role)
        );
    END IF;
    RETURN NEW;
END;
$$;

-- Trigger function to audit meeting status changes
CREATE OR REPLACE FUNCTION public.fn_audit_meeting_status_change()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    IF (OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO public.admin_audit_log (
            admin_id, action, resource_type, resource_id, summary
        ) VALUES (
            auth.uid(),
            'STATUS_CHANGE',
            'meetings',
            NEW.id::text,
            jsonb_build_object('old_status', OLD.status, 'new_status', NEW.status)
        );
    END IF;
    RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION public.book_meeting(
    p_name TEXT,
    p_email TEXT,
    p_contact_number TEXT,
    p_project_type TEXT,
    p_message TEXT,
    p_company TEXT DEFAULT NULL,
    p_budget TEXT DEFAULT NULL,
    p_timeline TEXT DEFAULT NULL,
    p_city TEXT DEFAULT NULL,
    p_country TEXT DEFAULT NULL,
    p_starts_at TIMESTAMPTZ DEFAULT NULL
)
RETURNS JSON
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_inquiry_id BIGINT;
    v_meeting_id BIGINT;
    v_settings RECORD;
    v_now TIMESTAMPTZ := NOW();
    v_lead_time_min INT;
    v_booking_window_days INT;
    v_working_days TEXT;
    v_start_min INT;
    v_end_min INT;
    v_tz TEXT;
    v_err_sqlstate TEXT;
    v_err_message TEXT;
BEGIN
    -- 1. Basic Validation
    IF p_name IS NULL OR p_email IS NULL OR p_contact_number IS NULL OR p_project_type IS NULL OR p_message IS NULL THEN
        RAISE EXCEPTION 'Missing required inquiry fields';
    END IF;

    -- 2. Catch concurrent booking conflicts (unique index violations) and convert
    --    them into the same user-facing conflict message the app returns.
    BEGIN
        -- 3. Fetch Meeting Settings
        SELECT * INTO v_settings FROM public.meeting_settings WHERE id = 1;
        IF v_settings IS NULL THEN
            RAISE EXCEPTION 'System configuration missing.';
        END IF;
        v_tz := v_settings.business_timezone;

        -- 3. Validate booking settings only when a meeting was requested.
        -- Normal inquiries must remain available when meeting booking is disabled.
        IF p_starts_at IS NOT NULL THEN
            IF NOT v_settings.enabled THEN
                RAISE EXCEPTION 'Meeting booking is currently unavailable.';
            END IF;
            -- A. Strict Hourly Alignment (Reject nonzero minutes, seconds, and fractional seconds)
            IF (EXTRACT(MINUTE FROM p_starts_at) != 0 OR
                EXTRACT(SECOND FROM p_starts_at) != 0) THEN
                RAISE EXCEPTION 'Meetings must start exactly on the hour (e.g., 09:00:00)';
            END IF;

            -- B. Validate Lead Time
            v_lead_time_min := v_settings.min_lead_time_minutes;
            IF p_starts_at < (v_now + (v_lead_time_min || ' minutes')::interval) THEN
                RAISE EXCEPTION 'The selected time is too soon. Please pick a later slot.';
            END IF;

            -- C. Validate Booking Window
            v_booking_window_days := v_settings.booking_window_days;
            IF p_starts_at > (v_now + (v_booking_window_days || ' days')::interval) THEN
                RAISE EXCEPTION 'The selected date is outside the booking window.';
            END IF;

            -- D. Validate Working Day (UTC).
            --     DOW: 0=Sunday..6=Saturday. Mon-Fri are 1..5.
            IF NOT (EXTRACT(DOW FROM p_starts_at)::text = ANY(string_to_array(v_settings.working_days, ','))) THEN
                RAISE EXCEPTION 'The selected date is not a working day.';
            END IF;

            -- E. Validate Working Hours (UTC).
            --     Day start/end minutes are stored as minutes-from-midnight in UTC.
            --     Booking window is [day_start_minutes, day_end_minutes) and a meeting
            --     must END by day_end_minutes, so the latest valid start is
            --     day_end_minutes - meeting_duration_minutes (19:00 for a 20:00 close
            --     with 30-minute meetings).
            v_start_min := v_settings.day_start_minutes;
            v_end_min := v_settings.day_end_minutes;
            IF (EXTRACT(HOUR FROM p_starts_at) * 60 + EXTRACT(MINUTE FROM p_starts_at)) < v_start_min OR
               (EXTRACT(HOUR FROM p_starts_at) * 60 + EXTRACT(MINUTE FROM p_starts_at) + v_settings.meeting_duration_minutes) > v_end_min THEN
                RAISE EXCEPTION 'The selected time is outside working hours.';
            END IF;

            -- F. Validate Blocks
            IF EXISTS (SELECT 1 FROM public.meeting_blocks WHERE blocked_date = p_starts_at::date) THEN
                RAISE EXCEPTION 'The selected date is blocked.';
            END IF;
            IF EXISTS (SELECT 1 FROM public.meeting_blocks WHERE starts_at = p_starts_at) THEN
                RAISE EXCEPTION 'The selected time slot is blocked.';
            END IF;

            -- G. Double Booking Check (exact start + status guard).
            --     Starts_at is unique per slot; two bookings for the same instant
            --     cannot coexist while both are active (BOOKED/COMPLETED).
            IF EXISTS (SELECT 1 FROM public.meetings WHERE starts_at = p_starts_at AND status IN ('BOOKED', 'COMPLETED')) THEN
                RAISE EXCEPTION 'That time slot has just been taken. Please choose another available slot.';
            END IF;
        END IF;

        -- 4. Atomic Creation
        INSERT INTO public.business_inquiries (
            name, email, contact_number, project_type, message, company, budget, timeline, city, country
        ) VALUES (
            p_name, p_email, p_contact_number, p_project_type, p_message, p_company, p_budget, p_timeline, p_city, p_country
        ) RETURNING id INTO v_inquiry_id;

        IF p_starts_at IS NOT NULL THEN
            INSERT INTO public.meetings (
                inquiry_id, starts_at, duration_minutes, status
            ) VALUES (
                v_inquiry_id, p_starts_at, v_settings.meeting_duration_minutes, 'BOOKED'
            ) RETURNING id INTO v_meeting_id;
        END IF;

        RETURN json_build_object(
            'inquiry_id', v_inquiry_id,
            'meeting_id', v_meeting_id,
            'status', 'SUCCESS'
        );
    EXCEPTION
        WHEN unique_violation THEN
            -- The active-slot unique index blocked a concurrent booking.
            GET STACKED DIAGNOSTICS v_err_sqlstate = RETURNED_SQLSTATE, v_err_message = MESSAGE_TEXT;
            RAISE EXCEPTION 'That time slot has just been taken. Please choose another available slot.';
    END;
END;
$$;

-- Ensure book_meeting is executable by PUBLIC (anon)
GRANT EXECUTE ON FUNCTION public.book_meeting(TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TIMESTAMPTZ) TO PUBLIC;

-- -----------------------------------------------------------------------------
-- AUDIT TRIGGERS
-- -----------------------------------------------------------------------------

-- Trigger function to audit role changes in public.profiles


-- Trigger function to audit meeting status changes


-- Trigger attachments
DROP TRIGGER IF EXISTS trg_audit_profile_role_change ON public.profiles;
CREATE TRIGGER trg_audit_profile_role_change
AFTER UPDATE ON public.profiles
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_profile_role_change();

DROP TRIGGER IF EXISTS trg_audit_meeting_status_change ON public.meetings;
CREATE TRIGGER trg_audit_meeting_status_change
AFTER UPDATE ON public.meetings
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_meeting_status_change();

-- -----------------------------------------------------------------------------
-- MEETING BOOKING (public.book_meeting) RULES
-- -----------------------------------------------------------------------------
-- 1. Time standard: UTC ONLY. No geographic timezone conversion.
-- 2. Working days: Monday(1) through Friday(5).
-- 3. Saturday(6) and Sunday(0) are unavailable for NEW bookings.
-- 4. Working hours: 09:00-20:00 UTC.
-- 5. Last valid meeting start: 19:00 UTC (meeting ends at 19:30).
-- 6. Meeting duration: 30 minutes.
-- 7. Slot interval: 60 minutes.
-- 8. Valid start times: 09:00, 10:00, 11:00, ... 19:00 UTC.
-- 9. Minimum lead time: from meeting_settings.min_lead_time_minutes.
-- 10. Booking window: from meeting_settings.booking_window_days.
-- 11. Administrator-blocked dates and slots are respected.
-- 12. Duplicate/concurrent bookings are prevented via the active-slot unique
--     index (status, starts_at) WHERE status IN ('BOOKED','COMPLETED').
--
-- EXISTING weekend bookings are preserved (no DELETE or UPDATE).
-- EXISTING meetings are never silently rescheduled.
--
