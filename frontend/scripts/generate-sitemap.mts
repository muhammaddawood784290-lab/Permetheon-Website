// SITEMAP GENERATOR (build-time only).
// Emits the URL set: 7 static routes + 4 case-study slugs.
// /system, /admin, /api/admin are deliberately absent (robots disallow — Master
// Sec. 22 / spec §41). Run automatically as part of `npm run build` (via the
// vite-react-ssg build chaining `&& npm run sitemap`).
import { writeFileSync } from "node:fs";
import { PAGE_SEO } from "../src/lib/pageSeo.ts";
import { siteUrl } from "../src/lib/seo.ts";

const STATIC_ROUTES = ["/", "/work", "/case-studies", "/services", "/process", "/about", "/contact"];
const caseStudyRoutes = PAGE_SEO.filter((p) => p.path.startsWith("/case-studies/")).map((p) => p.path);

const urls = [...STATIC_ROUTES, ...caseStudyRoutes];

const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls
  .map(
    (route) => `  <url>
    <loc>${siteUrl(route)}</loc>
    <changefreq>${route === "/" ? "weekly" : "monthly"}</changefreq>
    <priority>${route === "/" ? "1.0" : "0.7"}</priority>
  </url>`,
  )
  .join("\n")}
</urlset>
`;

writeFileSync(new URL("../dist/sitemap.xml", import.meta.url), xml);
console.log(`[sitemap] wrote dist/sitemap.xml with ${urls.length} URLs`);
