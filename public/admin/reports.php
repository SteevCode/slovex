<?php
/**
 * Solvex Report — Admin CSV Reports Export
 * 
 * Filter by date range, department, category, and status.
 * Stream as sanitized CSV without exposing student PII unless explicitly authorized.
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

// Filter values
$fromDate    = get('from_date', date('Y-m-01'));
$toDate      = get('to_date', date('Y-m-d'));
$deptId      = (int) get('department_id', '0');
$categoryId  = (int) get('category_id', '0');
$status      = get('status', '');
$includePii  = get('include_pii', '0') === '1';
$exportMode  = get('export', '0') === '1';

$where = ['1=1'];
$params = [];

if (!empty($fromDate)) {
    $where[] = 'c.created_at >= :from_date';
    $params[':from_date'] = $fromDate . ' 00:00:00';
}
if (!empty($toDate)) {
    $where[] = 'c.created_at <= :to_date';
    $params[':to_date'] = $toDate . ' 23:59:59';
}
if ($deptId > 0) {
    $where[] = 'c.department_id = :dept_id';
    $params[':dept_id'] = $deptId;
}
if ($categoryId > 0) {
    $where[] = 'c.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
if (!empty($status)) {
    $where[] = 'c.status = :status';
    $params[':status'] = $status;
}

$whereSql = implode(' AND ', $where);

// Handle Direct CSV Export Download
if ($exportMode) {
    admin_log($user['id'], 'export_report', 'csv_download', "Exported complaints report (PII included: " . ($includePii ? 'YES' : 'NO') . ")");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="solvex_complaints_report_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');

    $headerRow = [
        'Reference',
        'Category',
        'Department',
        'Location',
        'Status',
        'Priority',
        'Created At',
        'Resolved At',
        'Closed At',
        'Days To Resolve',
    ];
    if ($includePii) {
        $headerRow[] = 'Reporter Name';
        $headerRow[] = 'Register No';
    }
    fputcsv($output, $headerRow);

    $sql = "
        SELECT c.reference, cat.name AS category_name, dept.name AS department_name,
               loc.name AS location_name, c.status, c.priority,
               c.created_at, c.resolved_at, c.closed_at,
               CASE 
                   WHEN c.closed_at IS NOT NULL THEN DATEDIFF(c.closed_at, c.created_at)
                   WHEN c.resolved_at IS NOT NULL THEN DATEDIFF(c.resolved_at, c.created_at)
                   ELSE DATEDIFF(NOW(), c.created_at)
               END AS days_to_resolve,
               u_rep.full_name AS reporter_name, u_rep.register_no AS student_register_no
        FROM complaints c
        JOIN categories cat ON cat.id = c.category_id
        JOIN departments dept ON dept.id = c.department_id
        LEFT JOIN locations loc ON loc.id = c.location_id
        JOIN users u_rep ON u_rep.id = c.student_id
        WHERE {$whereSql}
        ORDER BY c.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($r = $stmt->fetch()) {
        $row = [
            $r['reference'],
            $r['category_name'],
            $r['department_name'],
            $r['location_name'] ?? 'Main Campus',
            $r['status'],
            $r['priority'],
            $r['created_at'],
            $r['resolved_at'] ?? '',
            $r['closed_at'] ?? '',
            $r['days_to_resolve'],
        ];
        if ($includePii) {
            $row[] = $r['reporter_name'];
            $row[] = $r['student_register_no'];
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// Preview Query (Top 50)
$previewSql = "
    SELECT c.id, c.reference, c.title, c.status, c.priority,
           c.created_at, c.resolved_at, c.closed_at,
           cat.name AS category_name, dept.name AS department_name, loc.name AS location_name,
           u_rep.full_name AS reporter_name, u_rep.register_no AS student_register_no,
           CASE 
               WHEN c.closed_at IS NOT NULL THEN DATEDIFF(c.closed_at, c.created_at)
               WHEN c.resolved_at IS NOT NULL THEN DATEDIFF(c.resolved_at, c.created_at)
               ELSE DATEDIFF(NOW(), c.created_at)
           END AS days_to_resolve
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    JOIN users u_rep ON u_rep.id = c.student_id
    WHERE {$whereSql}
    ORDER BY c.created_at DESC
    LIMIT 50
";
$previewStmt = $pdo->prepare($previewSql);
$previewStmt->execute($params);
$previewRows = $previewStmt->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();
$categories  = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

$pageTitle = 'Resolution Reports';
$activeNav = 'reports';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1"><i class="bi bi-file-earmark-bar-graph-fill text-primary me-2"></i>Campus Resolution Reports</h1>
            <p class="text-muted small mb-0">Generate SLA performance reports and export historical CSV metrics.</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" action="<?= base_url('admin/reports.php') ?>" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" class="form-control-solvex form-control-sm" name="from_date" value="<?= e($fromDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" class="form-control-solvex form-control-sm" name="to_date" value="<?= e($toDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Department</label>
                    <select class="form-select-solvex form-select-sm" name="department_id">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $deptId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Category</label>
                    <select class="form-select-solvex form-select-sm" name="category_id">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="include_pii" value="1" id="checkPii" <?= $includePii ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold text-navy" for="checkPii">
                            <i class="bi bi-shield-lock-fill text-warning-dark me-1"></i> Include Reporter Identity (Audit Authorized)
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-solvex btn-secondary btn-sm px-3">
                            <i class="bi bi-eye me-1"></i> Update Preview
                        </button>
                        <button type="submit" name="export" value="1" class="btn-solvex btn-primary btn-sm px-4">
                            <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Download CSV
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Table -->
    <div class="card-solvex border-0 shadow-sm">
        <div class="card-header bg-white border-bottom p-3">
            <h5 class="h6 fw-bold text-navy mb-0">Preview (Showing top <?= count($previewRows) ?> matching rows)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Category</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Days to Resolve</th>
                            <th>Created</th>
                            <?php if ($includePii): ?>
                                <th class="pe-4">Reporter</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($previewRows)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No records match the selected date range and filter criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($previewRows as $r): ?>
                                <tr>
                                    <td class="ps-4 font-monospace text-primary fw-bold"><?= e($r['reference']) ?></td>
                                    <td><?= e($r['category_name']) ?></td>
                                    <td><?= e($r['department_name']) ?></td>
                                    <td><span class="status-pill <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                                    <td><span class="priority-tag <?= e($r['priority']) ?>"><?= e($r['priority']) ?></span></td>
                                    <td><strong><?= (int)$r['days_to_resolve'] ?>d</strong></td>
                                    <td><?= format_date($r['created_at'], 'd M Y') ?></td>
                                    <?php if ($includePii): ?>
                                        <td class="pe-4 small"><?= e($r['reporter_name']) ?> (<?= e($r['student_register_no']) ?>)</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

