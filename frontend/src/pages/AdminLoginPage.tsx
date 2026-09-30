import { useEffect } from "react";
import { AdminLoginForm } from "@/components/admin/AdminLoginForm";

// The admin portal is private: never indexed, never listed (spec §04/§41).
// Client-only route (no prerender) — sets its own document title.
export function AdminLoginPage() {
  useNoindexTitle("Admin Login | Permetheon");
  return <AdminLoginForm />;
}

/** Shared by the noindex client-only routes. */
export function useNoindexTitle(title: string) {
  useEffect(() => {
    document.title = title;
    const meta = document.createElement("meta");
    meta.name = "robots";
    meta.content = "noindex, nofollow";
    document.head.appendChild(meta);
    return () => {
      document.title = "Permetheon";
      meta.remove();
    };
  }, [title]);
}
