-- =============================================================================
-- FILE: supabase_rls.sql (RECONSTRUCTED)
-- PURPOSE: Hardened Row Level Security Policies.
-- =============================================================================

-- Enable RLS on all tables
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.business_inquiries ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inquiry_notes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.meetings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.meeting_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.meeting_blocks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_audit_log ENABLE ROW LEVEL SECURITY;

-- -----------------------------------------------------------------------------
-- 1. PROFILES
-- -----------------------------------------------------------------------------
-- Users can read their own profile
CREATE POLICY "Profiles: users can read own" ON public.profiles
    FOR SELECT USING (auth.uid() = id);

-- SECURITY HARDENING:
-- Direct UPDATE on public.profiles is disabled for authenticated users to prevent
-- role escalation. All updates must go through the controlled RPCs:
-- 1. update_profile_basic_info() for self-service updates.
-- 2. manage_user_role() for administrative role changes.
-- Only SUPER_ADMIN can update profiles via direct SQL/Dashboard.
CREATE POLICY "Profiles: super_admin can update all" ON public.profiles
    FOR UPDATE USING (
        public.get_current_user_role() = 'SUPER_ADMIN'
    );

-- -----------------------------------------------------------------------------
-- 2. BUSINESS INQUIRIES
-- -----------------------------------------------------------------------------
-- Public can create inquiries
CREATE POLICY "Inquiries: public can insert" ON public.business_inquiries
    FOR INSERT WITH CHECK (true);

-- Only authenticated admins can read inquiries
CREATE POLICY "Inquiries: admins can read" ON public.business_inquiries
    FOR SELECT USING (
        public.get_current_user_role() IS NOT NULL
    );

-- Admins can update inquiries (status, priority)
CREATE POLICY "Inquiries: admins can update" ON public.business_inquiries
    FOR UPDATE USING (
        public.get_current_user_role() IS NOT NULL
    );

-- -----------------------------------------------------------------------------
-- 3. INQUIRY NOTES
-- -----------------------------------------------------------------------------
-- Only authenticated admins can read notes
CREATE POLICY "Notes: admins can read" ON public.inquiry_notes
    FOR SELECT USING (
        public.get_current_user_role() IS NOT NULL
    );

-- Only authenticated admins can create notes
CREATE POLICY "Notes: admins can insert" ON public.inquiry_notes
    FOR INSERT WITH CHECK (
        public.get_current_user_role() IS NOT NULL
    );

-- -----------------------------------------------------------------------------
-- 4. MEETINGS
-- -----------------------------------------------------------------------------
-- Only authenticated admins can read meetings
CREATE POLICY "Meetings: admins can read" ON public.meetings
    FOR SELECT USING (
        public.get_current_user_role() IS NOT NULL
    );

-- Direct INSERT is disabled (must use book_meeting RPC)
-- No policies for INSERT.

-- Only authenticated admins can update meeting status
CREATE POLICY "Meetings: admins can update" ON public.meetings
    FOR UPDATE USING (
        public.get_current_user_role() IS NOT NULL
    );

-- -----------------------------------------------------------------------------
-- 5. MEETING SETTINGS
-- -----------------------------------------------------------------------------
-- Public can read settings (to render calendar)
CREATE POLICY "Settings: public can read" ON public.meeting_settings
    FOR SELECT USING (true);

-- Only SUPER_ADMIN can update settings
CREATE POLICY "Settings: super_admin can update" ON public.meeting_settings
    FOR UPDATE USING (
        public.get_current_user_role() = 'SUPER_ADMIN'
    );

-- -----------------------------------------------------------------------------
-- 6. MEETING BLOCKS
-- -----------------------------------------------------------------------------
-- Public can read blocks
CREATE POLICY "Blocks: public can read" ON public.meeting_blocks
    FOR SELECT USING (true);

-- Only SUPER_ADMIN and MANAGER can manage blocks
CREATE POLICY "Blocks: managers can manage" ON public.meeting_blocks
    FOR ALL USING (
        public.get_current_user_role() IN ('SUPER_ADMIN', 'MANAGER')
    );

-- -----------------------------------------------------------------------------
-- 7. ADMIN AUDIT LOG
-- -----------------------------------------------------------------------------
-- Only authenticated admins can insert audit logs (usually via trigger)
CREATE POLICY "Audit: admins can insert" ON public.admin_audit_log
    FOR INSERT WITH CHECK (
        public.get_current_user_role() IS NOT NULL
    );

-- Only SUPER_ADMIN can read the audit log
CREATE POLICY "Audit: super_admin can read" ON public.admin_audit_log
    FOR SELECT USING (
        public.get_current_user_role() = 'SUPER_ADMIN'
    );

-- Prevent any updates or deletes to the audit log
CREATE POLICY "Audit: no one can update" ON public.admin_audit_log
    FOR UPDATE USING (false);

CREATE POLICY "Audit: no one can delete" ON public.admin_audit_log
    FOR DELETE USING (false);
