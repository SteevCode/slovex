<?php
/**
 * Solvex Report — Authentication Module
 * 
 * Handles session management, login, registration,
 * token verification, password reset, and access control.
 */

/**
 * Start a secure session with proper cookie settings.
 */
function init_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,           // Browser session
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Local dev automated capture session impersonation
    if (defined('APP_ENV') && APP_ENV === 'local' && !empty($_GET['demo_as']) && !is_logged_in()) {
        $demoRole = $_GET['demo_as'];
        if (in_array($demoRole, ['student', 'staff', 'admin'], true)) {
            $stmt = db()->prepare("SELECT id, full_name, role FROM users WHERE role = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$demoRole]);
            $u = $stmt->fetch();
            if ($u) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['user_role'] = $u['role'];
                $_SESSION['user_name'] = $u['full_name'];
                $_SESSION['last_activity'] = time();
            }
        }
    }

    // Idle timeout check
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
            flash('warning', 'Your session has expired. Please log in again.');
            return;
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Check if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Get the currently logged-in user's data.
 * @return array|null User data or null if not logged in.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    // Cache in a static var to avoid repeated DB queries
    static $user = null;
    static $cachedId = null;

    if ($user !== null && $cachedId === $_SESSION['user_id']) {
        return $user;
    }

    $stmt = db()->prepare('SELECT id, register_no, staff_code, full_name, email, role, department_id, status FROM users WHERE id = ? AND status = ?');
    $stmt->execute([$_SESSION['user_id'], 'active']);
    $user = $stmt->fetch();
    $cachedId = $_SESSION['user_id'];

    if (!$user) {
        // User was disabled/deleted since login
        session_unset();
        session_destroy();
        return null;
    }

    return $user;
}

/**
 * Require the user to be logged in. Redirects to login if not.
 */
function require_login(): void
{
    if (!is_logged_in() || !current_user()) {
        flash('warning', 'Please log in to continue.');
        redirect(base_url('login.php'));
    }
}

/**
 * Require the user to have one of the given roles.
 * @param string ...$roles Allowed roles
 */
function require_role(string ...$roles): void
{
    require_login();

    if (!in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        include SOLVEX_ROOT . '/public/errors/403.php';
        exit;
    }
}

/**
 * Get the home URL for a given role.
 */
function role_home(string $role): string
{
    return match ($role) {
        'student' => base_url('student/dashboard.php'),
        'staff'   => base_url('staff/queue.php'),
        'admin'   => base_url('admin/dashboard.php'),
        default   => base_url('login.php'),
    };
}

/**
 * Attempt to log in a user.
 * Returns an array with 'success' boolean and 'message' string.
 */
function attempt_login(string $identifier, string $password): array
{
    $pdo = db();
    $ip  = get_ip();

    // ── Throttle check ──────────────────────────────────────
    if (is_throttled($identifier, $ip)) {
        return [
            'success' => false,
            'message' => 'Too many login attempts. Please wait 15 minutes before trying again.',
        ];
    }

    // ── Find user by register_no, staff_code, or email ──────
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, password_hash, role, status, email_verified_at
         FROM users
         WHERE register_no = ? OR staff_code = ? OR email = ?
         LIMIT 1'
    );
    $stmt->execute([$identifier, $identifier, $identifier]);
    $user = $stmt->fetch();

    // Generic error for both unknown user and wrong password
    $genericError = 'Invalid credentials. Please check your details and try again.';

    if (!$user) {
        log_login_attempt($identifier, $ip, false);
        return ['success' => false, 'message' => $genericError];
    }

    if (!password_verify($password, $user['password_hash'])) {
        log_login_attempt($identifier, $ip, false);
        return ['success' => false, 'message' => $genericError];
    }

    // Check account status
    if ($user['status'] === 'pending') {
        return [
            'success' => false,
            'message' => 'Your account is pending verification. Please check your email.',
        ];
    }

    if ($user['status'] === 'disabled') {
        return [
            'success' => false,
            'message' => 'Your account has been disabled. Please contact the administrator.',
        ];
    }

    if ($user['email_verified_at'] === null && $user['role'] === 'student') {
        return [
            'success' => false,
            'message' => 'Please verify your email address before logging in.',
        ];
    }

    // ── Success ─────────────────────────────────────────────
    log_login_attempt($identifier, $ip, true);

    // Regenerate session ID to prevent fixation (if headers not yet sent)
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();

    return ['success' => true, 'message' => 'Login successful.'];
}

/**
 * Check if login attempts are throttled for an identifier or IP.
 */
function is_throttled(string $identifier, string $ip): bool
{
    $pdo = db();
    $cutoff = date('Y-m-d H:i:s', time() - (LOGIN_LOCKOUT_MINUTES * 60));

    // Check by identifier
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE identifier = ? AND attempted_at > ? AND success = 0'
    );
    $stmt->execute([$identifier, $cutoff]);
    if ($stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
        return true;
    }

    // Check by IP
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND attempted_at > ? AND success = 0'
    );
    $stmt->execute([$ip, $cutoff]);
    if ($stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
        return true;
    }

    return false;
}

/**
 * Log a login attempt.
 */
function log_login_attempt(string $identifier, string $ip, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (identifier, ip, attempted_at, success)
         VALUES (?, ?, NOW(), ?)'
    );
    $stmt->execute([$identifier, $ip, $success ? 1 : 0]);
}

/**
 * Destroy the current session completely.
 */
function logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}

/**
 * Register a new student user.
 * Returns ['success' => bool, 'message' => string, 'token' => string|null]
 */
function register_student(string $registerNo, string $email, string $password): array
{
    $pdo = db();

    // Verify against student master
    $stmt = $pdo->prepare('SELECT register_no, full_name FROM student_master WHERE register_no = ?');
    $stmt->execute([$registerNo]);
    $student = $stmt->fetch();

    // Generic message for security (don't reveal if register_no exists)
    $genericError = 'Registration could not be completed. Please verify your register number and try again.';

    if (!$student) {
        return ['success' => false, 'message' => $genericError, 'token' => null];
    }

    // Check if already registered
    $stmt = $pdo->prepare('SELECT id FROM users WHERE register_no = ?');
    $stmt->execute([$registerNo]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'An account with this register number already exists.', 'token' => null];
    }

    // Check email uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This email address is already in use.', 'token' => null];
    }

    // Create user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (register_no, full_name, email, password_hash, role, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $registerNo,
        $student['full_name'],
        $email,
        $passwordHash,
        'student',
        'pending',
    ]);
    $userId = $pdo->lastInsertId();

    // Generate verification token
    $token     = generate_token(32);
    $tokenHash = hash_token($token);
    $expiresAt = date('Y-m-d H:i:s', time() + VERIFY_TOKEN_EXPIRY);

    $stmt = $pdo->prepare(
        'INSERT INTO auth_tokens (user_id, type, token_hash, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, 'verify', $tokenHash, $expiresAt]);

    return ['success' => true, 'message' => 'Registration successful!', 'token' => $token];
}

/**
 * Verify an email verification token.
 */
function verify_email_token(string $token): array
{
    $pdo       = db();
    $tokenHash = hash_token($token);

    $stmt = $pdo->prepare(
        'SELECT at.id AS token_id, at.user_id, at.expires_at, at.used_at, u.status
         FROM auth_tokens at
         JOIN users u ON u.id = at.user_id
         WHERE at.token_hash = ? AND at.type = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash, 'verify']);
    $record = $stmt->fetch();

    if (!$record) {
        return ['success' => false, 'message' => 'Invalid verification link.'];
    }

    if ($record['used_at'] !== null) {
        return ['success' => false, 'message' => 'This verification link has already been used.'];
    }

    if (strtotime($record['expires_at']) < time()) {
        return ['success' => false, 'message' => 'This verification link has expired. Please register again.'];
    }

    // Activate the user
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET status = ?, email_verified_at = NOW() WHERE id = ?');
        $stmt->execute(['active', $record['user_id']]);

        $stmt = $pdo->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$record['token_id']]);

        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        error_log('Email verification failed: ' . $ex->getMessage());
        return ['success' => false, 'message' => 'Verification failed. Please try again.'];
    }

    return ['success' => true, 'message' => 'Your email has been verified! You can now log in.'];
}

/**
 * Create a password reset token.
 * Always returns a generic response for security.
 */
function create_reset_token(string $identifier): array
{
    $pdo = db();
    $genericMessage = 'If the details match an account, a reset link has been sent.';

    // Find user
    $stmt = $pdo->prepare(
        'SELECT id, email FROM users WHERE register_no = ? OR staff_code = ? OR email = ? LIMIT 1'
    );
    $stmt->execute([$identifier, $identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => true, 'message' => $genericMessage, 'token' => null, 'email' => null];
    }

    // Invalidate existing reset tokens for this user
    $stmt = $pdo->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL');
    $stmt->execute([$user['id'], 'reset']);

    // Generate new token
    $token     = generate_token(32);
    $tokenHash = hash_token($token);
    $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

    $stmt = $pdo->prepare(
        'INSERT INTO auth_tokens (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'], 'reset', $tokenHash, $expiresAt]);

    return [
        'success' => true,
        'message' => $genericMessage,
        'token'   => $token,
        'email'   => $user['email'],
    ];
}

/**
 * Verify and use a password reset token.
 */
function verify_reset_token(string $token): array
{
    $pdo       = db();
    $tokenHash = hash_token($token);

    $stmt = $pdo->prepare(
        'SELECT at.id AS token_id, at.user_id, at.expires_at, at.used_at
         FROM auth_tokens at
         WHERE at.token_hash = ? AND at.type = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash, 'reset']);
    $record = $stmt->fetch();

    if (!$record) {
        return ['success' => false, 'message' => 'Invalid reset link.', 'user_id' => null];
    }

    if ($record['used_at'] !== null) {
        return ['success' => false, 'message' => 'This reset link has already been used.', 'user_id' => null];
    }

    if (strtotime($record['expires_at']) < time()) {
        return ['success' => false, 'message' => 'This reset link has expired. Please request a new one.', 'user_id' => null];
    }

    return ['success' => true, 'message' => 'Token valid.', 'user_id' => $record['user_id'], 'token_id' => $record['token_id']];
}

/**
 * Reset a user's password using a valid token.
 */
function reset_password(int $userId, int $tokenId, string $newPassword): array
{
    $pdo = db();
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $userId]);

        // Mark token as used
        $stmt = $pdo->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$tokenId]);

        // Invalidate all other reset tokens for this user
        $stmt = $pdo->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL');
        $stmt->execute([$userId, 'reset']);

        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        error_log('Password reset failed: ' . $ex->getMessage());
        return ['success' => false, 'message' => 'Password reset failed. Please try again.'];
    }

    return ['success' => true, 'message' => 'Your password has been reset. You can now log in.'];
}
