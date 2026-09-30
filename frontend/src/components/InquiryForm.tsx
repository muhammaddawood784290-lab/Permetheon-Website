
import { useState } from "react";
import { apiUrl } from "@/lib/api";
import {
  BUDGET_OPTIONS,
  normalizeContactNumber,
  PROJECT_TYPE_OPTIONS,
  TIMELINE_OPTIONS,
  validateInquiry,
  type InquiryFieldErrors,
} from "@/lib/inquiries";
import { Button } from "./Button";
import { formatLongDate, formatTime12h, splitSlot } from "@/lib/meetings";
import { MeetingBookingSection, submitLabel, type MeetingSelection } from "./MeetingBookingSection";

/**
 * BUSINESS INQUIRY FORM — the canonical public form (spec §11–§20 as amended 2026-09-22, §31–§33).
 * Eight fields: name* · company · email* · contactNumber* · projectType* · budget · timeline · message*.
 * contact_number is THE single contact-number field (labeled “Contact Number / WhatsApp”);
 * it is normalized to canonical E.164 before submission (§15) using the SAME shared
 * contract the server enforces (src/lib/inquiries.ts) — one implementation, no divergence.
 * Client-side validation uses the SAME shared contract the server enforces
 * (src/lib/inquiries.ts) — one implementation, no divergence (spec §22).
 * The official public business-inquiry email is businessinquiry@permetheon.com (§03) —
 * shown as the fallback channel; no phone number anywhere (§19).
 */

const inputClasses =
  "w-full rounded-card border border-line bg-paper-soft px-4 py-3 text-sm text-ink placeholder:text-fog focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30";

const labelClasses = "text-xs font-semibold uppercase tracking-[0.14em] text-fog";

type Status = "idle" | "submitting" | "success" | "error";

const FIELD_ERROR_CONTROL: Record<keyof InquiryFieldErrors, string> = {
  name: "inquiry-name",
  company: "inquiry-company",
  email: "inquiry-email",
  contactNumber: "inquiry-contact",
  projectType: "inquiry-type",
  budget: "inquiry-budget",
  timeline: "inquiry-timeline",
  message: "inquiry-message",
  form: "inquiry-form-error",
};

function FieldError({ field, errors }: { field: keyof InquiryFieldErrors; errors: InquiryFieldErrors }) {
  const message = errors[field];
  if (!message) return null;
  return (
    <p id={`${FIELD_ERROR_CONTROL[field]}-error`} className="text-xs text-accent-strong" role="alert">
      {message}
    </p>
  );
}

export function InquiryForm() {
  const [status, setStatus] = useState<Status>("idle");
  const [errors, setErrors] = useState<InquiryFieldErrors>({});
  const [meeting, setMeeting] = useState<MeetingSelection>(null);
  const [meetingRefreshKey, setMeetingRefreshKey] = useState(0);
  const [bookedMeeting, setBookedMeeting] = useState<{ startsAt?: string; status?: string } | null>(null);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);

    const payload = {
      name: String(data.get("name") ?? ""),
      company: String(data.get("company") ?? ""),
      email: String(data.get("email") ?? ""),
      contactNumber: normalizeContactNumber(String(data.get("contactNumber") ?? "")),
      projectType: String(data.get("projectType") ?? ""),
      budget: String(data.get("budget") ?? ""),
      timeline: String(data.get("timeline") ?? ""),
      message: String(data.get("message") ?? ""),
    };

    // Shared validation contract — same rules the server enforces.
    const result = validateInquiry(payload);
    if (!result.ok) {
      setErrors(result.errors);
      const firstField = (Object.keys(FIELD_ERROR_CONTROL) as Array<keyof InquiryFieldErrors>).find(
        (field) => result.errors[field]
      );
      if (firstField) document.getElementById(FIELD_ERROR_CONTROL[firstField])?.focus();
      return;
    }
    setErrors({});
    setStatus("submitting");

    // Meeting payload (meetings spec §1/§5): only when the customer picked a
    // slot. The server re-validates availability inside the booking
    // transaction — this client is never trusted.
    const body = meeting ? { ...payload, meeting: { booking: true, startsAt: meeting.startsAt } } : payload;

    try {
      const response = await fetch(apiUrl("/api/inquiries"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });

      if (response.status === 422) {
        const errorBody = (await response.json().catch(() => null)) as {
          error?: { fields?: Record<string, string> };
        } | null;
        setErrors((errorBody?.error?.fields as InquiryFieldErrors) ?? { form: "Please review the highlighted fields." });
        setStatus("idle");
        return;
      }
      if (response.status === 429) {
        setErrors({ form: "Too many submissions from this connection. Please try again shortly." });
        setStatus("idle");
        return;
      }
      // 409 — the slot was taken between pick and submit (meetings spec §5):
      // say so plainly, drop the stale selection, refresh the calendar.
      if (response.status === 409) {
        setErrors({
          form: "That meeting time was just taken. Please pick another available time — your other details are still here.",
        });
        setMeeting(null);
        setMeetingRefreshKey((k) => k + 1);
        setStatus("idle");
        return;
      }
      if (!response.ok) throw new Error("Submission failed");

      const okBody = (await response.json().catch(() => null)) as {
        data?: { meeting?: { startsAt?: string; status?: string } | null };
      } | null;
      setBookedMeeting(okBody?.data?.meeting ?? null);

      // Success only after confirmed persistence (spec §29/§32).
      setStatus("success");
      form.reset();
      setMeeting(null);
      // Availability changed (a slot is now taken) — refresh for the next visitor.
      setMeetingRefreshKey((k) => k + 1);
    } catch {
      setStatus("error");
    }
  }

  if (status === "success") {
    return (
      <div className="rounded-card border border-line bg-paper-soft p-8" aria-live="polite">
        <h2 className="font-display text-xl font-semibold text-ink">
          Thank you — your inquiry has been received.
        </h2>
        {bookedMeeting ? (
          <div className="mt-4 rounded-card border border-accent/40 bg-paper p-4">
            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">Your meeting</p>
            <p className="mt-1.5 font-display text-lg font-semibold text-ink">
              {bookedMeeting.startsAt ? formatLongDate(splitSlot(bookedMeeting.startsAt).date) : ""}
            </p>
            <p className="text-sm text-ink">
              {bookedMeeting.startsAt ? formatTime12h(splitSlot(bookedMeeting.startsAt).time) : ""} ·{" "}
              {bookedMeeting.status ?? "BOOKED"} — we&apos;ll confirm by email.
            </p>
          </div>
        ) : null}
        <p className="mt-3 text-sm leading-relaxed text-fog">
          We&apos;ll review your project details and get back to you.
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6" aria-busy={status === "submitting"}>
      {errors.form ? (
        <p id="inquiry-form-error" role="alert" className="rounded-card border border-accent/40 bg-accent/10 p-4 text-sm text-ink">
          {errors.form}
        </p>
      ) : null}

      {status === "error" ? (
        <div role="alert" className="rounded-card border border-accent/40 bg-accent/10 p-4 text-sm text-ink">
          <p>We couldn&apos;t submit your inquiry right now. Please try again in a moment.</p>
          <p className="mt-2 text-fog">
            You can also reach us directly at{" "}
            <a href="mailto:businessinquiry@permetheon.com" className="underline hover:text-ink">
              businessinquiry@permetheon.com
            </a>
            .
          </p>
        </div>
      ) : null}

      <div className="grid gap-6 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <label htmlFor="inquiry-name" className={labelClasses}>
            Full Name *
          </label>
          <input
            id="inquiry-name"
            name="name"
            type="text"
            autoComplete="name"
            placeholder="Your name"
            maxLength={100}
            className={inputClasses}
            aria-invalid={Boolean(errors.name)}
            aria-describedby={errors.name ? "inquiry-name-error" : undefined}
            required
          />
          <FieldError field="name" errors={errors} />
        </div>

        <div className="flex flex-col gap-2">
          <label htmlFor="inquiry-company" className={labelClasses}>
            Company / Business
          </label>
          <input
            id="inquiry-company"
            name="company"
            type="text"
            autoComplete="organization"
            placeholder="Company or organization (optional)"
            maxLength={150}
            className={inputClasses}
            aria-invalid={Boolean(errors.company)}
            aria-describedby={errors.company ? "inquiry-company-error" : undefined}
          />
          <FieldError field="company" errors={errors} />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="inquiry-email" className={labelClasses}>
          Business Email *
        </label>
        <input
          id="inquiry-email"
          name="email"
          type="email"
          autoComplete="email"
          placeholder="you@company.com"
          maxLength={254}
          className={inputClasses}
          aria-invalid={Boolean(errors.email)}
          aria-describedby={errors.email ? "inquiry-email-error" : undefined}
          required
        />
        <FieldError field="email" errors={errors} />
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="inquiry-contact" className={labelClasses}>
          Contact Number / WhatsApp *
        </label>
        <input
          id="inquiry-contact"
          name="contactNumber"
          type="tel"
          autoComplete="tel"
          inputMode="tel"
          placeholder="+1 202 555 0147"
          maxLength={32}
          className={inputClasses}
          aria-invalid={Boolean(errors.contactNumber)}
          aria-describedby={errors.contactNumber ? "inquiry-contact-error" : "inquiry-contact-hint"}
          required
        />
        <p id="inquiry-contact-hint" className="text-xs text-fog">
          Please provide a number where we can contact you. WhatsApp number is preferred if
          available. Include the country code, e.g. +1 202 555 0147.
        </p>
        <FieldError field="contactNumber" errors={errors} />
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="inquiry-type" className={labelClasses}>
          What do you need built? *
        </label>
        <select
          id="inquiry-type"
          name="projectType"
          className={inputClasses}
          defaultValue=""
          aria-invalid={Boolean(errors.projectType)}
          aria-describedby={errors.projectType ? "inquiry-type-error" : undefined}
          required
        >
          <option value="" disabled>
            Select a project type
          </option>
          {PROJECT_TYPE_OPTIONS.map((type) => (
            <option key={type} value={type}>
              {type}
            </option>
          ))}
        </select>
        <FieldError field="projectType" errors={errors} />
      </div>

      <div className="grid gap-6 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <label htmlFor="inquiry-budget" className={labelClasses}>
            Estimated Budget
          </label>
          <select
            id="inquiry-budget"
            name="budget"
            className={inputClasses}
            defaultValue=""
            aria-invalid={Boolean(errors.budget)}
            aria-describedby={errors.budget ? "inquiry-budget-error" : undefined}
          >
            <option value="">Select a range (optional)</option>
            {BUDGET_OPTIONS.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <FieldError field="budget" errors={errors} />
        </div>

        <div className="flex flex-col gap-2">
          <label htmlFor="inquiry-timeline" className={labelClasses}>
            When would you like to start?
          </label>
          <select
            id="inquiry-timeline"
            name="timeline"
            className={inputClasses}
            defaultValue=""
            aria-invalid={Boolean(errors.timeline)}
            aria-describedby={errors.timeline ? "inquiry-timeline-error" : undefined}
          >
            <option value="">Select a timeline (optional)</option>
            {TIMELINE_OPTIONS.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <FieldError field="timeline" errors={errors} />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="inquiry-message" className={labelClasses}>
          Tell us about your project *
        </label>
        <textarea
          id="inquiry-message"
          name="message"
          rows={6}
          minLength={20}
          maxLength={5000}
          placeholder="What are you building, what problem does it solve, and what does success look like?"
          className={inputClasses}
          aria-invalid={Boolean(errors.message)}
          aria-describedby={errors.message ? "inquiry-message-error" : undefined}
          required
        />
        <FieldError field="message" errors={errors} />
      </div>

      {/* Optional meeting booking (meetings spec §1/§11) — a natural extension
          of the form: same tokens, same scale, below the canonical fields. */}
      <MeetingBookingSection
        selection={meeting}
        onSelect={setMeeting}
        refreshKey={meetingRefreshKey}
        disabled={status === "submitting"}
      />

      <div className="flex flex-col items-start gap-3">
        <Button type="submit" variant="primary" disabled={status === "submitting"}>
          {submitLabel(Boolean(meeting), status === "submitting")}
        </Button>
        <p className="text-xs text-fog">
          Prefer email? Write to{" "}
          <a href="mailto:businessinquiry@permetheon.com" className="underline hover:text-ink">
            businessinquiry@permetheon.com
          </a>{" "}
          — our official business inquiry address.
        </p>
      </div>
    </form>
  );
}
