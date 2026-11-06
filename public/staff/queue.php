<?php
/**
 * Solvex Report — Staff Queue
 * 
 * Zendesk and Jira Service Management inspired ticket queue:
 * - Department filtered queue for staff (Admin can filter by all depts)
 * - Sticky filter bar: status, priority, category, location, assignee, public/private, date range, search
 * - Quick tabs: Unassigned, Mine, Overdue, On hold
 * - Sorting by priority then age
 * - Overdue rows highlighted
 * - Pagination & URL-persisted filters
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
require_role('staff', 'admin');

$user = current_user();
$userId = (int) $user['id'];
$userRole = $user['role'];
$userDeptId = !empty($user['department_id']) ? (int) $user['department_id'] : null;
$pdo = db();

// Filter inputs
$q          = trim(get('q', ''));
$tab        = get('tab', 'all');
$status     = get('status', '');
$priority   = get('priority', '');
$categoryId = (int) get('category_id', '0');
$locationId = (int) get('location_id', '0');
$assignee   = get('assignee', '');
$visibility = get('visibility', '');
$fromDate   = get('from_date', '');
$toDate     = get('to_date', '');
$deptFilter = ($userRole === 'admin') ? (int) get('department_id', '0') : $userDeptId;
$page       = max(1, (int) get('page', '1'));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

// Base query builder
$where = ['1=1'];
$params = [];

// Department boundary constraint (and sensitive complaints restricted from staff)
if ($userRole === 'staff') {
    $where[] = 'c.department_id = :dept_id';
    $where[] = 'cat.sensitive = 0';
    $params[':dept_id'] = $userDeptId;
} elseif ($userRole === 'admin' && $deptFilter > 0) {
    $where[] = 'c.department_id = :dept_id';
    $params[':dept_id'] = $deptFilter;
}

// Quick Tabs logic
if ($tab === 'unassigned') {
    $where[] = 'c.assigned_to IS NULL AND c.status IN (\'pending\', \'submitted\', \'reopened\')';
} elseif ($tab === 'mine') {
    $where[] = 'c.assigned_to = :my_id';
    $params[':my_id'] = $userId;
} elseif ($tab === 'overdue') {
    $where[] = 'DATEDIFF(NOW(), c.created_at) > cat.target_days AND c.status NOT IN (\'closed\', \'rejected\', \'duplicate\')';
} elseif ($tab === 'on_hold') {
    $where[] = 'c.status = \'on_hold\'';
}

// Filter inputs override / supplement
if (!empty($q)) {
    $where[] = '(c.reference LIKE :q1 OR c.title LIKE :q2 OR c.description LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
if (!empty($status) && $tab !== 'on_hold') {
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
if ($locationId > 0) {
    $where[] = 'c.location_id IN (
        WITH RECURSIVE loc_tree AS (
            SELECT id FROM locations WHERE id = :location_id
            UNION ALL
            SELECT l.id FROM locations l JOIN loc_tree lt ON l.parent_id = lt.id
        ) SELECT id FROM loc_tree
    )';
    $params[':location_id'] = $locationId;
}
if ($assignee === 'me') {
    $where[] = 'c.assigned_to = :assignee_me';
    $params[':assignee_me'] = $userId;
} elseif ($assignee === 'unassigned') {
    $where[] = 'c.assigned_to IS NULL';
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

// Count total matching complaints
$countSql = "
    SELECT COUNT(*) 
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    WHERE {$whereSql}
";
$cntStmt = $pdo->prepare($countSql);
$cntStmt->execute($params);
$totalRecords = (int) $cntStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $perPage));

// Main complaints query sorted by Priority (critical > high > medium > low) then Age (created_at ASC)
$sql = "
    SELECT c.id, c.reference, c.title, c.description, c.status, c.priority, c.visibility,
           c.created_at, c.updated_at, c.resolved_at, c.closed_at, c.hold_until, c.assigned_to,
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
    ORDER BY 
        CASE c.priority
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END ASC,
        c.created_at ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Fetch filter options (Categories, Departments, Locations)
$categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$departments = ($userRole === 'admin') ? $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll() : [];
$locations = $pdo->query('SELECT id, name FROM locations WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();

// Quick tab counters
$tabCounts = [
    'all'        => 0,
    'unassigned' => 0,
    'mine'       => 0,
    'overdue'    => 0,
    'on_hold'    => 0
];

$baseDeptCond = ($userRole === 'staff') ? "AND c.department_id = {$userDeptId} AND cat.sensitive = 0" : ($deptFilter > 0 ? "AND c.department_id = {$deptFilter}" : "");
$tabCountSql = "
    SELECT
        COUNT(*) AS count_all,
        COUNT(CASE WHEN c.assigned_to IS NULL AND c.status IN ('submitted', 'reopened') THEN 1 END) AS count_unassigned,
        COUNT(CASE WHEN c.assigned_to = {$userId} THEN 1 END) AS count_mine,
        COUNT(CASE WHEN DATEDIFF(NOW(), c.created_at) > cat.target_days AND c.status NOT IN ('closed', 'rejected', 'duplicate') THEN 1 END) AS count_overdue,
        COUNT(CASE WHEN c.status = 'on_hold' THEN 1 END) AS count_on_hold
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    WHERE 1=1 {$baseDeptCond}
";
$tCounts = $pdo->query($tabCountSql)->fetch();
if ($tCounts) {
    $tabCounts['all']        = (int)$tCounts['count_all'];
    $tabCounts['unassigned'] = (int)$tCounts['count_unassigned'];
    $tabCounts['mine']       = (int)$tCounts['count_mine'];
    $tabCounts['overdue']    = (int)$tCounts['count_overdue'];
    $tabCounts['on_hold']    = (int)$tCounts['count_on_hold'];
}

$pageTitle = 'Department Queue';
$activeNav = 'queue';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- Top Summary & Actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1">
                <i class="bi bi-inbox-fill text-primary me-2"></i>Department Resolution Queue
            </h1>
            <p class="text-muted small mb-0">
                Logged in as <strong><?= e($user['full_name']) ?></strong> 
                (<?= strtoupper(e($user['role'])) ?><?= $userDeptId ? ' &bull; ' . e($departments[array_search($userDeptId, array_column($departments, 'id'))]['name'] ?? 'Assigned Dept') : '' ?>)
                &bull; Total <?= $totalRecords ?> tickets matching criteria
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('staff/queue.php') ?>" class="btn-solvex btn-ghost btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
            <?php if ($userRole === 'admin'): ?>
                <a href="<?= base_url('admin/dashboard.php') ?>" class="btn-solvex btn-secondary btn-sm">
                    <i class="bi bi-speedometer2 me-1"></i> Admin Console
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Tab Pills -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <?php
        $buildTabUrl = function($targetTab) {
            $p = $_GET;
            $p['tab'] = $targetTab;
            $p['page'] = 1;
            return '?' . http_build_query($p);
        };
        ?>
        <a href="<?= e($buildTabUrl('all')) ?>" class="btn btn-sm <?= ($tab === 'all') ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
            All (<?= $tabCounts['all'] ?>)
        </a>
        <a href="<?= e($buildTabUrl('unassigned')) ?>" class="btn btn-sm <?= ($tab === 'unassigned') ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
            <i class="bi bi-person-x me-1"></i> Unassigned (<?= $tabCounts['unassigned'] ?>)
        </a>
        <a href="<?= e($buildTabUrl('mine')) ?>" class="btn btn-sm <?= ($tab === 'mine') ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
            <i class="bi bi-person-check me-1"></i> Mine (<?= $tabCounts['mine'] ?>)
        </a>
        <a href="<?= e($buildTabUrl('overdue')) ?>" class="btn btn-sm <?= ($tab === 'overdue') ? 'btn-danger' : 'btn-light border text-danger' ?> rounded-pill px-3 fw-bold">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Overdue (<?= $tabCounts['overdue'] ?>)
        </a>
        <a href="<?= e($buildTabUrl('on_hold')) ?>" class="btn btn-sm <?= ($tab === 'on_hold') ? 'btn-warning text-dark' : 'btn-light border text-warning-dark' ?> rounded-pill px-3">
            <i class="bi bi-pause-circle me-1"></i> On Hold (<?= $tabCounts['on_hold'] ?>)
        </a>
    </div>

    <!-- Sticky Filter Bar -->
    <div class="card-solvex border-0 shadow-sm mb-4 sticky-top bg-white z-2" style="top: 70px;">
        <div class="card-body p-3">
            <form method="GET" action="<?= base_url('staff/queue.php') ?>" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                
                <div class="col-md-3 col-lg-2">
                    <input type="text" class="form-control-solvex form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search ref or text...">
                </div>

                <div class="col-6 col-md-2 col-lg-1">
                    <select class="form-select-solvex form-select-sm" name="status">
                        <option value="">Status: All</option>
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

                <div class="col-6 col-md-2 col-lg-1">
                    <select class="form-select-solvex form-select-sm" name="priority">
                        <option value="">Priority: All</option>
                        <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 col-lg-2">
                    <select class="form-select-solvex form-select-sm" name="category_id">
                        <option value="0">Category: All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($userRole === 'admin'): ?>
                <div class="col-6 col-md-2 col-lg-2">
                    <select class="form-select-solvex form-select-sm" name="department_id">
                        <option value="0">Dept: All</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-6 col-md-2 col-lg-1">
                    <select class="form-select-solvex form-select-sm" name="assignee">
                        <option value="">Assignee: All</option>
                        <option value="me" <?= $assignee === 'me' ? 'selected' : '' ?>>Assigned to Me</option>
                        <option value="unassigned" <?= $assignee === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 col-lg-1">
                    <select class="form-select-solvex form-select-sm" name="visibility">
                        <option value="">Visibility</option>
                        <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>Public</option>
                        <option value="private" <?= $visibility === 'private' ? 'selected' : '' ?>>Private</option>
                    </select>
                </div>

                <div class="col-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn-solvex btn-primary btn-sm px-3">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    <a href="<?= base_url('staff/queue.php') ?>" class="btn-solvex btn-secondary btn-sm" title="Clear Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Complaints Queue Table -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <?php if (empty($complaints)): ?>
                <div class="empty-state py-5 text-center">
                    <img src="<?= asset_url('img/empty-queue-clear.webp') ?>" alt="Queue clear" width="160" height="120" class="mb-3" loading="lazy">
                    <h3 class="h5 fw-bold text-navy">All clear! No tickets found</h3>
                    <p class="text-muted small">No complaints match your active filters or department queue.</p>
                    <a href="<?= base_url('staff/queue.php') ?>" class="btn-solvex btn-secondary btn-sm mt-2">
                        Reset All Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                        <thead class="table-light text-muted text-uppercase small">
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Problem Summary &amp; Location</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Days Open</th>
                                <th>Assignee</th>
                                <th>Updated</th>
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
                                    $chipClass = $isOverdue ? 'danger' : ((!$isDone && $daysOpen > ($targetDays / 2)) ? 'warning' : '');
                                ?>
                                <tr class="<?= $isOverdue ? 'table-warning' : '' ?>">
                                    <td class="ps-4">
                                        <a href="<?= base_url('staff/complaint.php?id=' . (int)$c['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= e($c['reference']) ?>
                                        </a>
                                        <?php if ($c['visibility'] === 'private'): ?>
                                            <span class="badge bg-light text-secondary border ms-1" style="font-size:0.65rem;" title="Private ticket"><i class="bi bi-lock-fill"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-navy text-truncate" style="max-width: 280px;" title="<?= e($c['title']) ?>">
                                            <?= e($c['title']) ?>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">
                                            <i class="bi bi-geo-alt me-1"></i> <?= e($c['location_name'] ?? 'Main Campus') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="text-primary"><?= category_icon_svg($c['category_icon'] ?? (int)($c['category_id'] ?? 1), 16) ?></span>
                                            <span class="fw-semibold"><?= e($c['category_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="priority-tag <?= e($c['priority']) ?>"><?= e($c['priority']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-pill <?= e($c['status']) ?>"><?= ucwords(str_replace('_', ' ', e($c['status']))) ?></span>
                                        <?php if ($c['status'] === 'on_hold' && !empty($c['hold_until'])): ?>
                                            <div class="text-muted" style="font-size: 0.70rem;">Until <?= format_date($c['hold_until'], 'd M') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="days-chip <?= $chipClass ?>">
                                            <i class="bi bi-clock-history"></i> <?= $daysOpen ?>d / <?= $targetDays ?>d
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($c['assigned_staff_name'])): ?>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="avatar-initials sm" style="width:24px;height:24px;font-size:10px;background-color:<?= avatar_color($c['assigned_staff_name']) ?>;">
                                                    <?= e(initials($c['assigned_staff_name'])) ?>
                                                </span>
                                                <span class="small"><?= e($c['assigned_staff_name']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= format_date($c['updated_at'], 'd M, H:i') ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="<?= base_url('staff/complaint.php?id=' . (int)$c['id']) ?>" class="btn-solvex btn-primary btn-sm py-1 px-2">
                                            Workspace <i class="bi bi-arrow-right"></i>
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
                        <div class="small text-muted">
                            Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalRecords ?> items)
                        </div>
                        <nav aria-label="Queue pagination">
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
