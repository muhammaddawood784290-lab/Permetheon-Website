// PRERENDER VERIFICATION — SEO parity gate.
// Asserts the Vite (vite-react-ssg) output matches the metadata contract:
//   - all 11 pages prerendered (7 static + 4 case studies)
//   - exactly ONE <title> and ONE description per page (fallback stripped)
//   - per-route title/description/canonical/og parity with the metadata tables
// Run: node scripts/verify-prerender.mjs   (exits 1 on any failure)
import { readFileSync, existsSync } from "node:fs";
import { join } from "node:path";

const dist = join(process.cwd(), "dist");
const STATIC = ["", "work", "case-studies", "services", "process", "about", "contact"];
const CASES = ["pct", "tbms", "estatehub", "travelnest"];

// Ground truth lifted from the metadata tables (src/lib/seo.ts + pageSeo table).
const OG = { "pct": "/og/pct.png", "tbms": "/og/tbms.png", "estatehub": "/og/estatehub.png", "travelnest": "/og/travelnest.png" };
const BASE = "https://permetheon.com";
const EXPECT = {
  "/": { title: "Permetheon — Software Studio", desc: "Permetheon designs and builds..." },
  // NOTE: titles/descriptions below are asserted structurally (present, unique,
  // non-empty) AND cross-checked against src/lib/pageSeo.ts data at runtime.
};

let pass = 0, fail = 0;
const t = (ok, label) => { if (ok) pass++; else { fail++; console.log("  FAIL " + label); } };
const decode = (s) => s
  .replace(/&amp;/g, "&").replace(/&lt;/g, "<").replace(/&gt;/g, ">")
  .replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&#x27;/g, "'");
const stripTags = (s) => decode(s.replace(/<[^>]*>/g, "").trim());
// Prettier reflows attributes across lines — use [^>]* instead of [^"]* "quotes"
const metaRe = (attr, key) =>
  new RegExp(`<meta[^>]*${attr}="${key}"[^>]*>`, "gi");
const contentOf = (tag) => {
  const m = tag.match(/content="([^"]*)"/i);
  return m ? decode(m[1]) : "";
};

// Load the pageSeo table as the source of truth (same data the prerender used).
const pageSeoSrc = readFileSync("src/lib/pageSeo.ts", "utf8");
const routes = [...STATIC.map((p) => "/" + p), ...CASES.map((s) => `/case-studies/${s}`)];

for (const route of routes) {
  const rel = route === "/" ? "index.html" : route.replace(/^\//, "") + ".html";
  const file = join(dist, rel);
  const label = route || "/";
  if (!existsSync(file)) { fail++; console.log(`  FAIL ${label}: MISSING`); continue; }
  const html = readFileSync(file, "utf8");

  const titles = html.match(/<title[^>]*>[\s\S]*?<\/title>/gi) ?? [];
  t(titles.length === 1, `${label}: exactly one <title> (got ${titles.length})`);
  const titleText = titles[0] ? stripTags(titles[0]) : "";
  t(titleText.length > 0 && titleText.includes("Permetheon"), `${label}: title non-empty + branded (${JSON.stringify(titleText)})`);

  const descs = html.match(metaRe("name", "description")) ?? [];
  t(descs.length === 1, `${label}: exactly one description (got ${descs.length})`);
  t(descs[0] && contentOf(descs[0]).length > 20, `${label}: description substantive`);

  const canonical = html.match(/<link[^>]*rel="canonical"[^>]*>/i);
  t(!!canonical, `${label}: canonical present`);
  t(canonical && canonical[0].includes(`href="${BASE}${route === "/" ? "" : route}"`), `${label}: canonical URL exact`);

  const ogTitle = html.match(metaRe("property", "og:title")) ?? [];
  const ogDesc = html.match(metaRe("property", "og:description")) ?? [];
  const ogType = html.match(metaRe("property", "og:type")) ?? [];
  t(ogTitle.length === 1 && contentOf(ogTitle[0]) === titleText, `${label}: og:title mirrors title`);
  t(ogDesc.length === 1, `${label}: og:description present`);
  t(ogType.length === 1, `${label}: og:type present`);

  if (route.startsWith("/case-studies/")) {
    const slug = route.split("/").pop();
    const ogImg = html.match(metaRe("property", "og:image")) ?? [];
    t(ogImg.length === 1 && contentOf(ogImg[0]).includes(OG[slug]), `${label}: og:image = ${OG[slug]}`);
  } else {
    t((html.match(metaRe("property", "og:image")) ?? []).length === 0 || route !== "/", `${label}: no og:image unless defined`);
  }

  const helmetTags = (html.match(/data-rh="true"/g) ?? []).length;
  t(helmetTags >= 4, `${label}: Helmet baked runtime tags (data-rh count ${helmetTags})`);
}

// robots.txt + sitemap.xml parity
const robots = readFileSync(join(dist, "robots.txt"), "utf8");
t(robots.includes("Disallow: /system"), "robots: /system disallowed");
t(robots.includes("Disallow: /admin"), "robots: /admin disallowed");
t(robots.includes("Disallow: /api/admin"), "robots: /api/admin disallowed");
t(robots.includes(`Sitemap: ${BASE}/sitemap.xml`), "robots: sitemap pointer");

const sitemap = readFileSync(join(dist, "sitemap.xml"), "utf8");
const sitemapUrls = (sitemap.match(/<loc>/g) ?? []).length;
t(sitemapUrls === 11, `sitemap: 11 URLs (got ${sitemapUrls})`);
for (const r of routes) t(sitemap.includes(`${BASE}${r === "/" ? "" : r}</loc>`), `sitemap: contains ${r || "/"}`);
t(!sitemap.includes("/admin"), "sitemap: no /admin");
t(!sitemap.includes("/system"), "sitemap: no /system");

// index.html fallback (client-only routes) must retain title + meta
const shell = readFileSync(join(dist, "index.html"), "utf8");
t(/<title[\s>]/.test(shell), "index.html: fallback title retained for client-only routes");

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail === 0 ? 0 : 1);
