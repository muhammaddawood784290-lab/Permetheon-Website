// CLIENT-ONLY ADMIN SHELL GENERATOR.
// Generate an empty React root for admin pages so the homepage's
// prerendered content and hydration data are not reused on admin routes.

import {
  readFileSync,
  writeFileSync,
  existsSync,
} from "node:fs";
import { join } from "node:path";

const dist = "dist";
const shellPath = join(dist, "index.html");

if (!existsSync(shellPath)) {
  throw new Error(`[admin-shell] Missing ${shellPath}. Run the frontend build first.`);
}

let html = readFileSync(shellPath, "utf8");

// Remove prerendered root content and leave an empty client-side root.
html = html.replace(
  /<div id="root"[^>]*>[\s\S]*?<\/div>/i,
  '<div id="root"></div>',
);

// Remove any route-specific hydration payload.
html = html.replace(
  /<script>\s*window\.__staticRouterHydrationData\s*=[\s\S]*?<\/script>/g,
  "",
);

// Generate the shared shell.
const shellPathOut = join(dist, "admin-shell.html");
writeFileSync(shellPathOut, html);

// Replace only the generated admin HTML pages, keeping the original files
// available in Git and leaving unrelated public pages untouched.
for (const page of ["meetings.html", "users.html"]) {
  const pagePath = join(dist, "admin", page);

  if (existsSync(pagePath)) {
    writeFileSync(pagePath, html);
    console.log(`[admin-shell] updated dist/admin/${page}`);
  } else {
    console.warn(`[admin-shell] ${pagePath} not found; skipped`);
  }
}

console.log("[admin-shell] generated client-only admin shells");