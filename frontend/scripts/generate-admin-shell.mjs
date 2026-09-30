// CLIENT-ONLY SHELL GENERATOR (postbuild).
// /admin and /system are NOT prerendered (noindex, client-only). Serving them
// the prerendered index.html shell causes React #418: the baked root content +
// __staticRouterHydrationData belong to the homepage route, not the client
// route. This script emits admin-shell.html: same head/assets, EMPTY root,
// no hydration data — the Laravel SPA host serves it for client-only routes.
import { readFileSync, writeFileSync } from "node:fs";

let html = readFileSync("dist/index.html", "utf8");
html = html.replace(
  /<div id="root" data-server-rendered="true">[\s\S]*?<\/div>\s*(?=<script|$)/i,
  '<div id="root"></div>\n',
);
html = html.replace(
  /<script>window\.__staticRouterHydrationData[\s\S]*?<\/script>/g,
  "",
);
writeFileSync("dist/admin-shell.html", html);
console.log(
  `[admin-shell] wrote dist/admin-shell.html (${html.length} bytes, empty root, no hydration data)`,
);
