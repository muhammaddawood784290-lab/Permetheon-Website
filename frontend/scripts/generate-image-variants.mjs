// IMAGE VARIANT GENERATOR (prebuild).
// Generates WebP responsive rungs for every case-study screenshot in
// public/screenshots so <img srcSet> can serve right-sized images:
//   <name>.w480.webp / .w960.webp / .w1440.webp / <name>.webp (original size)
// PNG originals are NEVER modified — they remain the <img src> fallback.
// Also emits src/lib/screenshot-widths.ts (generated manifest: src -> px width)
// used by src/lib/images.ts to attach a correct width descriptor to the
// original-size rung.
//
// Incremental: a rung is regenerated only when missing, older than its PNG,
// OR its PNG's content hash no longer matches the committed lock
// (scripts/image-variants.lock.json) — mtime alone can lie across branch
// switches/checkouts, and a same-size content edit must never slip through.
// Run standalone:  node scripts/generate-image-variants.mjs
// CI gate (Python-free): node scripts/check-image-variants.mjs
//
// DEPLOYMENT NOTE: the WebP rungs AND the manifest are COMMITTED. When every
// output is up-to-date this script exits WITHOUT invoking Python — deploys on
// images without Python/Pillow succeed as long as the committed assets are
// fresh (the common case). Python/Pillow is only required when work is
// actually needed, and a missing interpreter now FAILS LOUDLY with the real
// error instead of a silent exit (the old behavior: spawnSync ENOENT ->
// `process.exit(status ?? 1)` printed NOTHING).
import { existsSync, statSync, writeFileSync, readFileSync } from "node:fs";
import { readdirSync } from "node:fs";
import { createHash } from "node:crypto";
import { join, relative, posix } from "node:path";
import { spawnSync } from "node:child_process";

const PUB = join(process.cwd(), "public");
const SHOTS = join(PUB, "screenshots");
const MANIFEST = join(process.cwd(), "src", "lib", "screenshot-widths.ts");
const LOCK = join(process.cwd(), "scripts", "image-variants.lock.json");
const RUNGS = [480, 960, 1440];

const hashOf = (png) => createHash("sha256").update(readFileSync(png)).digest("hex");

function walk(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) out.push(...walk(p));
    else if (e.name.toLowerCase().endsWith(".png")) out.push(p);
  }
  return out;
}

/** Find a usable Python interpreter: `python3` first (Linux images often
 * ship no `python` alias), then `python`. Returns null when neither runs. */
function resolvePython() {
  for (const exe of process.platform === "win32" ? ["python", "python3"] : ["python3", "python"]) {
    const probe = spawnSync(exe, ["-c", "print('ok')"], { encoding: "utf8" });
    if (probe.status === 0) return exe;
  }
  return null;
}

/** Run python with REAL error reporting. Exits the process on failure —
 * the deploy log must show the underlying cause, never a bare failure. */
function runPython(exe, args, { pipeStdout = false } = {}) {
  const r = spawnSync(exe, args, {
    encoding: "utf8",
    stdio: pipeStdout ? ["ignore", "pipe", "inherit"] : ["ignore", "inherit", "inherit"],
  });
  if (r.error) {
    // ENOENT / EACCES / EAGAIN — the process never ran. Print the cause.
    console.error(`[image-variants] ERROR: failed to start ${exe}: ${r.error.code ?? r.error.message}`);
    console.error("[image-variants] Python 3 + Pillow are required to generate image variants.");
    console.error("[image-variants] Either install Python 3 with Pillow (pip install Pillow),");
    console.error("[image-variants] or restore the committed *.webp rungs + src/lib/screenshot-widths.ts so generation can be skipped.");
    process.exit(1);
  }
  if (r.status !== 0) {
    // Python itself failed — its traceback already went to stderr (inherit).
    console.error(`[image-variants] ERROR: ${exe} exited with code ${r.status}.`);
    process.exit(r.status ?? 1);
  }
  return r;
}

const pngs = walk(SHOTS);
if (pngs.length === 0) {
  writeFileSync(MANIFEST, "export const SCREENSHOT_WIDTHS: Record<string, number> = {};\n");
  console.log("[image-variants] no screenshots found");
  process.exit(0);
}

// 1. Determine stale rungs (missing or older than the PNG source).
//    UNREALIZABLE rungs (the original PNG is narrower than the rung) are
//    filtered using the COMMITTED manifest's original widths — they can
//    never exist and must not make the scan permanently stale. This is what
//    lets the fast path run without Python (the OLD script needed a Python
//    width probe before it could even plan, which is what broke deploys on
//    Python-less images).
const committedWidths = {};
if (existsSync(MANIFEST)) {
  for (const m of readFileSync(MANIFEST, "utf8").matchAll(/"([^"]+)":\s*(\d+)/g)) {
    committedWidths[m[1]] = Number(m[2]);
  }
}
// Committed content hashes of the source PNGs at last generation. A hash
// mismatch means the PNG changed after its variants were generated — stale
// regardless of what mtimes claim (checkouts can reset timestamps).
const committedHashes = {};
if (existsSync(LOCK)) {
  try {
    const lock = JSON.parse(readFileSync(LOCK, "utf8"));
    for (const [url, entry] of Object.entries(lock.images ?? {})) {
      committedHashes[url] = entry.sha256;
    }
  } catch {
    console.error(`[image-variants] WARNING: ${relative(process.cwd(), LOCK)} is not valid JSON — treating as absent.`);
  }
}
const urlOf = (png) => "/" + posix.join(...relative(PUB, png).split("\\"));

const jobs = {};
let created = 0, skipped = 0;
let freshWebp = 0;
let knownWidths = 0;
for (const png of pngs) {
  const mtime = statSync(png).mtimeMs;
  const webp = png.slice(0, -4) + ".webp";
  const stale = (f) => !existsSync(f) || statSync(f).mtimeMs < mtime;
  const url = urlOf(png);
  // Content identity beats timestamps: a PNG whose bytes no longer match the
  // committed lock is stale even if mtimes were preserved by a checkout.
  const contentStale = committedHashes[url] !== undefined && committedHashes[url] !== hashOf(png);
  if (!stale(webp) && !contentStale) freshWebp += 1;
  const width = committedWidths[url];
  if (width !== undefined) knownWidths += 1;
  const rungs = RUNGS.filter((w) => {
    if (width !== undefined && w >= width) return false; // unrealizable — never generated
    const f = png.slice(0, -4) + `.w${w}.webp`;
    const ok = !stale(f) && !contentStale;
    if (ok) skipped += 1;
    return !ok;
  });
  if (stale(webp) || contentStale) {
    jobs[png] = rungs;
  } else if (rungs.length === 0) {
    continue;
  } else {
    jobs[png] = jobs[png] ?? rungs;
  }
}

// 2. FAST PATH — nothing to do AND every width is known from the committed
//    manifest. No Python invocation at all: deployment images without Python
//    succeed when the committed assets are fresh (the common case).
if (freshWebp === pngs.length && Object.keys(jobs).length === 0 && knownWidths === pngs.length) {
  console.log(`[image-variants] ${pngs.length} screenshots; all variants up-to-date (skipped generation)`);
  process.exit(0);
}

// 3. Work is required — Python 3 + Pillow must be available.
const python = resolvePython();
if (python === null) {
  console.error("[image-variants] ERROR: no Python interpreter found (tried python3, python).");
  console.error("[image-variants] Python 3 + Pillow are required to generate image variants.");
  console.error(`[image-variants] Missing/updated outputs for ${Object.keys(jobs).length} screenshot(s).`);
  console.error("[image-variants] Either install Python 3 with Pillow (pip install Pillow),");
  console.error("[image-variants] or restore the committed *.webp rungs + src/lib/screenshot-widths.ts so generation can be skipped.");
  process.exit(1);
}

// Narrow originals (< the largest rung) can never have that rung — planning it
// every build would make them permanently "stale". Read real widths first so
// only realizable rungs are planned (idempotent rebuilds).
const widthProbe = runPython(python, [
  "-c",
  "import sys,json;from PIL import Image;print(json.dumps({p: Image.open(p).width for p in json.loads(sys.argv[1])}))",
  JSON.stringify(pngs),
], { pipeStdout: true });
const W = JSON.parse(widthProbe.stdout);

// Re-plan jobs with real widths (drops unrealizable rungs; also drops stale-
// webp entries whose only planned rungs turned out unrealizable — the
// original-size webp is then already correct at its natural width).
for (const png of Object.keys(jobs)) {
  const realizable = jobs[png].filter((w) => w < W[png]);
  const webpStale = !existsSync(png.slice(0, -4) + ".webp")
    || statSync(png.slice(0, -4) + ".webp").mtimeMs < statSync(png).mtimeMs;
  if (!webpStale && realizable.length === 0) delete jobs[png];
  else jobs[png] = realizable;
}

// 4. One Python pass: generate stale rungs AND report every PNG's width.
const r = runPython(python, [
  "-c",
  `import sys, json
from PIL import Image
jobs = json.loads(sys.argv[1])   # png -> rungs to (re)generate
for path, rungs in jobs.items():
    im = Image.open(path)
    if im.mode != "RGB":
        im = im.convert("RGB")
    for w in rungs:
        if w >= im.width:
            continue
        h = round(im.height * w / im.width)
        im.resize((w, h), Image.Resampling.LANCZOS).save(
            path[:-4] + f".w{w}.webp", "WEBP", quality=80, method=6)
    if rungs:
        im.save(path[:-4] + ".webp", "WEBP", quality=80, method=6)
widths = {p: Image.open(p).width for p in json.loads(sys.argv[2])}
print(json.dumps(widths))`,
  JSON.stringify(jobs),
  JSON.stringify(pngs),
], { pipeStdout: true });
const widths = JSON.parse(r.stdout);

for (const png of Object.keys(jobs)) created += jobs[png].length + 1;

const entries = pngs
  .map((p) => {
    const url = "/" + posix.join(...relative(PUB, p).split("\\"));
    return `  ${JSON.stringify(url)}: ${widths[p]},`;
  })
  .sort()
  .join("\n");
writeFileSync(
  MANIFEST,
  `// GENERATED by scripts/generate-image-variants.mjs — do not edit.\n` +
    `// Original-width (px) of every screenshot, for srcset width descriptors.\n` +
    `export const SCREENSHOT_WIDTHS: Record<string, number> = {\n${entries}\n};\n`
);

// Content lock for the CI gate (check-image-variants.mjs) and for content-
// aware staleness on the next run: the sha256 of each SOURCE PNG at the time
// its variants were generated. Committed alongside the rungs.
const lock = {
  _comment: "GENERATED by scripts/generate-image-variants.mjs — do not edit. sha256 of each source PNG when its variants were last generated.",
  images: Object.fromEntries(
    pngs
      .map((p) => ({ url: urlOf(p), sha256: hashOf(p), width: widths[p] }))
      .sort((a, b) => a.url.localeCompare(b.url))
      .map(({ url, sha256, width }) => [url, { sha256, width }]),
  ),
};
writeFileSync(LOCK, JSON.stringify(lock, null, 2) + "\n");

console.log(`[image-variants] ${pngs.length} screenshots; ${created} rungs written, ${skipped} up-to-date`);
