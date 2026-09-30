#!/usr/bin/env node
/**
 * ISOLATED LARAVEL E2E SERVER — for the E2E inquiry + meetings suites
 * (scripts/e2e-inquiry.mjs, scripts/e2e-meetings.mjs).
 *
 * Boots `php artisan serve` with a THROWAWAY MySQL database that this launcher
 * creates before start and DROPS after the run, plus bootstrap credentials and
 * a raised rate limit — so the production database never receives test
 * records. The application database (permetheon) is never touched.
 *
 * Usage:
 *   node scripts/e2e-laravel-server.mjs            # start, print "READY", stay alive
 *   node scripts/e2e-laravel-server.mjs --stop     # kill a previous instance (by pidfile) + drop the DB
 *
 * MySQL connection for the throwaway database (defaults match a stock XAMPP
 * MariaDB): override with DB_HOST / DB_PORT / DB_USERNAME / DB_PASSWORD.
 * The mysql CLI client must be reachable (MYSQL_CLI env var or on PATH).
 */
import { spawn, execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";
import { existsSync, writeFileSync, readFileSync, rmSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const BACKEND = path.join(ROOT, "backend");
const PID_FILE = path.join(BACKEND, "storage", "e2e-server.pid");
// Port 3102 is the E2E-suite pin; for interactive dev (Vite proxy → Laravel)
// pass LARAVEL_PORT=8000 to match frontend/.env.development's documented target.
const PORT = Number(process.env.LARAVEL_PORT ?? 3102);

// Throwaway database — created fresh, dropped on stop. NEVER point this at a
// real database name.
const DB_NAME = process.env.E2E_DB_NAME ?? "permetheon_e2e";

const DB = {
  host: process.env.DB_HOST ?? "127.0.0.1",
  port: process.env.DB_PORT ?? "3306",
  username: process.env.DB_USERNAME ?? "root",
  password: process.env.DB_PASSWORD ?? "",
};

const ENV = {
  APP_ENV: "local",
  APP_DEBUG: "true",
  DB_CONNECTION: "mysql",
  DB_HOST: DB.host,
  DB_PORT: DB.port,
  DB_DATABASE: DB_NAME,
  DB_USERNAME: DB.username,
  DB_PASSWORD: DB.password,
  CACHE_STORE: "database",
  SESSION_DRIVER: "database",
  QUEUE_CONNECTION: "sync",
  MAIL_MAILER: "array",
  ADMIN_BOOTSTRAP_EMAIL: "admin@permetheon.com",
  ADMIN_BOOTSTRAP_PASSWORD: "E2E-test-passphrase-1",
  INQUIRY_RATE_LIMIT_MAX: "100",
  // `php artisan serve` hardcodes its host/port via argv, but APP_URL keeps
  // URL generation and origin checks consistent.
  APP_URL: `http://localhost:${PORT}`,
};

const MYSQL_CLI = process.env.MYSQL_CLI ?? resolveMysqlCli();

/** Find a mysql client: PATH first, then common Windows/XAMPP locations. */
function resolveMysqlCli() {
  if (process.platform !== "win32") return "mysql";
  const candidates = [
    "C:\\xampp\\mysql\\bin\\mysql.exe",
    "C:\\xampp\\mysql\\bin\\mysql.bat",
    "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe",
    "C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe",
  ];
  for (const c of candidates) {
    if (existsSync(c)) return c;
  }
  return "mysql"; // last try — may fail with a clear ENOENT
}

function mysqlArgs(extra = []) {
  return [
    `-h${DB.host}`,
    `-P${DB.port}`,
    `-u${DB.username}`,
    ...(DB.password !== "" ? [`-p${DB.password}`] : []),
    ...extra,
  ];
}

function mysqlExec(sql) {
  execFileSync(MYSQL_CLI, mysqlArgs(["-e", sql]), { stdio: "ignore" });
}

function createDatabase() {
  mysqlExec(
    `DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`,
  );
  console.log(`>> created throwaway database ${DB_NAME}`);
}

function dropDatabase() {
  try {
    mysqlExec(`DROP DATABASE IF EXISTS \`${DB_NAME}\`;`);
    console.log(`>> dropped throwaway database ${DB_NAME}`);
  } catch {
    console.log(`>> could not drop ${DB_NAME} (already gone or server unreachable)`);
  }
}

if (process.argv.includes("--stop")) {
  if (existsSync(PID_FILE)) {
    const pid = Number(readFileSync(PID_FILE, "utf8").trim());
    try {
      process.platform === "win32"
        ? execFileSync("taskkill", ["/F", "/T", "/PID", String(pid)], { stdio: "ignore" })
        : process.kill(pid, "SIGTERM");
      console.log(`stopped pid ${pid}`);
    } catch {
      console.log(`pid ${pid} already gone`);
    }
  } else {
    console.log("no pidfile — nothing to stop");
  }
  dropDatabase();
  rmSync(PID_FILE, { force: true });
  process.exit(0);
}

// ---------------------------------------------------------------- start path
if (process.platform !== "win32") {
  console.error("This launcher currently supports Windows (taskkill-based stop).");
  process.exit(1);
}
if (!existsSync(path.join(BACKEND, "artisan"))) {
  console.error(`Laravel backend not found at ${BACKEND}`);
  process.exit(1);
}

mkdirSync(path.join(BACKEND, "storage"), { recursive: true });

function run(cmd, args, useEnv = false) {
  execFileSync(cmd, args, {
    cwd: BACKEND,
    stdio: "inherit",
    ...(useEnv ? { env: { ...process.env, ...ENV } } : {}),
  });
}

createDatabase();

console.log(">> migrating fresh throwaway database…");
run("php", ["artisan", "migrate:fresh", "--force"], true);
run("php", ["artisan", "db:seed", "--force"], true);

console.log(`>> starting php artisan serve on :${PORT}…`);
const child = spawn("php", ["artisan", "serve", `--host=0.0.0.0`, `--port=${String(PORT)}`], {
  cwd: BACKEND,
  env: { ...process.env, ...ENV },
  stdio: ["ignore", "inherit", "inherit"],
  detached: false,
});

writeFileSync(PID_FILE, String(child.pid));

const shutdown = (sig) => {
  console.log(`\n>> ${sig} — stopping server and dropping the throwaway database…`);
  try {
    if (process.platform === "win32") {
      execFileSync("taskkill", ["/F", "/T", "/PID", String(child.pid)], { stdio: "ignore" });
    } else {
      process.kill(-child.pid, "SIGTERM");
    }
  } catch { /* already gone */ }
  dropDatabase();
  rmSync(PID_FILE, { force: true });
  process.exit(0);
};
process.on("SIGINT", () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));
process.on("exit", () => {
  // Best-effort fallback if killed hard.
  try { execFileSync("taskkill", ["/F", "/T", "/PID", String(child.pid)], { stdio: "ignore" }); } catch { /* gone */ }
  try { dropDatabase(); } catch { /* best effort */ }
});

// Wait until the server answers, then signal readiness.
const deadline = Date.now() + 45_000;
while (Date.now() < deadline) {
  try {
    const res = await fetch(`http://127.0.0.1:${PORT}/up`);
    if (res.ok) {
      console.log(`READY http://localhost:${PORT} (pid ${child.pid})`);
      break;
    }
  } catch { /* not up yet */ }
  await new Promise((r) => setTimeout(r, 500));
}

// Keep the launcher alive while the child runs.
child.on("exit", (code) => {
  console.log(`server exited (${code})`);
  process.exit(code ?? 0);
});
