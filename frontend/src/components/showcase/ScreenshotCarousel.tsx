
import { useCallback, useEffect, useRef, useState } from "react";
import type { ShowcaseImage } from "@/data/case-studies";
import { screenshotSrcSet, screenshotOriginalWebp, WIDE_SIZES } from "@/lib/images";

type ScreenshotCarouselProps = {
  images: ShowcaseImage[];
  /** Accessible label describing the showcase, e.g. "PCT product screenshots". */
  label: `${string} product screenshots`;
  className?: string;
};

/**
 * SCREENSHOT SHOWCASE CAROUSEL — Sec. 19 / ScreenshotGallery realization.
 * One primary screenshot at a time; prev/next, pagination dots, touch swipe,
 * keyboard operable, honors prefers-reduced-motion. The screenshot stays the
 * visual focus — restrained container, subtle border/shadow, no browser frames.
 */
export function ScreenshotCarousel({ images, label, className = "" }: ScreenshotCarouselProps) {
  const [index, setIndex] = useState(0);
  const touchStartX = useRef<number | null>(null);
  const trackRef = useRef<HTMLDivElement>(null);
  const count = images.length;

  const goTo = useCallback(
    (next: number) => setIndex(((next % count) + count) % count),
    [count]
  );

  // Keyboard: arrow keys while focus is inside the carousel.
  const onKeyDown = (event: React.KeyboardEvent) => {
    if (event.key === "ArrowRight") {
      event.preventDefault();
      goTo(index + 1);
    } else if (event.key === "ArrowLeft") {
      event.preventDefault();
      goTo(index - 1);
    }
  };

  // Preload adjacent images so prev/next feels instant — the original-size
  // WebP rung (what the carousel actually shows at desktop widths), not the
  // multi-megabyte PNG original.
  useEffect(() => {
    for (const offset of [1, -1]) {
      const neighbor = images[(index + offset + count) % count];
      if (!neighbor) continue;
      const img = new window.Image();
      img.src = screenshotOriginalWebp(neighbor.src) ?? neighbor.src;
    }
  }, [index, images, count]);

  return (
    <figure
      role="region"
      aria-roledescription="carousel"
      aria-label={label}
      onKeyDown={onKeyDown}
      className={`group relative overflow-hidden rounded-card border border-line bg-paper-soft shadow-lg shadow-black/5 ${className}`}
    >
      {/* Viewport — overflow hidden clips non-active slides. */}
      <div
        ref={trackRef}
        className="overflow-hidden"
        onTouchStart={(e) => {
          touchStartX.current = e.touches[0].clientX;
        }}
        onTouchEnd={(e) => {
          if (touchStartX.current === null) return;
          const dx = e.changedTouches[0].clientX - touchStartX.current;
          if (Math.abs(dx) > 48) goTo(index + (dx < 0 ? 1 : -1));
          touchStartX.current = null;
        }}
      >
        <div
          className="flex transition-transform duration-500 ease-out motion-reduce:transition-none"
          style={{ transform: `translateX(-${index * 100}%)` }}
        >
          {images.map((image, i) => (
            <div
              key={image.src}
              className="w-full shrink-0"
              role="group"
              aria-roledescription="slide"
              aria-label={`${i + 1} of ${count}`}
              aria-hidden={i !== index}
            >
              {/* object-contain: original aspect ratio preserved, no cropping or stretching. */}
              <img
                src={image.src}
                {...(screenshotSrcSet(image.src)
                  ? { srcSet: screenshotSrcSet(image.src), sizes: WIDE_SIZES }
                  : {})}
                alt={i === index ? image.alt : ""}
                className="pointer-events-none h-auto w-full select-none object-contain"
                loading={i === 0 ? "eager" : "lazy"}
                draggable={false}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Controls — subtle, professional; appear softly on hover on desktop. */}
      <button
        type="button"
        onClick={() => goTo(index - 1)}
        aria-label="Previous screenshot"
        className="absolute left-3 top-1/2 -translate-y-1/2 rounded-pill border border-line bg-paper/90 p-2.5 text-ink shadow-sm backdrop-blur-sm transition-opacity focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-accent md:opacity-0 md:group-hover:opacity-100"
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M10 3L5 8l5 5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => goTo(index + 1)}
        aria-label="Next screenshot"
        className="absolute right-3 top-1/2 -translate-y-1/2 rounded-pill border border-line bg-paper/90 p-2.5 text-ink shadow-sm backdrop-blur-sm transition-opacity focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-accent md:opacity-0 md:group-hover:opacity-100"
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M6 3l5 5-5 5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </button>

      {/* Pagination indicators */}
      <figcaption className="flex items-center justify-center gap-2 border-t border-line py-3">
        {images.map((image, i) => (
          <button
            key={image.src}
            type="button"
            aria-label={`Go to screenshot ${i + 1} of ${count}`}
            aria-current={i === index}
            onClick={() => goTo(i)}
            className={`h-1.5 rounded-pill transition-all ${
              i === index ? "w-6 bg-accent" : "w-1.5 bg-fog/40 hover:bg-fog"
            }`}
          />
        ))}
      </figcaption>
    </figure>
  );
}
