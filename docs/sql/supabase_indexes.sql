-- =============================================================================
-- FILE: supabase_indexes.sql
-- PURPOSE: Performance and Integrity Indexes.
-- =============================================================================

-- Inquiries
CREATE INDEX IF NOT EXISTS idx_inquiries_status ON public.business_inquiries(status);
CREATE INDEX IF NOT EXISTS idx_inquiries_created_at ON public.business_inquiries(created_at);

-- Notes
CREATE INDEX IF NOT EXISTS idx_notes_inquiry_created ON public.inquiry_notes(inquiry_id, created_at);

-- Blocks
CREATE INDEX IF NOT EXISTS idx_blocks_date ON public.meeting_blocks(blocked_date);
CREATE INDEX IF NOT EXISTS idx_blocks_starts_at ON public.meeting_blocks(starts_at);

-- Meetings
CREATE INDEX IF NOT EXISTS idx_meetings_starts_at ON public.meetings(starts_at);
CREATE INDEX IF NOT EXISTS idx_meetings_inquiry ON public.meetings(inquiry_id);

-- Audit Log
CREATE INDEX IF NOT EXISTS idx_audit_admin ON public.admin_audit_log(admin_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON public.admin_audit_log(action);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON public.admin_audit_log(created_at);

-- -----------------------------------------------------------------------------
-- DOUBLE-BOOKING GUARD
-- -----------------------------------------------------------------------------
-- Requirements:
-- 1. BOOKED and COMPLETED meetings occupy the slot.
-- 2. CANCELLED and NO_SHOW do not occupy the slot.
-- 3. Slots are based on starts_at.
-- -----------------------------------------------------------------------------
CREATE UNIQUE INDEX IF NOT EXISTS uq_meetings_active_slot
ON public.meetings (starts_at)
WHERE (status IN ('BOOKED', 'COMPLETED'));
