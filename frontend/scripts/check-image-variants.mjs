// IMAGE VARIANTS CI GATE (Python-free).
// Fails when the COMMITTED variants are stale relative to the committed
// source PNGs, with the exact reason per file. Runs on any machine with
// Node — no Python, no Pillow — so CI can gate every push.
//
// What "stale" means here (content identity, NOT mtimes — a fresh clone
// resets every timestamp, so mtimes are meaningless in CI):
//   1. a source PNG missing from the lock          -> "not in lock"
//   2. a source PNG whose sha256 != lock           -> "PNG changed after generation"
//   3. a required rung missing on disk             -> "missing rung wN"
//   4. a lock entry whose width != manifest width  -> "width mismatch"
//   5. a lock entry with no source PNG             -> "stale lock entry"
//
// Required rungs are derived from the LOCK's width (rungs < width), so no
// image library is needed. Exit 1 with a reason list on any staleness;
// prints a one-line OK otherwise.
import { existsSync, readFileSync, readdirSync } from "node:fs";
import { createHash } from "node:crypto";
import { join, relative } from "node:path";
import { posix } from "node:path";

const PUB = join(process.cwd(), "public");
const SHOTS = join(PUB, "screenshots");
const MANIFEST = join(process.cwd(), "src", "lib", "screenshot-widths.ts");
const LOCK = join(process.cwd(), "scripts", "image-variants.lock.json");
const RUNGS = [480, 960, 1440];

const fail = (msg) => {
  console.error(`[image-variants] CI GATE FAILED: ${msg}`);
  process.exit(1);
};

if (!existsSync(LOCK)) {
  fail(
    `scripts/image-variants.lock.json is missing. Run 'node scripts/generate-image-variants.mjs' ` +
      `where Python 3 + Pillow are available and commit the generated rungs + lock + manifest.`,
  );
}
if (!existsSync(MANIFEST)) {
  fail(
    `src/lib/screenshot-widths.ts is missing. Run 'node scripts/generate-image-variants.mjs' ` +
      `where Python 3 + Pillow are available and commit the manifest.`,
  );
}

const lock = JSON.parse(readFileSync(LOCK, "utf8"));
const manifestWidths = {};
for (const m of readFileSync(MANIFEST, "utf8").matchAll(/"([^"]+)":\s*(\d+)/g)) {
  manifestWidths[m[1]] = Number(m[2]);
}

const walk = (dir) =>
  readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name);
    return e.isDirectory() ? walk(p) : e.name.toLowerCase().endsWith(".png") ? [p] : [];
  });

const pngs = existsSync(SHOTS) ? walk(SHOTS) : [];
const urlOf = (png) => "/" + posix.join(...relative(PUB, png).split("\\"));

const problems = [];
const seen = new Set();

// --- per-source-PNG checks -------------------------------------------------
for (const png of pngs) {
  const url = urlOf(png);
  seen.add(url);
  const entry = lock.images?.[url];

  if (!entry) {
    problems.push(`${url}: PNG not in lock — variants were never generated for it`);
    continue;
  }

  const actualHash = createHash("sha256").update(readFileSync(png)).digest("hex");
  if (actualHash !== entry.sha256) {
    problems.push(`${url}: PNG changed after generation (sha256 ${entry.sha256.slice(0, 12)}… -> ${actualHash.slice(0, 12)}…)`);
    continue;
  }

  const width = manifestWidths[url];
  if (width === undefined) {
    problems.push(`${url}: missing from src/lib/screenshot-widths.ts manifest`);
    continue;
  }
  if (width !== entry.width) {
    problems.push(`${url}: manifest width ${width} != lock width ${entry.width}`);
    continue;
  }

  // Every rung smaller than the original must exist.
  for (const w of RUNGS) {
    if (w >= entry.width) continue; // unrealizable by design
    const rung = png.slice(0, -4) + `.w${w}.webp`;
    if (!existsSync(rung)) {
      problems.push(`${url}: missing rung w${w} (${relative(process.cwd(), rung)})`);
    }
  }
  if (!existsSync(png.slice(0, -4) + ".webp")) {
    problems.push(`${url}: missing original-size rung (${relative(process.cwd(), png.slice(0, -4) + ".webp")})`);
  }
}

// --- lock entries whose source vanished -------------------------------------
for (const url of Object.keys(lock.images ?? {})) {
  if (!seen.has(url)) {
    problems.push(`${url}: lock entry has no source PNG (PNG deleted without regenerating the lock)`);
  }
}

if (problems.length > 0) {
  console.error(
    `[image-variants] ${problems.length} stale variant problem(s) — the committed assets do not match the committed sources:`,
  );
  for (const p of problems) console.error("  - " + p);
  console.error(
    "[image-variants] Fix: run 'node scripts/generate-image-variants.mjs' where Python 3 + Pillow are available, then commit the regenerated rungs, lock and manifest.",
  );
  process.exit(1);
}

console.log(`[image-variants] CI gate OK: ${pngs.length} screenshot(s), all committed variants match their sources.`);
