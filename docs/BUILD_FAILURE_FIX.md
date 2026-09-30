# Build Failure Fix — `generate-image-variants.mjs` prebuild

**Status: BUILD FIXED** (2026-09-30, branch `main`)

## Symptom

The deployment build stops inside the prebuild step with no underlying error:

```
> permetheon-frontend@0.1.0 prebuild
> node scripts/generate-image-variants.mjs

ERROR: Failed to build the application
```

The Vite build is never reached. Nothing useful appears in the deploy log.

## Exact root cause

`frontend/scripts/generate-image-variants.mjs` spawned `python`
**unconditionally** — a Pillow width probe ran before the staleness scan
could even decide whether any work existed — and then **silently swallowed
the failure** when the interpreter was missing:

```js
const widthProbe = spawnSync("python", ["-c", "...PIL width probe..."], {...});
if (widthProbe.status !== 0) process.exit(widthProbe.status ?? 1);
```

When the executable does not exist, `spawnSync` returns
`{ status: null, error: <ENOENT> }`. The `?? 1` fallback then performed
`process.exit(1)` **without printing anything** — the ENOENT error object was
never surfaced. The deploy harness wrapped the silent failure as
"ERROR: Failed to build the application".

Two deployment-environment facts turned this into a hard failure:

1. **Linux deployment images typically ship `python3`, not `python`** (no
   `python` alias is installed by default on current Debian/Ubuntu images).
   The script hardcoded `python`, so on those images every build hit ENOENT.
2. Even though **all 131 WebP rungs and the generated manifest are committed
   to git** — i.e. generation was never actually required at deploy time —
   the unconditional probe meant the script could never reach the
   "everything up-to-date" skip without Python present.

Reproduced locally by calling `spawnSync('python', …)` the exact way the
script does: `{"status":null,"error":"ENOENT"}` → old logic → silent
`exit(1)` with empty stdout/stderr.

## Fix applied

`frontend/scripts/generate-image-variants.mjs` (only file changed):

1. **Real error reporting.** `runPython()` checks `result.error` and prints
   the underlying cause (`ENOENT`, `EACCES`, …) plus actionable remediation
   before exiting. Python tracebacks still stream to the deploy log via
   stdio inheritance. No failure path exits silently anymore.
2. **Interpreter resolution.** `resolvePython()` tries `python3` first, then
   `python` (order is platform-aware: Windows keeps `python` first). A
   missing interpreter on a Python-less image is reported loudly, listing
   exactly which outputs are missing.
3. **Python-free fast path.** The staleness scan now uses the **committed
   manifest** (`src/lib/screenshot-widths.ts`) to exclude unrealizable rungs
   (an original narrower than a rung can never have that rung — the old
   script needed the Python width probe for exactly this). When every PNG's
   original-size WebP is fresh and every realizable rung exists, the script
   exits **without invoking Python at all**. Deployment images without
   Python therefore build successfully whenever the committed assets are
   fresh — the normal case, since assets are committed.
4. **Generation behavior preserved.** When work IS needed (new/changed
   screenshots), the script requires Python 3 + Pillow, clearly reports its
   absence, and otherwise behaves exactly as before (same rungs, same
   quality settings, same manifest output).

## Why it failed in the deployment environment

- Node 22 on Linux, clean `npm ci` install: `node`/`npm` are present, but the
  base image has no `python` binary (and possibly no Python at all).
- The script spawned `python` before checking whether any work existed.
- `spawnSync` ENOENT → `status: null` → silent `process.exit(1)`.
- Case-sensitivity was NOT a factor; all committed paths are lowercase and
  consistent. No permissions, formats, or dependencies were involved.

## Local verification result

- `node scripts/generate-image-variants.mjs` (with Python available):
  `[image-variants] 27 screenshots; all variants up-to-date (skipped generation)`,
  exit 0.
- Simulated deployment (PATH without any python):
  **same fast path, exit 0** — a Python-less deploy now succeeds.
- Simulated stale rung + no Python: exit 1 with the REAL error printed:
  `[image-variants] ERROR: no Python interpreter found (tried python3, python).`
  plus remediation lines (the deploy log will now show the cause).
- Simulated stale rung + Python present: regenerated the rung; regenerated
  bytes are **byte-identical to the committed file** (git reports zero diffs
  in `public/`) — generation is deterministic and production-safe.

## Production build result

`npm install && npm run build` from clean state on branch `main`:

- prebuild: passes (fast path, no Python required)
- Vite build: passes (`[vite-react-ssg] Build finished.`)
- `dist/` generated (23 top-level entries incl. `admin/`, `case-studies/`,
  `assets/`)
- prerendered routes: **140 passed, 0 failed** (verify-prerender step)
- sitemap: `dist/sitemap.xml` written with 11 URLs
- image references: **54 unique `screenshots/...` refs across prerendered
  HTML, 0 broken** (every ref resolves to a file in `dist/`)
- typecheck: `tsc --noEmit` clean

React stays at 19; no dependency changes, no `--force`, no
`--legacy-peer-deps`. The react-helmet-async peer warning remains a warning
and does not affect the build.
