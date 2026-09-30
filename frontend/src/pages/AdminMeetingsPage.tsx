import { MeetingsCalendar } from "@/components/admin/MeetingsCalendar";

// The admin calendar is private: never indexed, never listed (spec §04/§41).
// Gating is AdminLayout in App.tsx; authority lives on every API route.
export function AdminMeetingsPage() {
  return (
    <main id="main-content" className="mx-auto min-h-screen w-full max-w-6xl px-4 py-8 md:px-8">
      <header className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="font-display text-xl font-bold tracking-tight text-ink">Meetings &amp; Calendar</p>
          <p className="mt-1 text-sm text-fog">Bookings linked to business inquiries — availability, blocks and status.</p>
        </div>
        <div className="flex items-center gap-4 text-sm">
          <a href="/admin" className="text-fog underline hover:text-ink">
            ← Business inquiries
          </a>
          <a href="/admin/users" className="text-fog underline hover:text-ink">
            Admin accounts
          </a>
        </div>
      </header>
      <MeetingsCalendar />
    </main>
  );
}
