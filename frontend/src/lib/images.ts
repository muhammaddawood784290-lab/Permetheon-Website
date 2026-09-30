// RESPONSIVE IMAGE HELPERS — pairs every screenshot with its generated WebP
// rungs (scripts/generate-image-variants.mjs) and the layout slot it appears in.
// PNG originals stay as the <img src> fallback; modern browsers pick WebP rungs.
import { SCREENSHOT_WIDTHS } from "./screenshot-widths";

const RUNGS = [480, 960, 1440];

/** `/screenshots/tbms/customer-home.png` -> `/screenshots/tbms/customer-home` */
const stem = (url: string) => url.replace(/\.png$/i, "");

/**
 * srcSet for a screenshot URL: WebP rungs 480/960/1440 + the original-width
 * rung with a correct width descriptor (from the generated manifest).
 */
export function screenshotSrcSet(url: string): string | undefined {
  const width = SCREENSHOT_WIDTHS[url];
  if (!width) return undefined; // unknown asset — caller keeps a plain <img>
  const parts = RUNGS.filter((w) => w < width).map(
    (w) => `${stem(url)}.w${w}.webp ${w}w`
  );
  parts.push(`${stem(url)}.webp ${width}w`);
  return parts.join(", ");
}

/** The original-size WebP rung — for JS-side neighbor preloading. */
export function screenshotOriginalWebp(url: string): string | undefined {
  return SCREENSHOT_WIDTHS[url] ? `${stem(url)}.webp` : undefined;
}

/** `sizes` for the wide, ~full-width showcase slots (carousel / hero). */
export const WIDE_SIZES =
  "(max-width: 1279px) 100vw, (min-width: 1280px) 1216px";

/** `sizes` for ImageFrame slots: half-width card grids and split hero figures. */
export const CARD_SIZES =
  "(min-width: 1024px) 620px, (min-width: 768px) 50vw, 100vw";
