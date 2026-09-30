import { Head } from "vite-react-ssg";
import { useLocation } from "react-router-dom";
import { SITE_NAME, SITE_URL, pageTitle, type PageSeo } from "@/lib/seo";
import { PAGE_SEO } from "@/lib/pageSeo";

/**
 * SEO HEAD �.
 * Renders <title>/description/canonical/OG as Helmet ELEMENTS via
 * vite-react-ssg's <Head> (react-helmet-async — it only accepts React
 * children, not HTML strings), so the prerender BAKES the tags into the
 * static HTML for every indexed route — the canonical tag set defined in
 * src/lib/seo.ts pageMetadata().
 *
 * Noindex client-only routes (/system, /admin*) are not prerendered and set
 * document.title themselves; this component renders nothing for them.
 */
export function SeoHead() {
  const { pathname } = useLocation();
  // The homepage route is ""; normalize trailing variations.
  const normalized = pathname === "" || pathname === "/" ? "/" : pathname.replace(/\/$/, "");
  const seo = PAGE_SEO.find((p) => p.path === normalized);

  if (!seo) return null; // noindex/client-only route — no baked head

  return <Head>{headElements(seo)}</Head>;
}

/** The canonical per-route metadata tag set, rendered as Helmet elements. */
function headElements({ title, description, path, ogImage }: PageSeo) {
  const url = `${SITE_URL}${path === "/" ? "" : path}`;
  const og = ogImage ? `${SITE_URL}${ogImage}` : undefined;
  const fullTitle = pageTitle(title);

  return (
    <>
      <title>{fullTitle}</title>
      <meta name="description" content={description} />
      <link rel="canonical" href={url} />
      <meta property="og:title" content={fullTitle} />
      <meta property="og:description" content={description} />
      <meta property="og:url" content={url} />
      <meta property="og:site_name" content={SITE_NAME} />
      <meta property="og:type" content="website" />
      <meta property="og:locale" content="en_US" />
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      {/* NB: conditional tags stay FLAT in this fragment — a nested
          <>{og ? <><meta/></> : null}</> gets silently dropped by
          react-helmet-async 1.3.0's fragment recursion (verified: og:image
          vanished from the baked HTML while flat siblings rendered). */}
      {og ? <meta property="og:image" content={og} /> : null}
      {og ? <meta name="twitter:image" content={og} /> : null}
    </>
  );
}
