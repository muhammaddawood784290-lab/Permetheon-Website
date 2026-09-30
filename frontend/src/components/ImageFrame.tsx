import { Badge } from "./Badge";
import { screenshotSrcSet, CARD_SIZES } from "@/lib/images";

type ImageFrameProps = {
  src?: string;
  alt?: string;
  label?: string;
  aspect?: "video" | "square" | "wide";
  className?: string;
  dark?: boolean;
};

const aspectMap = {
  video: "aspect-video",
  wide: "aspect-[21/9]",
  square: "aspect-square",
} as const;

/**
 * Renders a real screenshot when `src` is provided (object-contain — the product UI
 * is never cropped or stretched).
 * When no asset exists, renders a clearly marked TEMPORARY placeholder —
 * never a fabricated UI (Master Sec. 19, Blocker 02).
 */
export function ImageFrame({
  src,
  alt,
  label,
  aspect = "video",
  className = "",
  dark = false,
}: ImageFrameProps) {
  if (!src) {
    return (
      <div
        className={`flex ${aspectMap[aspect]} w-full flex-col items-center justify-center gap-3 rounded-card border border-dashed p-6 text-center ${
          dark ? "border-line-dark bg-ink-soft" : "border-line bg-paper-soft"
        } ${className}`}
      >
        <Badge dark={dark}>TEMPORARY — REAL SCREENSHOT PENDING</Badge>
        {label ? (
          <p className={`text-sm ${dark ? "text-fog-dark" : "text-fog"}`}>{label}</p>
        ) : null}
      </div>
    );
  }
  const srcSet = screenshotSrcSet(src);
  return (
    <img
      src={src}
      {...(srcSet ? { srcSet, sizes: CARD_SIZES } : {})}
      alt={alt ?? ""}
      loading="lazy"
      className={`w-full rounded-card bg-inherit object-contain ${aspectMap[aspect]} ${className}`}
    />
  );
}
