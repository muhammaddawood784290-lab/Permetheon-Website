/**
 * PERMETHEON — SEO SOURCE.
 * Port of src/lib/seo.ts. The page-meta table is consumed ONLY by the
 * build-time prerender (vite-react-ssg bakes <title>/canonical/OG into the
 * static HTML). Indexed pages do NO runtime head manipulation; the two
 * noindex client-only routes (/admin, /system) set document.title in a tiny
 * effect (see useNoindexTitle).
 *
 * D-011 (OPEN, Business Owner): the production canonical domain is not defined in
 * the Master Document; permetheon.com is the placeholder. Every absolute URL in the
 * site derives from SITE_URL — swapping the domain is a one-line change.
 */
export const SITE_URL = "https://permetheon.com";
export const SITE_NAME = "Permetheon";

/** One source for every absolute URL (canonicals, sitemap, robots). */
export function siteUrl(path = "/"): string {
  return `${SITE_URL}${path === "/" ? "" : path}`;
}

export type PageSeo = {
  /** Final <title> — homepage uses the default title (no suffix); others get " | Permetheon". */
  title: string;
  description: string;
  /** Route path, also the canonical path. */
  path: string;
  /** OG/Twitter image path under /og/ (static PNGs, see §1C). */
  ogImage?: string;
};

/**
 * Compose the final title using the `%s | Permetheon` template.
 * The homepage passes its full default title and is returned untouched.
 */
export function pageTitle(raw: string): string {
  return raw === `${SITE_NAME} — Digital Products, Websites & Business Systems`
    ? raw
    : `${raw} | ${SITE_NAME}`;
}

/**
 * NOTE: the per-route tag rendering lives in components/SeoHead.tsx as Helmet
 * JSX — vite-react-ssg's <Head> only accepts React element children, so an
 * HTML-string helper silently baked nothing (verified: metaAttributes empty).
 * The SITEMAP generator consumes PAGE_SEO + siteUrl() directly.
 */
