# Solvex Report — Phase 1 Test Results

**Date of Execution:** 2026-10-31  
**PHP Version:** PHP 8.2+ (PDO MySQL)  
**Database:** MariaDB / MySQL 8.0 (InnoDB, utf8mb4)  
**Timezone:** Asia/Kolkata (`+05:30`)  
**Test Suite:** `tests/test_phase1.php`  
**Browser Visual Suite:** Antigravity Browser Subagent (1280x800, 768x1024, 360x740)

---

## Summary Matrix

| # | Test Case Description | Status | Evidence / Verification Method |
|---|----------------------|:------:|--------------------------------|
| **1** | Fresh install on a clean database | **PASS** | 13 tables created (`schema.sql`); 6 departments, 10 categories, 43 locations, 30 student records seeded (`seed.sql`). |
| **2** | Registration with non-existent register number | **PASS** | Registration for `9999ZZ999` rejected with generic message: `"Registration could not be completed. Please verify your register number and try again."` Zero student directory disclosure. |
| **3** | Valid registration, email token activation, reuse & expiry | **PASS** | Account created with `status = 'pending'`. Link with 32-byte token sent & logged; token verified & user transitioned to `status = 'active'`; re-use failed (`"already been used"`); expired token rejected (`"expired"`). |
| **4** | Login: identical error message, brute force throttling, session regeneration | **PASS** | Unknown user & wrong password returned identical generic string: `"Invalid credentials. Please check your details and try again."`. 5 consecutive failures triggered lockout (`"Too many login attempts. Please wait 15 minutes before trying again."`). Valid login regenerated session ID. |
| **5** | Password reset: token expiry, single use, invalidation of old password | **PASS** | Generic reset response returned. Reset token verified; new password saved; previous password rejected on login; reusing token returned `"already been used"`; expired token returned `"expired"`. |
| **6** | CSRF Protection on POST requests | **PASS** | POST request without `csrf_token` rejected with HTTP 403. POST with matching token passed `csrf_verify()`. |
| **7** | Student role access guard | **PASS** | Authenticated student attempting to access `/staff/queue.php` and `/admin/dashboard.php` receives HTTP 403 Forbidden. |
| **8** | XSS output escaping & credential security | **PASS** | `<script>alert(1)</script>` safely rendered as `&lt;script&gt;alert(1)&lt;/script&gt;` via `e()`. Static scan of public templates revealed 0 database secrets or raw hashes. |
| **9** | Responsive visual verification (360px, 768px, 1280px) | **PASS** | Landing page, Login page, and Register page tested via browser automation across mobile (360px), tablet (768px), and desktop (1280px). No visual overlaps or horizontal scrolling. |

---

## Detailed Test Logs

### Test 1: Clean Database & Schema Execution
* Command executed: `C:\xampp\php\php.exe scratch/init_db.php`
* Results:
  * Database `solvex_report` created with character set `utf8mb4` and collation `utf8mb4_unicode_ci`.
  * All 13 tables created with foreign key constraints (`ON DELETE RESTRICT`):
    `departments`, `users`, `student_master`, `categories`, `locations`, `complaints`, `complaint_images`, `complaint_events`, `comments`, `affected`, `notifications`, `auth_tokens`, `login_attempts`.
  * 6 departments, 10 categories with default priorities/visibilities, 43 campus location nodes, and 30 sample student records inserted.

### Test 2: Unlisted Student Register Number
* Payload: `register_no = '9999ZZ999'`, `email = 'fake@test.com'`, `password = 'Password123!'`
* Output: `success = false`, `message = 'Registration could not be completed. Please verify your register number and try again.'`
* Result: Generic message avoids confirming whether a register number is valid.

### Test 3: Valid Registration, Token Activation, Token Reuse, and Expiration
* Payload: `register_no = '2024CS001'`, `email = '2024cs001@college.edu'`, `password = 'StudentSecure2024!'`
* Step 1: User created with `status = 'pending'`, `email_verified_at = NULL`.
* Step 2: 32-byte hex token generated; SHA-256 hash stored in `auth_tokens`.
* Step 3: `verify_email_token($token)` executed &rarr; User updated to `status = 'active'`, `email_verified_at = NOW()`, `token.used_at = NOW()`.
* Step 4: Secondary execution of `verify_email_token($token)` &rarr; Rejected with: `"This verification link has already been used."`
* Step 5: Artificial expiration simulated (`expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)`) &rarr; Rejected with: `"This verification link has expired. Please register again."`

### Test 4: Login Authentication & Throttling
* Non-existent user check: `identifier = 'NONEXISTENT_USER'`, `password = 'WrongPass123!'` &rarr; `"Invalid credentials. Please check your details and try again."`
* Existing user wrong password check: `identifier = '2024CS001'`, `password = 'DefinitelyWrongPass!'` &rarr; `"Invalid credentials. Please check your details and try again."`
* Throttling check: 5 consecutive invalid login attempts logged in `login_attempts` table.
  * 6th attempt output: `success = false`, `message = 'Too many login attempts. Please wait 15 minutes before trying again.'`
* Valid login: `session_regenerate_id(true)` executed cleanly, `$_SESSION['user_id']` set, `$_SESSION['user_role'] = 'student'`.

### Test 5: Password Reset Lifecycle
* Request reset: `identifier = '2024cs001@college.edu'` &rarr; Returns generic confirmation `"If the details match an account, a reset link has been sent."`
* Token hashed into `auth_tokens` with 30-minute expiry (`type = 'reset'`).
* New password set: `NewStudentPass2025!`. `users.password_hash` updated; reset token marked used.
* Verification: Attempting login with old password `StudentSecure2024!` fails. Attempting login with new password succeeds. Re-using reset token rejected.

### Test 6: Cross-Site Request Forgery (CSRF)
* Missing token test: POST request without `$_POST['csrf_token']` intercepted and rejected with HTTP 403 Forbidden.
* Valid token test: Token matching `$_SESSION['csrf_token']` validated via `hash_equals()`. Token rotated on state change.

### Test 7: Role Authorization Enforcement
* Student session initialized (`$_SESSION['user_role'] = 'student'`).
* Request to `/staff/queue.php`: Denied (requires `['staff', 'admin']`), HTTP 403 returned.
* Request to `/admin/dashboard.php`: Denied (requires `['admin']`), HTTP 403 returned.

### Test 8: XSS Prevention & Information Disclosure
* XSS vector: `<script>alert(1)</script>` passed through `e()` &rarr; Safely converted to `&lt;script&gt;alert(1)&lt;/script&gt;`.
* Source code inspection of all public templates confirmed zero sensitive database credentials, API keys, or raw password hashes exposed.

### Test 9: Responsive Layout Verification
Browser automated session recorded and verified:
1. **Desktop (1280px × 800px):**
   * Landing page: Full navbar with logo and CTA buttons, 2-column hero with preview ticket card, 3-column live stats counter, 3-column "How It Works" steps, 4-card "Why Solvex" grid, full emergency banner footer.
   * Login: Centered auth card (460px max-width) with clean typography and form controls.
   * Register: 3-step progress bar cleanly distributed across top of card.
2. **Tablet (768px × 1024px):**
   * Grid collapses gracefully to 3-column tablet view for stats and steps.
   * Action buttons re-wrap without text clipping.
3. **Mobile (360px × 740px):**
   * Navbar collapses to compact mobile header.
   * Hero headline scales down smoothly using `clamp()` font sizes.
   * Stat counters and feature cards stack into a single column with 100% width and 44px touch targets.
   * Multi-step registration wizard displays one step at a time with clear "Continue" and "Back" touch buttons.
