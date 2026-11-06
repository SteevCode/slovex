<?php
/**
 * Solvex Report — Admin User Management
 * 
 * Manage Staff and Student Accounts:
 * - Create staff accounts with temporary password
 * - Enable / Disable any account (blocks login, never deletes records)
 * - Resend verification & trigger password reset tokens
 * - Logs all administrative actions to admin_log table
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';
require_once SOLVEX_ROOT . '/includes/maintenance.php';

init_session();
require_role('admin');

run_maintenance();

$user = current_user();
$pdo  = db();

$tab = get('tab', 'staff');

// Handle POST actions
if (is_post()) {
    csrf_verify();
    $action = post('action');

    // 1. Create Staff Account
    if ($action === 'create_staff') {
        $staffCode = strtoupper(trim(post('staff_code')));
        $fullName  = trim(post('full_name'));
        $email     = strtolower(trim(post('email')));
        $deptId    = (int) post('department_id');
        $tempPass  = post('temporary_password');

        if (empty($staffCode) || empty($fullName) || empty($email) || $deptId <= 0 || empty($tempPass)) {
            flash('danger', 'All fields are required to create a staff member account.');
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? OR staff_code = ?');
            $check->execute([$email, $staffCode]);
            if ($check->fetch()) {
                flash('danger', 'A user with this email or staff code already exists.');
            } else {
                $hash = password_hash($tempPass, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('
                    INSERT INTO users (staff_code, full_name, email, password_hash, role, department_id, status, email_verified_at, must_change_password, created_at)
                    VALUES (?, ?, ?, ?, \'staff\', ?, \'active\', NOW(), 1, NOW())
                ');
                $ins->execute([$staffCode, $fullName, $email, $hash, $deptId]);
                $newId = (int)$pdo->lastInsertId();

                admin_log($user['id'], 'create_staff', "staff:{$staffCode}", "Created staff account for {$fullName} ({$email}) in dept {$deptId}");
                flash('success', "Staff account for {$fullName} ({$staffCode}) created successfully.");
            }
        }
        redirect(base_url('admin/users.php?tab=staff'));
    }

    // 2. Toggle Account Status (active <-> disabled)
    if ($action === 'toggle_status') {
        $targetUserId = (int) post('user_id');
        $newStatus    = post('target_status') === 'disabled' ? 'disabled' : 'active';

        if ($targetUserId === (int)$user['id']) {
            flash('danger', 'You cannot disable your own administrator account.');
        } else {
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetUserId]);
            admin_log($user['id'], 'toggle_user_status', "user:{$targetUserId}", "Set status to {$newStatus}");
            flash('success', "User account status updated to {$newStatus}.");
        }
        redirect(base_url("admin/users.php?tab={$tab}"));
    }

    // 3. Reset Password / Generate Token
    if ($action === 'reset_password') {
        $targetUserId = (int) post('user_id');
        $token = generate_token();
        $tokenHash = hash_token($token);
        
        $pdo->prepare('INSERT INTO auth_tokens (user_id, type, token_hash, expires_at, created_at) VALUES (?, \'reset\', ?, NOW() + INTERVAL 2 HOUR, NOW())')
            ->execute([$targetUserId, $tokenHash]);
        
        $resetUrl = base_url("reset.php?token={$token}");
        admin_log($user['id'], 'reset_password_token', "user:{$targetUserId}", "Generated reset token link");
        flash('success', "Reset token created! Direct link: <code>" . e($resetUrl) . "</code>");
        redirect(base_url("admin/users.php?tab={$tab}"));
    }
}

// Fetch Staff or Students depending on tab
if ($tab === 'students') {
    $stmt = $pdo->query('
        SELECT u.id, u.register_no, u.full_name, u.email, u.status, u.created_at, u.email_verified_at,
               (SELECT COUNT(*) FROM complaints c WHERE c.student_id = u.id) AS complaint_count
        FROM users u
        WHERE u.role = \'student\'
        ORDER BY u.id DESC
        LIMIT 100
    ');
    $userList = $stmt->fetchAll();
} else {
    $stmt = $pdo->query('
        SELECT u.id, u.staff_code, u.full_name, u.email, u.status, u.created_at, u.must_change_password,
               dept.name AS department_name,
               (SELECT COUNT(*) FROM complaints c WHERE c.assigned_to = u.id) AS assigned_count
        FROM users u
        LEFT JOIN departments dept ON dept.id = u.department_id
        WHERE u.role IN (\'staff\', \'admin\')
        ORDER BY u.id DESC
    ');
    $userList = $stmt->fetchAll();
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();

$pageTitle = 'User Management';
$activeNav = 'users';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1"><i class="bi bi-people-fill text-primary me-2"></i>User Management</h1>
            <p class="text-muted small mb-0">Create staff, manage role permissions, and control security state.</p>
        </div>
        <?php if ($tab === 'staff'): ?>
            <button class="btn-solvex btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newStaffCollapse">
                <i class="bi bi-person-plus-fill me-1"></i> New Staff Member
            </button>
        <?php endif; ?>
    </div>

    <!-- Collapsible New Staff Form -->
    <?php if ($tab === 'staff'): ?>
        <div class="collapse mb-4" id="newStaffCollapse">
            <div class="card-solvex border-0 shadow-sm p-4 bg-light">
                <h5 class="h6 fw-bold text-navy mb-3"><i class="bi bi-person-plus text-primary me-1"></i> Register New Staff Member</h5>
                <form method="POST" action="<?= base_url('admin/users.php?tab=staff') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_staff">
                    
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Staff Code</label>
                        <input type="text" class="form-control-solvex form-control-sm" name="staff_code" placeholder="e.g. STF010" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" class="form-control-solvex form-control-sm" name="full_name" placeholder="Staff Name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Campus Email</label>
                        <input type="email" class="form-control-solvex form-control-sm" name="email" placeholder="staff@solvex.edu" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Department</label>
                        <select class="form-select-solvex form-select-sm" name="department_id" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Temporary Password</label>
                        <input type="text" class="form-control-solvex form-control-sm" name="temporary_password" value="Staff@2026" required>
                        <div class="text-muted" style="font-size:0.7rem;">Will force change on first login.</div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" class="btn-solvex btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#newStaffCollapse">Cancel</button>
                        <button type="submit" class="btn-solvex btn-primary btn-sm px-4">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($tab === 'staff') ? 'active fw-bold' : '' ?>" href="<?= base_url('admin/users.php?tab=staff') ?>">
                <i class="bi bi-briefcase me-1"></i> Staff &amp; Admins
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($tab === 'students') ? 'active fw-bold' : '' ?>" href="<?= base_url('admin/users.php?tab=students') ?>">
                <i class="bi bi-mortarboard me-1"></i> Student Accounts
            </a>
        </li>
    </ul>

    <!-- Users Table -->
    <div class="card-solvex border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4"><?= ($tab === 'staff') ? 'Code' : 'Register No' ?></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th><?= ($tab === 'staff') ? 'Department' : 'Verified' ?></th>
                            <th>Status</th>
                            <th>Workload</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userList as $u): ?>
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-primary">
                                    <?= e($u['staff_code'] ?? $u['register_no'] ?? '-') ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-navy"><?= e($u['full_name']) ?></div>
                                    <div class="text-muted small" style="font-size:0.75rem;">Created <?= format_date($u['created_at'], 'd M Y') ?></div>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <?php if ($tab === 'staff'): ?>
                                        <span class="badge bg-light text-dark border"><?= e($u['department_name'] ?? 'Super Admin') ?></span>
                                    <?php else: ?>
                                        <?= !empty($u['email_verified_at']) ? '<span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Yes</span>' : '<span class="text-warning-dark small"><i class="bi bi-clock-fill me-1"></i>Pending</span>' ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= ($u['status'] === 'active') ? 'bg-success' : 'bg-danger' ?> text-capitalize">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($tab === 'staff'): ?>
                                        <span class="small text-muted"><?= (int)$u['assigned_count'] ?> active</span>
                                    <?php else: ?>
                                        <span class="small text-muted"><?= (int)$u['complaint_count'] ?> reports</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Toggle Status -->
                                        <form method="POST" action="<?= base_url('admin/users.php?tab=' . $tab) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="target_status" value="<?= ($u['status'] === 'active') ? 'disabled' : 'active' ?>">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Disable user account" onclick="return confirm('Disable this account? User will not be able to log in.');">
                                                    Disable
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Enable user account">
                                                    Enable
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Password Reset Token -->
                                        <form method="POST" action="<?= base_url('admin/users.php?tab=' . $tab) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn-solvex btn-ghost btn-sm py-0 px-2" title="Create reset link">
                                                Reset
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

