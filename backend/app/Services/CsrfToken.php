<?php

namespace App\Services;

/**
 * SESSION-BOUND CSRF TOKEN (F3) — replaces the independent random
 * double-submit cookie.
 *
 * The old token was pure random: ANY party who could set the admin_csrf
 * cookie (subdomain injection, cookie tossing on a misconfigured domain)
 * could align cookie and header and pass validation. Now the token is a
 * random nonce SIGNED with HMAC-SHA256 keyed by the session-token hash —
 * the value ONLY the server and the HttpOnly admin_session cookie know.
 * A forged or cross-session cookie value cannot produce a valid tag, so
 * binding to the header value can't be replayed across sessions.
 *
 * Wire format: <43-char base64url nonce> . "." . <43-char base64url tag>
 *
 * Two-phase lifecycle:
 *   - issue($sessionHash) at login/refresh — cookie + body token;
 *   - validate($cookieValue, $headerValue, $sessionHash) at write time —
 *     the middleware knows the session hash from the resolved session row.
 */
class CsrfToken
{
    private const NONCE_BYTES = 32;
    private const TAG_BYTES = 32;

    /** Issue a fresh token bound to the given session (token hash). */
    public function issue(string $sessionHash): string
    {
        $nonce = $this->b64(random_bytes(self::NONCE_BYTES));
        $tag = $this->b64(hash_hmac('sha256', $nonce, $sessionHash, true));

        return $nonce . '.' . $tag;
    }

    /**
     * Constant-time validation of the double-submitted token against the
     * session it must be bound to. Both cookie and header must be present,
     * structurally valid, equal, AND carry a tag that verifies under THIS
     * session's key.
     */
    public function validate(?string $cookieValue, ?string $headerValue, string $sessionHash): bool
    {
        if (! is_string($cookieValue) || ! is_string($headerValue)) {
            return false;
        }
        if ($cookieValue === '' || $cookieValue !== $headerValue) {
            return false;
        }
        // Length gate before hash_equals: leak-free rejection of wrong-size input.
        $expectedLength = 43 + 1 + 43;
        if (strlen($cookieValue) !== $expectedLength) {
            return false;
        }

        [$nonce, $tag] = explode('.', $cookieValue, 2);
        $expectedTag = $this->b64(hash_hmac('sha256', $nonce, $sessionHash, true));

        return hash_equals($expectedTag, $tag);
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
