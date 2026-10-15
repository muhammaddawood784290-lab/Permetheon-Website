# Supabase SQL Implementation Guide

This directory contains the SQL artifacts required to initialize the Supabase Cloud database for the Permetheon Website V3 project.

## SQL Artifacts

| File | Purpose | Execution Order |
| :--- | :--- | :--- |
| `supabase_schema.sql` | Base tables, enums, and core constraints. | 1 |
| `supabase_indexes.sql` | Performance indexes and double-booking guard. | 2 |
| `supabase_functions.sql` | RPC functions for protected logic (`book_meeting`). | 3 |
| `supabase_rls.sql` | Row Level Security policies. | 4 |
| `supabase_seed.sql` | Initial system settings. | 5 |

## Execution Procedure

1. **Open Supabase Dashboard** $\rightarrow$ **SQL Editor**.
2. Execute the files in the order specified above.
3. Verify each step using the provided verification queries in the migration plan.

## Verification Checklist

- [ ] `public.profiles` table exists and links to `auth.users`.
- [ ] `business_inquiries` requires `contact_number` with E.164 format.
- [ ] `uq_meetings_active_slot` index is created on `meetings`.
- [ ] `book_meeting` RPC function is callable and atomic.
- [ ] RLS prevents public users from reading inquiries/notes.
- [ ] `meeting_settings` singleton row is present.

## WARNING
These files are **DESIGN ARTIFACTS**. They have **NOT** been executed against the Supabase Cloud environment. Review carefully before running.
