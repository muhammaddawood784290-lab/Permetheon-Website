/**
 * PER-ROUTE PAGE META �.
 * The per-page metadata table; consumed
 * only by the prerender (and the sitemap generator). /system, /admin,
 * /admin/login are noindex client-only routes and are intentionally absent.
 */
import type { PageSeo } from "./seo.ts";
import { CASE_STUDIES } from "../data/case-studies.ts";

export const DEFAULT_TITLE = "Permetheon — Digital Products, Websites & Business Systems";

export const PAGE_SEO: PageSeo[] = [
  {
    title: DEFAULT_TITLE,
    description:
      "Permetheon designs and builds websites, web applications, booking platforms and custom business systems for modern businesses.",
    path: "/",
  },
  {
    title: "Work",
    description:
      "Digital products and business systems built by Permetheon — from internal platforms to customer-facing booking systems.",
    path: "/work",
  },
  {
    title: "Case Studies",
    description:
      "How Permetheon turned business problems into working digital products — real projects, real outcomes.",
    path: "/case-studies",
  },
  {
    title: "Services",
    description:
      "Websites, web applications, booking systems, business platforms and custom digital products — designed and built end to end.",
    path: "/services",
  },
  {
    title: "Process",
    description:
      "Six deliberate stages from discovery to deployment — you can see what happens at every step.",
    path: "/process",
  },
  {
    title: "About",
    description:
      "Permetheon builds purpose-built digital products for real business problems — design and engineering working together.",
    path: "/about",
  },
  {
    title: "Contact",
    description:
      "Start the conversation — tell us about the business problem and we'll take it from there.",
    path: "/contact",
  },
  // Case studies: titles come from the project data (Master Sec. 22 verified patterns).
  ...CASE_STUDIES.map<PageSeo>((c) => ({
    title: c.seoTitle,
    description: c.description,
    path: `/case-studies/${c.slug}`,
    ogImage: `/og/${c.slug}.png`,
  })),
];
