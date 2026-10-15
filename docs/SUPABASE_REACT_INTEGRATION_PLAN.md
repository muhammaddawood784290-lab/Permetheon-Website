# Supabase React Integration Plan

## 1. Required Packages
```bash
npm install @supabase/supabase-js
```

## 2. File Changes & New Files
### New Files
- `frontend/src/lib/supabase.ts`: Singleton client initialization.
- `frontend/src/hooks/useAuth.ts`: Auth state management hook.
- `frontend/src/hooks/useInquiries.ts`: Data access for business inquiries.
- `frontend/src/hooks/useMeetings.ts`: Data access for calendar/booking.

### Modified Files
- `frontend/src/lib/api.ts`: Deprecated in favor of `supabase.ts`.
- `frontend/src/App.tsx`: Integration of Auth provider and route protection.
- `frontend/src/pages/AdminLoginPage.tsx`: Updated to use `supabase.auth.signInWithPassword`.
- `frontend/src/components/InquiryForm.tsx`: Updated to use `supabase.from('business_inquiries').insert()`.
- `frontend/src/components/MeetingBookingSection.tsx`: Updated to use RPC `book_meeting`.
- `frontend/src/pages/AdminPage.tsx`: Updated to fetch data via Supabase client.

## 3. Environment Variables
Add to `.env.local` / `.env.production`:
- `VITE_SUPABASE_URL`: Your Supabase project URL.
- `VITE_SUPABASE_PUBLISHABLE_KEY`: Your Supabase anonymous public key.

## 4. Supabase Client Setup
```typescript
// frontend/src/lib/supabase.ts
import { createClient } from '@supabase/supabase-js';

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY;

export const supabase = createClient(supabaseUrl, supabaseAnonKey);
```

## 5. React Integration Pattern
- **Provider Pattern:** Wrap the app in an `AuthProvider` to manage the `User` object.
- **Custom Hooks:** Encapsulate Supabase logic in hooks (e.g., `useInquiries`) to separate UI from data access.
- **Loading/Error States:** Use local state (`loading`, `error`) within hooks to drive UI feedback.

## 6. Data Access Patterns
### Public Inquiry Submission
```typescript
const { data, error } = await supabase
  .from('business_inquiries')
  .insert([{ ...payload }])
  .select();
```

### Atomic Booking (via RPC)
```typescript
const { data, error } = await supabase.rpc('book_meeting', {
  inquiry_payload: { ... },
  starts_at: '2026-10-01T10:00:00Z',
});
```

### Admin Data Fetching
```typescript
const { data, error } = await supabase
  .from('business_inquiries')
  .select('*, inquiry_notes(*)')
  .order('created_at', { ascending: false });
```

## 7. Authentication & Admin Access
- **Auth Flow:** `supabase.auth.signInWithPassword` $\rightarrow$ session stored in LocalStorage.
- **Authorization:**
    1. Client checks `user.role` from `public.profiles`.
    2. Server enforces access via RLS based on the authenticated JWT.
- **Admin Guard:** High-order component (HOC) or Wrapper that redirects non-authenticated users to `/admin/login`.

## 8. RLS Requirements
- **Public:** `INSERT` only for `business_inquiries`.
- **Authenticated Admins:** `SELECT`, `UPDATE` for inquiries; `ALL` for notes.
- **Super Admins:** `ALL` for `meeting_settings` and `profiles`.

## 9. Error Handling
- **Supabase Errors:** Handle `PostgrestError` (e.g., code `23505` for unique violation).
- **User Feedback:** Map DB error codes to user-friendly messages (e.g., "Slot already taken").

## 10. Testing Strategy
- **Integration Tests:** Verify the flow from `InquiryForm` $\rightarrow$ Supabase $\rightarrow$ Admin Dashboard.
- **Auth Tests:** Verify that an unauthenticated user cannot access `/admin`.
- **Concurrency Tests:** Test simultaneous bookings in the browser.
