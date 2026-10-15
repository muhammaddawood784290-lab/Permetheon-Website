-- =============================================================================
-- FILE: supabase_seed.sql
-- PURPOSE: Initial System Data.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. MEETING SETTINGS SINGLETON
-- -----------------------------------------------------------------------------
INSERT INTO public.meeting_settings (
    id, business_timezone, working_days, day_start_minutes, day_end_minutes,
    slot_interval_minutes, meeting_duration_minutes,
    booking_window_days, min_lead_time_minutes, enabled
) VALUES (
    1, 'UTC', '1,2,3,4,5', 540, 1200,
    60, 30,
    30, 120, true
) ON CONFLICT (id) DO NOTHING;
