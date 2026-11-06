<?php
/**
 * Solvex Report — Admin Complaints Management
 * 
 * Global complaints view across all campus departments with advanced filters:
 * - Filter by status, priority, category, department, visibility, date range, search
 * - Instant link to Staff/Admin complaint workspace
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

$user = current_user();
$pdo  = db();

// Filter parameters
$q          = trim(get('q', ''));
$status     = get('status', '');
$priority   = get('priority', '');
$categoryId = (int) get('category_id', '0');
$deptId     = (int) get('department_id', '0');
$visibility = get('visibility', '');
$fromDate   = get('from_date', '');
$toDate     = get('to_date', '');
$sort       = get('sort', 'newest');
$page       = max(1, (int) get('page', '1'));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if (!empty($q)) {
    $where[] = '(c.reference LIKE :q1 OR c.title LIKE :q2 OR c.description LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
if (!empty($status)) {
    $where[] = 'c.status = :status';
    $params[':status'] = $status;
}
if (!empty($priority)) {
    $where[] = 'c.priority = :priority';
    $params[':priority'] = $priority;
}
if ($categoryId > 0) {
    $where[] = 'c.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
if ($deptId > 0) {
    $where[] = 'c.department_id = :dept_id';
    $params[':dept_id'] = $deptId;
}
if ($visibility === 'public' || $visibility === 'private') {
    $where[] = 'c.visibility = :visibility';
    $params[':visibility'] = $visibility;
}
if (!empty($fromDate)) {
    $where[] = 'c.created_at >= :from_date';
    $params[':from_date'] = $fromDate . ' 00:00:00';
}
if (!empty($toDate)) {
    $where[] = 'c.created_at <= :to_date';
    $params[':to_date'] = $toDate . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $perPage));

// Sort
$orderClause = match ($sort) {
    'oldest'    => 'c.created_at ASC',
    'priority'  => "CASE c.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END ASC, c.created_at DESC",
    'days_open' => 'c.created_at ASC',
    default     => 'c.created_at DESC',
};

// Fetch complaints
$sql = "
    SELECT c.id, c.reference, c.title, c.status, c.priority, c.visibility,
           c.created_at, c.updated_at, c.resolved_at, c.closed_at, c.assigned_to,
           cat.name AS category_name, cat.icon AS category_icon, cat.target_days,
           dept.name AS department_name,
           loc.name AS location_name,
           u_staff.full_name AS assigned_staff_name
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    LEFT JOIN users u_staff ON u_staff.id = c.assigned_to
    WHERE {$whereSql}
    ORDER BY {$orderClause}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Dropdowns
$categories  = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();

$pageTitle = 'Manage All Complaints';
$activeNav = 'complaints';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1"><i class="bi bi-list-task text-primary me-2"></i>Campus Complaints Management</h1>
            <p class="text-muted small mb-0">Total <strong><?= $totalRecords ?></strong> complaints across all campus facilities.</p>
        </div>
        <a href="<?= base_url('admin/reports.php') ?>" class="btn-solvex btn-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export to CSV
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= base_url('admin/complaints.php') ?>" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" class="form-control-solvex form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search reference, title...">
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select-solvex form-select-sm" name="status">
                        <option value="">Status: All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="assigned" <?= $status === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="reopened" <?= $status === 'reopened' ? 'selected' : '' ?>>Reopened</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select-solvex form-select-sm" name="department_id">
                        <option value="0">Dept: All</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $deptId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select-solvex form-select-sm" name="category_id">
                        <option value="0">Category: All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <select class="form-select-solvex form-select-sm" name="priority">
                        <option value="">Priority</option>
                        <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <select class="form-select-solvex form-select-sm" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                        <option value="days_open" <?= $sort === 'days_open' ? 'selected' : '' ?>>Days Open</option>
                    </select>
                </div>
                <div class="col-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn-solvex btn-primary btn-sm px-3">Filter</button>
                    <a href="<?= base_url('admin/complaints.php') ?>" class="btn-solvex btn-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <?php if (empty($complaints)): ?>
                <div class="empty-state py-5 text-center">
                    <h4 class="h6 fw-bold text-navy">No complaints found</h4>
                    <p class="text-muted small">Try broadening your filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                        <thead class="table-light text-muted text-uppercase small">
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Problem Summary</th>
                                <th>Category</th>
                                <th>Department</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Days Open</th>
                                <th>Assignee</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <?php
                                    $daysOpen = days_open($c);
                                    $targetDays = (int)$c['target_days'];
                                    $isDone = in_array($c['status'], ['closed', 'rejected', 'duplicate'], true);
                                    $isOverdue = (!$isDone && $daysOpen > $targetDays);
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <a href="<?= base_url('staff/complaint.php?id=' . $c['id']) ?>" class="font-monospace text-primary fw-bold text-decoration-none">
                                            <?= e($c['reference']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-navy text-truncate" style="max-width: 240px;"><?= e($c['title']) ?></div>
                                        <div class="text-muted small" style="font-size:0.75rem;"><i class="bi bi-geo-alt me-1"></i><?= e($c['location_name'] ?? 'Main Campus') ?></div>
                                    </td>
                                    <td><i class="bi <?= e($c['category_icon']) ?> text-primary me-1"></i><?= e($c['category_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($c['department_name']) ?></span></td>
                                    <td><span class="priority-tag <?= e($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
                                    <td><span class="status-pill <?= e($c['status']) ?>"><?= ucwords(str_replace('_', ' ', e($c['status']))) ?></span></td>
                                    <td>
                                        <span class="days-chip <?= $isOverdue ? 'danger' : '' ?>">
                                            <?= $daysOpen ?>d
                                        </span>
                                    </td>
                                    <td><?= e($c['assigned_staff_name'] ?? 'Unassigned') ?></td>
                                    <td class="pe-4 text-end">
                                        <a href="<?= base_url('staff/complaint.php?id=' . $c['id']) ?>" class="btn-solvex btn-ghost btn-sm py-1 px-2">
                                            Open <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center">
                        <div class="small text-muted">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> items)</div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $pageUrl = function($p) {
                                    $params = $_GET;
                                    $params['page'] = $p;
                                    return '?' . http_build_query($params);
                                };
                                ?>
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
                                </li>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                                        <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                                        </li>
                                    <?php elseif (abs($p - $page) == 3): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

