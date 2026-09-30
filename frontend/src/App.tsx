import { Outlet, useLocation } from "react-router-dom";
import { useEffect, useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { SeoHead } from "@/components/SeoHead";
import { apiUrl } from "@/lib/api";
import { CASE_STUDIES } from "@/data/case-studies";

import type { RouteRecord } from "vite-react-ssg";
import { HomePage } from "@/pages/HomePage";
import { WorkPage } from "@/pages/WorkPage";
import { CaseStudiesPage } from "@/pages/CaseStudiesPage";
import { CaseStudyPage } from "@/pages/CaseStudyPage";
import { ServicesPage } from "@/pages/ServicesPage";
import { ProcessPage } from "@/pages/ProcessPage";
import { AboutPage } from "@/pages/AboutPage";
import { ContactPage } from "@/pages/ContactPage";
import { SystemPage } from "@/pages/SystemPage";
import { AdminLoginPage } from "@/pages/AdminLoginPage";
import { AdminPage } from "@/pages/AdminPage";
import { AdminMeetingsPage } from "@/pages/AdminMeetingsPage";
import { AdminUsersPage } from "@/pages/AdminUsersPage";
import { NotFoundPage } from "@/pages/NotFoundPage";

/** PUBLIC SITE LAYOUT — mirrors the (site) route group: public chrome around every public page. */
function SiteLayout() {
  return (
    <>
      <SeoHead />
      <Navbar />
      <main id="main-content">
        <Outlet />
      </main>
      <Footer />
    </>
  );
}

/**
 * ADMIN AUTH GATE (client-side UX only �.
 * The SPA gates /admin client-side before rendering; the server remains the
 * probes the session and bounces to /admin/login on 401. The authoritative
 * checks remain server-side on every API route (unchanged trust model).
 */
function AdminLayout() {
  const location = useLocation();
  const [state, setState] = useState<"checking" | "ok">("checking");

  useEffect(() => {
    const controller = new AbortController();
    fetch(apiUrl("/api/admin/auth/session"), {
      signal: controller.signal,
      headers: { Accept: "application/json" },
    })
      .then((r) => (r.ok ? setState("ok") : Promise.reject(new Error(String(r.status)))))
      .catch(() => {
        // Redirect to login preserving the intended destination.
        const next = location.pathname === "/admin/login" ? "" : `?next=${encodeURIComponent(location.pathname)}`;
        window.location.replace(`/admin/login${next}`);
      });
    return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (state === "checking") {
    return (
      <main id="main-content" className="grid min-h-screen place-items-center bg-ink text-fog-dark">
        <p className="animate-pulse text-sm tracking-wide">Checking session…</p>
      </main>
    );
  }
  return <Outlet />;
}/**
 * ROUTE CONFIG — plain route objects (NOT a pre-built router): the prerender
 * must import this module in Node (no `document`), so createBrowserRouter can
 * only run in the browser. vite-react-ssg builds its own router per route at
 * build time and on the client.
 */
export const routes: RouteRecord[] = [
  {
    element: <SiteLayout />,
    children: [
      { path: "/", element: <HomePage /> },
      { path: "/work", element: <WorkPage /> },
      { path: "/case-studies", element: <CaseStudiesPage /> },
      {
        path: "/case-studies/:slug",
        element: <CaseStudyPage />,
        // Prerender one static HTML per known slug — routesToPaths expands via this hook.
        getStaticPaths: async () => CASE_STUDIES.map((c) => `/case-studies/${c.slug}`),
      },
      { path: "/services", element: <ServicesPage /> },
      { path: "/process", element: <ProcessPage /> },
      { path: "/about", element: <AboutPage /> },
      { path: "/contact", element: <ContactPage /> },
    ],
  },
  // Noindex client-only routes (excluded from prerender).
  { path: "/system", element: <SystemPage /> },
  { path: "/admin/login", element: <AdminLoginPage /> },
  {
    path: "/admin",
    element: <AdminLayout />,
    children: [
      { index: true, element: <AdminPage /> },
      { path: "meetings", element: <AdminMeetingsPage /> },
      { path: "users", element: <AdminUsersPage /> },
    ],
  },
  { path: "*", element: <NotFoundPage /> },
];
