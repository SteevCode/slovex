# Architectural & Technical Decisions — Solvex Report

This document records the foundational architectural, security, and design decisions adopted during Phase 1 of Solvex Report.

---

## 1. Stack & Architecture Constraints

* **Runtime & Portability:** Native PHP 8+ without frameworks (e.g. Laravel/Symfony) or Node.js build processes. Designed to run smoothly on standard local XAMPP installations, Apache/MariaDB environments, and free PHP hosting (e.g., InfinityFree, 000webhost, shared cPanel).
* **Database Access:** Standardized on `PDO` with:
  * Strict error mode (`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`)
  * Emulated prepares disabled (`PDO::ATTR_EMULATE_PREPARES => false`) to enforce genuine prepared statements at the DBMS level.
  * Explicit Asia/Kolkata connection offset: `SET time_zone = '+05:30'` executed on every connection.
  * Positional placeholders `?` used where parameters are evaluated against multiple columns to comply with non-emulated PDO query rules.
* **Separation of Web Root:**
  * Public web root placed in `public/` (containing index, auth endpoints, errors, and static assets).
  * Backend business logic, database credentials, application configuration, storage uploads, and logs kept strictly outside the web root (`includes/`, `storage/`, `config.local.php`).

---

## 2. Security Decisions

* **Authentication & Credentials:**
  * Passwords hashed using standard `password_hash()` with `PASSWORD_DEFAULT` (Bcrypt).
  * Fixed-time string comparisons (`hash_equals()`) for all cryptographic tokens to prevent timing attacks.
  * Identical error messages ("Invalid credentials. Please check your details and try again.") for non-existent identifiers and incorrect passwords to prevent account enumeration.
* **Brute-Force & Denial-of-Service Defense:**
  * Throttling via `login_attempts` table tracking both identifier and remote IP.
  * Threshold: 5 failed attempts within 15 minutes triggers an immediate temporary lockout message.
  * Legitimate logins immediately invoke `session_regenerate_id(true)` to mitigate session fixation attacks.
* **CSRF Defense:**
  * Cryptographically secure 32-byte pseudo-random tokens stored in `$_SESSION['csrf_token']`.
  * Injected via `csrf_field()` and verified on every state-changing HTTP POST request via `csrf_verify()`.
  * Replayed tokens rejected; regenerated after successful validation.
* **Output Escaping & XSS:**
  * Universal `e()` helper wrapping `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` for all dynamic data inserted into HTML.

---

## 3. Student Registration & Privacy Model

* **Student Master Pre-Verification:**
  * Students can only register if their University Register Number exists in `student_master`.
  * If a student enters an unlisted register number, the application returns a generic message to prevent reconnaissance of the student directory.
* **Two-Stage Activation (Email Verification):**
  * Newly registered accounts are assigned `status = 'pending'`.
  * Cryptographic 32-byte raw token generated, but **only its SHA-256 hash** (`token_hash`) is persisted in `auth_tokens`.
  * Expiry: 24 hours. Single-use only (flagged with `used_at = NOW()`).
* **Password Recovery (Forgot/Reset):**
  * Generic confirmation returned ("If the details match an account, a reset link has been sent") regardless of whether the email or identifier was found.
  * Reset tokens expire in 30 minutes. Upon successful password reset, all other active reset tokens for that user ID are invalidated immediately.

---

## 4. Brand & Design System Decisions

* **Civic, Trustworthy, Modern Aesthetic:**
  * Custom vector SVG identity crafted: Map-pin geometry enclosing an active checkmark (from reported to solved).
  * Color tokens: Primary Teal (`#0E9F8E`), Deep Navy (`#0B2545`), Accent Amber (`#F59E0B`).
  * Typography: Plus Jakarta Sans for high-impact geometric headings, Inter for crisp, accessible UI density.
  * WCAG AA compliant contrast ratios and high-visibility focus rings.
  * Native CSS variables powering cards, status pills, priority tags, and responsive layouts.

---

## 5. Phase 2 — Complaint Core Decisions

* **Visibility is decided in one function:** `can_view_complaint()`. Failed checks always render HTTP **404** (never 403) so private/sensitive tickets cannot be enumerated.
* **Reporter identity** is stripped by `sanitize_complaint_for_view()` for anyone failing `can_see_reporter()`. Peers see "Anonymous student" and never receive GPS, room, register number, or email.
* **Status changes** go through `change_status()` with a single transition matrix. Students may only `resolved → closed` (sets `closed_by=student`) and `resolved → reopened` (reason required). Updates use `WHERE id=? AND status=?` so a concurrent edit changes zero rows and aborts.
* **Events are append-only.** There is no UPDATE/DELETE path on `complaint_events`.
* **Uploads** are re-encoded with GD (EXIF/GPS stripped), stored under random names in `storage/uploads` (outside the web root), and served only through `image.php` after `can_view_complaint()`.
* **AJAX CSRF** (`csrf_verify_ajax`) validates the token without rotating it, so in-page fetch actions (affected toggle, notification mark-read) keep working.
* **"Urgent" is a hint only:** it does not override category default priority; it is recorded on the created event and prefixed on staff notifications.
