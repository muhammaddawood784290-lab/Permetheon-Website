# Supabase Cloud Migration Plan

## 1. Target Architecture
The application is migrating from a Laravel-centric backend to a **Client-Side First** architecture using Supabase.

- **Frontend:** React 19 + TypeScript + Vite
- **Integration:** `@supabase/supabase-js` (Direct Client-to-DB communication)
- **Backend:** Supabase Cloud (PostgreSQL, Auth, RLS)
- **Pattern:** The React frontend communicates directly with the Supabase API. Business logic is moved to PostgreSQL (via RLS, Functions, and Constraints).

## 2. React/Vite Integration
- **Client:** Singleton Supabase client using `VITE_SUPABASE_URL` and `VITE_SUPABASE_PUBLISHABLE_KEY`.
- **State Management:** React hooks for managing auth state and data fetching.
- **API Layer:** Direct calls to `supabase.from('table')` replacing Laravel API endpoints.

## 3. Supabase Services Used
- **PostgreSQL:** Canonical data store.
- **Supabase Auth:** Admin authentication and session management.
- **RLS (Row Level Security):** The primary security mechanism replacing Laravel's Middleware and Policy classes.
- **Realtime:** For admin notifications of new inquiries.

## 4. Existing Laravel Responsibilities
Laravel is retained as a **read-only reference implementation** for:
- **Validation Logic:** E.164 and field length requirements.
- **Calendar Rules:** Slot intervals and duration logic.
- **Permission Model:** Role hierarchy (`SUPER_ADMIN` > `MANAGER` > `ADMIN`).
- **Audit Logic:** Append-only semantics for admin logs.

## 5. Responsibilities Moving to Supabase
| Laravel Responsibility | Supabase Implementation |
| :--- | :--- |
| Admin Authentication | Supabase Auth $\rightarrow$ `auth.users` |
| Admin Profiles/Roles | `public.profiles` table linked to `auth.uid()` |
| Inquiry Validation | PostgreSQL CHECK constraints + Frontend Validation |
| Double-Booking Guard | Partial Unique Index on `meetings` |
| Atomic Booking | PostgreSQL RPC function `book_meeting` |
| Audit Logging | RLS (Insert-only) + Database Triggers |

## 6. PostgreSQL Schema
The schema is ported to native PostgreSQL. **Laravel migrations are used as the source of truth for design only; they are NOT executed against Supabase.**
- **Admin Auth:** `admin_users` and `admin_sessions` are **REPLACED** by Supabase Auth and `public.profiles`.
- **Profiles:** A new `public.profiles` table stores `role` and `name`.
- **Tables:** `business_inquiries`, `meetings`, `meeting_settings`, `meeting_blocks`, `admin_audit_log`.
- **Contact Number:** `contact_number` is **REQUIRED**. Constraint: `contact_number ~ '^\\+[1-9]\\d{1,14}$'`. No empty strings allowed.
- **Slot Model:**
    - `slot_interval_minutes` (60): The grid increment for availability generation.
    - `meeting_duration_minutes` (30): The actual length of a booked meeting.

## 7. RLS Strategy
Default Policy: **DENY ALL**.

| Table | Policy | Condition |
| :--- | :--- | :--- |
| `profiles` | SELECT | `auth.uid() = id` |
| `profiles` | UPDATE | `(SELECT role FROM profiles WHERE id = auth.uid()) = 'SUPER_ADMIN'` |
| `business_inquiries` | INSERT | Public (Anon) |
| `business_inquiries` | SELECT | `(SELECT role FROM profiles WHERE id = auth.uid()) IS NOT NULL` |
| `inquiry_notes` | SELECT | `(SELECT role FROM profiles WHERE id = auth.uid()) IS NOT NULL` |
| `inquiry_notes` | INSERT | `(SELECT role FROM profiles WHERE id = auth.uid()) IS NOT NULL` |
| `meetings` | SELECT | `(SELECT role FROM profiles WHERE id = auth.uid()) IS NOT NULL` |
| `meetings` | INSERT | **Disabled** (Must use RPC `book_meeting` for atomicity) |
| `meeting_settings` | SELECT | Public (Anon) |
| `meeting_settings` | UPDATE | `(SELECT role FROM profiles WHERE id = auth.uid()) = 'SUPER_ADMIN'` |
| `meeting_blocks` | SELECT | Public (Anon) |
| `meeting_blocks` | ALL | `(SELECT role FROM profiles WHERE id = auth.uid()) IN ('SUPER_ADMIN', 'MANAGER')` |
| `admin_audit_log` | INSERT | `(SELECT role FROM profiles WHERE id = auth.uid()) IS NOT NULL` |
| `admin_audit_log` | SELECT | `(SELECT role FROM profiles WHERE id = auth.uid()) = 'SUPER_ADMIN'` |

## 8. Authentication Strategy
- **Provider:** Supabase Auth.
- **Role Mapping:** Upon sign-up/migration, users are assigned a role in `public.profiles`.
- **Privilege Escalation:** RLS prevents any user (including admins) from updating their own `role` column. Only `SUPER_ADMIN` can update roles.

## 9. Admin Authorization Strategy
- **Roles:** `SUPER_ADMIN`, `MANAGER`, `ADMIN`.
- **Enforcement:** RLS checks the `role` column in `public.profiles` using the `auth.uid()` of the JWT.

## 10. Inquiry Architecture
- **Submission:** Direct `INSERT` to `business_inquiries`.
- **Validation:**
    - DB-level CHECK constraints for E.164 and length.
    - Required fields: `name`, `email`, `contact_number`, `project_type`, `message`.

## 11. Meeting & Calendar Architecture
- **Storage:** UTC ISO 8601 timestamps.
- **Grid:** 60-minute intervals for availability generation.
- **Duration:** 30-minute duration for booked meetings.
- **Flow:**
    1. React fetches `meeting_settings` and `meetings` (active).
    2. React computes available 60-min slots.
    3. User books $\rightarrow$ RPC `book_meeting`.

## 12. Availability & Double-Booking Protection
- **Constraint:** Partial Unique Index:
  `CREATE UNIQUE INDEX uq_meetings_active_slot ON meetings (starts_at) WHERE (status IN ('BOOKED', 'COMPLETED'));`
- **Behavior:**
    - `BOOKED` / `COMPLETED`: Conflict.
    - `CANCELLED` / `NO_SHOW`: No conflict (slot is freed).

## 13. City/Country Timezone Architecture
- **Resolution:**
    - **Source:** Client provides City + Country.
    - **Resolver:** Deterministic resolution using the `countries-and-timezones` dataset.
    - **Location:** Resolution happens in the **React Frontend** to determine the IANA timezone string.
- **Standard:**
    - Storage: UTC.
    - API: UTC ISO 8601.
    - Display: local AM/PM via IANA timezone.
    - DST: Handled by IANA-aware date libraries (e.g., `date-fns-tz`).

## 14. Storage Strategy
- **Current:** Not required.

## 15. Realtime Strategy
- **Usage:** Admin Dashboard "New Inquiry" alerts via `postgres_changes` on `business_inquiries`.

## 16. Edge Function Requirements
- **Minimalism:** Most logic is in RLS and RPC.
- **Potential Use:** Third-party email triggers (SendGrid/Postmark) if Supabase Auth emails are insufficient.

## 17. Frontend Environment Variables
- `VITE_SUPABASE_URL`
- `VITE_SUPABASE_PUBLISHABLE_KEY`

## 18. Secret Management
- **Frontend:** Only the publishable anon key is used.
- **Privileged Ops:** Handled via `SECURITY DEFINER` PostgreSQL functions to safely use `service_role` privileges without exposing keys.

## 19. Migration Strategy
The migration follows a strictly non-destructive flow:
**Laravel Migration Inspection $\rightarrow$ SQL Artifact Generation $\rightarrow$ Review $\rightarrow$ Supabase SQL Editor $\rightarrow$ Verification.**

Required SQL artifacts:
1. `docs/sql/supabase_schema.sql` (Tables, Types, Constraints)
2. `docs/sql/supabase_indexes.sql` (Partial Unique Indexes)
3. `docs/sql/supabase_functions.sql` (RPCs like `book_meeting`)
4. `docs/sql/supabase_rls.sql` (Row Level Security Policies)
5. `docs/sql/supabase_seed.sql` (Singleton settings, initial admin)

## 20. Testing Strategy
- **RLS Validation:** Test as `Anon` vs `Admin` roles in Supabase Dashboard.
- **Atomicity Test:** Trigger `book_meeting` with a conflicting slot to verify rollback of the inquiry.
- **DST Test:** Verify booking across a DST transition window using the IANA resolver.

## 21. Rollback/Recovery Strategy
- **No Destructive Rollbacks:** `DROP TABLE` is forbidden on populated databases.
- **Versioning:** Every change is captured in a SQL migration file.
- **Recovery:**
    1. Point-in-time recovery (PITR) via Supabase backups.
    2. Corrective "Up" migrations to fix schema errors.

## 22. Deployment Strategy
- **Database:** Apply SQL artifacts to Supabase Cloud.
- **Frontend:** Deploy React build to static hosting.

## 23. Security Risks
- **Public Writes:** RLS must strictly limit `business_inquiries` to `INSERT` only.
- **Role Escalation:** Profiles table must be protected from self-updates.

## 24. Open Decisions
- **Admin User Migration:** Strategy for moving existing hashes to Supabase Auth (likely a manual reset or CSV import).

## 25. Final Readiness Checklist
- [ ] SQL artifacts produced and reviewed.
- [ ] `book_meeting` RPC logic verified.
- [ ] RLS policies mapped to role hierarchy.
- [ ] E.164 constraints defined in SQL.
- [ ] Timezone resolution dataset selected.
