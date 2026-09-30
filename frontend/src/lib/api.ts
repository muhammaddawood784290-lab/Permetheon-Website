/**
 * API BASE â€.
 * Production is same-origin by definition (Laravel serves the built SPA), so
 * VITE_API_BASE defaults to "" and only the dev Vite server sets it
 * (frontend/.env.development: VITE_API_BASE=http://localhost:8000).
 * This keeps CSRF/origin semantics identical to the current deployment.
 */
export const API_BASE: string = import.meta.env.VITE_API_BASE ?? "";

export function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}
