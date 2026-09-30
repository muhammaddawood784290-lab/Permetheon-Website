import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath } from "node:url";

/**
 * VITE CONFIG �.
 * `vite-react-ssg build` prerenders every route in the router config
 * (7 static pages + 4 case-study pages). /system, /admin, /admin/login are
 * excluded via `includedRoutes` (client-only, noindex). The `@/*` alias
 * matches the TypeScript path mapping in tsconfig.json.
 *
 * DEV API PROXY — the dev server forwards /api/* to the Laravel backend
 * (php artisan serve, default http://localhost:8000 — override with
 * LARAVEL_API_URL). This keeps the browser SAME-ORIGIN in dev exactly as in
 * production (Laravel serves the built SPA), so admin cookies (SameSite=Strict),
 * CSRF double-submit and the login Origin-vs-Host check all work unchanged —
 * no CORS surface and no per-request credentials hacks (map §2B-11 resolved
 * this way instead of dev-only CORS).
 */
const laravelTarget = process.env.LARAVEL_API_URL ?? "http://localhost:8000";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  server: {
    proxy: {
      "/api": {
        target: laravelTarget,
        changeOrigin: true,
      },
    },
  },
  ssgOptions: {
    script: "async",
    // `prettify` runs prettier over the baked HTML, which reflows whitespace
    // between elements and breaks React 19 hydration (#418) — the injected
    // "\n      " text nodes don't exist in the client render. Formatting must
    // stay OFF (verified via the dev-mode hydration diff).
    formatting: "none",
    onBeforePageRender(path, html) {
      // Strip the index.html fallback <title>/description for PRERENDERED
      // routes: Helmet bakes the real per-route tags, and leaving the fallback
      // would produce two <title>s and two descriptions per page (SEO bug).
      // The fallback stays in index.html for the client-only noindex routes
      // (/system, /admin*) which are served the raw index.html.
      //
      // LINE ENDINGS: the trailing \n must tolerate \r\n — a Windows checkout
      // (core.autocrlf=true, no .gitattributes) materializes index.html with
      // CRLF, and a bare-\n regex silently fails to strip, breaking the
      // prerender verification gate (two <title>s per page).
      return html
        .replace(/[\t ]*<!--[\s\S]*?Title\/description\/canonical\/OG are baked[\s\S]*?-->\r?\n/g, "")
        .replace(/[\t ]*<title>[\s\S]*?<\/title>\r?\n/g, "")
        .replace(/[\t ]*<meta\s+name="description"[\s\S]*?\/>\r?\n/g, "");
    },
    includedRoutes(paths) {
      // Must return PATH STRINGS (the lib filters them again). Drop the
      // client-only noindex routes from prerendering.
      const excluded = ["/system", "/admin", "/admin/login"];
      return paths.filter((p) => !excluded.includes(p));
    },
  },
});
