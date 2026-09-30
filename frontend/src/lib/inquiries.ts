/**
 * BUSINESS INQUIRY CONTRACT — single source of truth for the public form AND the API.
 * Canonical fields (spec §11 + Master amendment 2026-09-22): name · company · email ·
 * contact_number (canonical E.164, §15/§47) · project_type · budget · timeline · message.
 * Pure and dependency-free so it runs identically client- and server-side; the API is
 * authoritative (§21/§23) — this module is the one implementation both layers share.
 *
 * Project_type options are aligned with Permetheon's verified services (docs/07, Master Sec. 10)
 * per spec §15; budget ranges are inquiry classification only — never pricing promises (§16).
 */

export const PROJECT_TYPE_OPTIONS = [
  "Website Development",
  "Web Application",
  "Business System",
  "Booking / Reservation System",
  "UI/UX & Product Design",
  "Custom Digital Product",
  "Other",
] as const;

export const BUDGET_OPTIONS = [
  "Under $1,000",
  "$1,000 – $3,000",
  "$3,000 – $5,000",
  "$5,000 – $10,000",
  "$10,000+",
  "Not sure yet",
] as const;

export const TIMELINE_OPTIONS = [
  "As soon as possible",
  "Within 1 month",
  "1–3 months",
  "3–6 months",
  "6+ months",
  "Not sure yet",
] as const;

export const INQUIRY_STATUSES = [
  "NEW",
  "REVIEWING",
  "CONTACTED",
  "QUALIFIED",
  "PROPOSAL",
  "WON",
  "LOST",
] as const;

export const INQUIRY_PRIORITIES = ["LOW", "MEDIUM", "HIGH", "URGENT"] as const;

/** Server-controlled default for new inquiries (spec §25). */
export const DEFAULT_PRIORITY = "MEDIUM";

export type ProjectType = (typeof PROJECT_TYPE_OPTIONS)[number];
export type InquiryStatus = (typeof INQUIRY_STATUSES)[number];
export type InquiryPriority = (typeof INQUIRY_PRIORITIES)[number];

/** The canonical public submission after validation/normalization. */
export type InquirySubmission = {
  name: string;
  company: string | null;
  email: string;
  contactNumber: string;
  projectType: ProjectType;
  budget: string | null;
  timeline: string | null;
  message: string;
};

export type InquiryField =
  | "name"
  | "company"
  | "email"
  | "contactNumber"
  | "projectType"
  | "budget"
  | "timeline"
  | "message"
  | "form";

export type InquiryFieldErrors = Partial<Record<InquiryField, string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Reject script/markup payloads (spec §12/§14/§18) — stored and rendered as plain text only. */
const DANGEROUS_PATTERN = /<\s*(script|iframe|object|embed|style|svg)\b|on\w+\s*=|javascript\s*:/i;

/**
 * CONTACT NUMBER — one canonical field, one canonical format (Master amendment
 * 2026-09-22, spec §15/§47). E.164 only: `+<country code><national>`, digits only,
 * ≤15 digits total. Normalization strips presentation formatting (spaces, dots,
 * hyphens, brackets) and converts the ITU `00` international prefix. The country
 * code is NEVER guessed or auto-assumed (§15): numbers without `+`/`00` are rejected.
 * Structural validation checks the calling code against the ITU assignment table
 * (the zero-dependency equivalent of a phone library) and enforces the exact
 * national lengths of fixed-size plans (NANP +1, +7); variable-length plans
 * accept 4–12 national digits. This single implementation serves BOTH the form
 * (client normalization) and the API (authoritative validation) — §21/§22.
 */

const E164_PATTERN = /^\+[1-9]\d{1,14}$/;

/** Assigned ITU country calling codes (compact registry dump, longest-match probed). */
const COUNTRY_CODES = new Set(
  "1 7 20 27 30 31 32 33 34 36 39 40 41 43 44 45 46 47 48 49 51 52 53 54 55 56 57 58 60 61 62 63 64 65 66 81 82 84 86 90 91 92 93 94 95 98 211 212 213 216 218 220 221 222 223 224 225 226 227 228 229 230 231 232 233 234 235 236 237 238 239 240 241 242 243 244 245 246 247 248 249 250 251 252 253 254 255 256 257 258 260 261 262 263 264 265 266 267 268 269 290 291 297 298 299 350 351 352 353 354 355 356 357 358 359 370 371 372 373 374 375 376 377 378 379 380 381 382 383 385 386 387 389 420 421 423 500 501 502 503 504 505 506 507 508 509 590 591 592 593 594 595 596 597 598 599 670 672 673 674 675 676 677 678 679 680 681 682 683 684 685 686 687 688 689 690 691 692 850 852 853 855 856 870 878 880 881 882 883 886 960 961 962 963 964 965 966 967 968 970 971 972 973 974 975 976 977 992 993 994 995 996 998".split(
    " "
  )
);

/** Numbering plans with a fixed national (significant) number length. */
const FIXED_NATIONAL_LENGTH: Record<string, number> = { "1": 10, "7": 10 };

/** Strips presentation formatting and the `00` international prefix; does NOT validate. */
export function normalizeContactNumber(raw: string): string {
  const compact = raw.trim().replace(/[\s().[\]{}\-–—−]/g, "");
  return compact.startsWith("00") ? `+${compact.slice(2)}` : compact;
}

function isValidE164(value: string): boolean {
  if (!E164_PATTERN.test(value)) return false;
  const digits = value.slice(1);
  for (const length of [3, 2, 1]) {
    const callingCode = digits.slice(0, length);
    if (!COUNTRY_CODES.has(callingCode)) continue;
    const national = digits.slice(length);
    const fixed = FIXED_NATIONAL_LENGTH[callingCode];
    return fixed !== undefined ? national.length === fixed : national.length >= 4 && national.length <= 12;
  }
  return false;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function asText(value: unknown): string {
  return typeof value === "string" ? value : "";
}

function optionError(_field: InquiryField, options: readonly string[]): string {
  return `Select one of the available options: ${options.join(", ")}.`;
}

/** Danger check runs on meaningful free-text fields; message names the field generically. */
function containsMarkup(text: string): boolean {
  return DANGEROUS_PATTERN.test(text);
}

/**
 * Canonical validation (spec §12–§20, §22–§23):
 *   name         required, trimmed, 2–100, ≥1 letter, no script/markup
 *   company      optional, trimmed, ≤150, empty → null
 *   email        required, trimmed + lowercased, valid format, ≤254
 *   contactNumber required, normalized to E.164 + structurally validated (§15/§47)
 *   projectType  required, enum
 *   budget       optional, enum, empty → null
 *   timeline     optional, enum, empty → null
 *   message      required, trimmed, 20–5000, no script/markup
 *
 * Unknown/privileged fields (status, priority, role, id, …) are ignored entirely
 * on purpose — the caller persists ONLY the returned value (mass-assignment protection, §26).
 */
export function validateInquiry(raw: unknown): {
  ok: boolean;
  errors: InquiryFieldErrors;
  value?: InquirySubmission;
} {
  const errors: InquiryFieldErrors = {};

  if (!isRecord(raw)) {
    return { ok: false, errors: { form: "Invalid request payload." } };
  }

  const name = asText(raw.name).trim();
  const company = asText(raw.company).trim();
  const email = asText(raw.email).trim().toLowerCase();
  const contactNumber = normalizeContactNumber(asText(raw.contactNumber));
  const projectType = asText(raw.projectType).trim();
  const budget = asText(raw.budget).trim();
  const timeline = asText(raw.timeline).trim();
  const message = asText(raw.message).trim();

  // name
  if (!name) errors.name = "Please tell us your name.";
  else if (name.length < 2) errors.name = "Name must be at least 2 characters.";
  else if (name.length > 100) errors.name = "Name must be at most 100 characters.";
  else if (!/[a-zA-Z]/.test(name)) errors.name = "Name must contain at least one letter.";
  else if (containsMarkup(name)) errors.name = "Name may not contain markup or code.";

  // company (optional)
  if (company.length > 150) errors.company = "Company must be at most 150 characters.";
  else if (containsMarkup(company)) errors.company = "Company may not contain markup or code.";

  // email
  if (!email) errors.email = "Please enter your business email.";
  else if (email.length > 254) errors.email = "Email must be at most 254 characters.";
  else if (!EMAIL_PATTERN.test(email)) errors.email = "Enter a valid email address.";

  // contactNumber (required, canonical E.164 — spec §15/§47)
  if (!contactNumber) errors.contactNumber = "Please enter your contact number.";
  else if (!isValidE164(contactNumber))
    errors.contactNumber = "Enter a valid international contact number, e.g. +12025550147.";

  // projectType (required enum)
  if (!projectType) errors.projectType = "Select what you need built.";
  else if (!(PROJECT_TYPE_OPTIONS as readonly string[]).includes(projectType))
    errors.projectType = optionError("projectType", PROJECT_TYPE_OPTIONS);

  // budget (optional enum)
  if (budget && !(BUDGET_OPTIONS as readonly string[]).includes(budget))
    errors.budget = optionError("budget", BUDGET_OPTIONS);

  // timeline (optional enum)
  if (timeline && !(TIMELINE_OPTIONS as readonly string[]).includes(timeline))
    errors.timeline = optionError("timeline", TIMELINE_OPTIONS);

  // message
  if (!message) errors.message = "Tell us about your project (at least 20 characters).";
  else if (message.length < 20) errors.message = "Please provide at least 20 characters.";
  else if (message.length > 5000) errors.message = "Message must be at most 5,000 characters.";
  else if (containsMarkup(message)) errors.message = "Message may not contain markup or code.";

  const ok = Object.keys(errors).length === 0;
  if (!ok) return { ok: false, errors };

  return {
    ok: true,
    errors: {},
    value: {
      name,
      company: company === "" ? null : company,
      email,
      contactNumber,
      projectType: projectType as ProjectType,
      budget: budget === "" ? null : budget,
      timeline: timeline === "" ? null : timeline,
      message,
    },
  };
}
