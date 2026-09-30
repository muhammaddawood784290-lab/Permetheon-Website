import { AdminDashboard } from "@/components/admin/AdminDashboard";

// The admin portal is private: never indexed, never listed (spec §04/§41).
// Server-side gating lives on every API route; the client-side session gate
// is AdminLayout in App.tsx.
export function AdminPage() {
  return <AdminDashboard />;
}
