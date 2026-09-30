import { AdminUsers } from "@/components/admin/AdminUsers";

// The admin portal is private: never indexed, never listed (spec §04/§41).
// Gating is AdminLayout in App.tsx; authority lives on every API route.
export function AdminUsersPage() {
  return (
    <main id="main-content" className="mx-auto min-h-screen w-full max-w-6xl px-4 py-8 md:px-8">
      <header className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="font-display text-xl font-bold tracking-tight text-ink">Admin Accounts</p>
          <p className="mt-1 text-sm text-fog">
            Who can sign in to the admin panel — roles, sessions and access.
          </p>
        </div>
        <a href="/admin" className="text-sm text-fog underline hover:text-ink">
          ← Business inquiries
        </a>
      </header>
      <AdminUsers />
    </main>
  );
}
