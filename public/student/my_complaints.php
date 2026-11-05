<?php
/**
 * Solvex Report — My Complaints List
 * 
 * Displays all complaints reported by the logged-in student with:
 * - Status tabs (All, Open, In Progress, Resolved, Closed)
 * - Text search (reference, title, description)
 * - Priority and date sorting
 * - Single JOIN query avoiding N+1 overhead
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';

init_session();
require_role('student');

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

// Filter parameters
$statusTab = get('status', 'all');
$search    = trim(get('q', ''));
$sort      = get('sort', 'newest');

// Base query
$sql = '
    SELECT c.id, c.reference, c.title, c.description, c.status, c.priority, c.visibility,
           c.created_at, c.updated_at, c.resolved_at,
           cat.name AS category_name, cat.icon AS category_icon, cat.target_days,
           dept.name AS department_name,
           loc.name AS location_name,
           (SELECT COUNT(*) FROM comments com WHERE com.complaint_id = c.id AND com.is_internal = 0) AS comment_count,
           (SELECT COUNT(*) FROM complaint_images ci WHERE ci.complaint_id = c.id) AS image_count,
           (SELECT COUNT(*) FROM affected a WHERE a.complaint_id = c.id) AS affected_count
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    WHERE c.student_id = :student_id
';

$params = ['student_id' => $userId];

// Status Tab filter
if ($statusTab === 'open') {
    $sql .= " AND c.status IN ('submitted', 'assigned')";
} elseif ($statusTab === 'in_progress') {
    $sql .= " AND c.status IN ('in_progress', 'on_hold', 'reopened')";
} elseif ($statusTab === 'resolved') {
    $sql .= " AND c.status = 'resolved'";
} elseif ($statusTab === 'closed') {
    $sql .= " AND c.status IN ('closed', 'rejected', 'duplicate')";
}

// Search filter
if (!empty($search)) {
    $sql .= ' AND (c.reference LIKE :search_ref OR c.title LIKE :search_title OR c.description LIKE :search_desc)';
    $like = '%' . $search . '%';
    $params['search_ref'] = $like;
    $params['search_title'] = $like;
    $params['search_desc'] = $like;
}

// Sorting
$orderBy = match ($sort) {
    'oldest'   => 'c.created_at ASC',
    'priority' => 'FIELD(c.priority, \'critical\', \'high\', \'medium\', \'low\'), c.created_at DESC',
    default    => 'c.created_at DESC',
};

$sql .= ' ORDER BY ' . $orderBy;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$pageTitle = 'My Reported Complaints';
$activeNav = 'dashboard';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">

    <!-- Header & Action -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1">My Reported Complaints</h1>
            <p class="text-muted small mb-0">Track live ageing, department updates, and resolution status for your reports.</p>
        </div>
        <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Report a Problem
        </a>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="<?= base_url('student/my_complaints.php') ?>" class="row g-3 align-items-center">
                <input type="hidden" name="status" value="<?= e($statusTab) ?>">

                <!-- Search Input -->
                <div class="col-md-6 col-lg-7">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input 
                            type="text" 
                            name="q" 
                            class="form-control-solvex form-control border-start-0 ps-0" 
                            placeholder="Search by reference (e.g. SVX-2024-00001) or title..." 
                            value="<?= e($search) ?>"
                        >
                    </div>
                </div>

                <!-- Sort Dropdown -->
                <div class="col-md-4 col-lg-3">
                    <select name="sort" class="form-select-solvex" onchange="this.form.submit()">
                        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Sort: Newest First</option>
                        <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Sort: Oldest First</option>
                        <option value="priority" <?= ($sort === 'priority') ? 'selected' : '' ?>>Sort: Highest Priority</option>
                    </select>
                </div>

                <!-- Submit / Clear -->
                <div class="col-md-2 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn-solvex btn-primary w-100">Filter</button>
                    <?php if (!empty($search) || $sort !== 'newest' || $statusTab !== 'all'): ?>
                        <a href="<?= base_url('student/my_complaints.php') ?>" class="btn-solvex btn-secondary" title="Reset Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Status Filter Tabs -->
        <div class="card-footer bg-white border-top px-4 py-2">
            <ul class="nav nav-pills small gap-2">
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($statusTab === 'all') ? 'active' : '' ?>" href="<?= base_url('student/my_complaints.php?status=all&q=' . urlencode($search) . '&sort=' . urlencode($sort)) ?>">
                        All
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($statusTab === 'open') ? 'active' : '' ?>" href="<?= base_url('student/my_complaints.php?status=open&q=' . urlencode($search) . '&sort=' . urlencode($sort)) ?>">
                        Open / Submitted
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($statusTab === 'in_progress') ? 'active' : '' ?>" href="<?= base_url('student/my_complaints.php?status=in_progress&q=' . urlencode($search) . '&sort=' . urlencode($sort)) ?>">
                        In Progress
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($statusTab === 'resolved') ? 'active' : '' ?>" href="<?= base_url('student/my_complaints.php?status=resolved&q=' . urlencode($search) . '&sort=' . urlencode($sort)) ?>">
                        Resolved (Verify)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($statusTab === 'closed') ? 'active' : '' ?>" href="<?= base_url('student/my_complaints.php?status=closed&q=' . urlencode($search) . '&sort=' . urlencode($sort)) ?>">
                        Closed
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Complaints Cards / Feed -->
    <?php if (empty($complaints)): ?>
        <div class="card-solvex border-0 shadow-sm p-5 text-center">
            <div class="empty-icon mx-auto mb-3">
                <i class="bi bi-search fs-2 text-muted"></i>
            </div>
            <h4 class="h5 fw-bold text-navy">No complaints found</h4>
            <p class="text-muted small">Try broadening your search term or tab filter.</p>
            <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-primary mx-auto mt-2">
                <i class="bi bi-plus-lg me-1"></i> Report a Problem
            </a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($complaints as $c): ?>
                <?php
                    $daysOpen = days_since($c['created_at']);
                    $targetDays = (int)$c['target_days'];
                    $chipClass = ($daysOpen > $targetDays) ? 'danger' : (($daysOpen > ($targetDays / 2)) ? 'warning' : '');
                ?>
                <div class="card-solvex border-0 shadow-sm p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                    <?= e($c['reference']) ?>
                                </a>
                                <span class="status-pill <?= e($c['status']) ?>"><?= ucwords(str_replace('_', ' ', e($c['status']))) ?></span>
                                <span class="priority-tag <?= e($c['priority']) ?>"><?= e($c['priority']) ?></span>
                                <?php if ($c['visibility'] === 'private'): ?>
                                    <span class="visibility-badge private"><i class="bi bi-lock-fill"></i> Private</span>
                                <?php else: ?>
                                    <span class="visibility-badge public"><i class="bi bi-globe"></i> Public</span>
                                <?php endif; ?>
                                <span class="days-chip <?= $chipClass ?>">
                                    <i class="bi bi-clock-history"></i> <?= $daysOpen ?>d open
                                </span>
                            </div>

                            <h4 class="h5 fw-bold text-navy mb-2">
                                <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="text-navy text-decoration-none hover-teal">
                                    <?= e($c['title']) ?>
                                </a>
                            </h4>

                            <p class="text-secondary small mb-3 text-truncate-2" style="max-width: 750px;">
                                <?= e(truncate($c['description'], 180)) ?>
                            </p>

                            <div class="d-flex align-items-center gap-3 text-muted small flex-wrap">
                                <span><i class="bi <?= e($c['category_icon']) ?> me-1 text-primary"></i> <?= e($c['category_name']) ?></span>
                                <span>&bull;</span>
                                <span><i class="bi bi-building me-1"></i> <?= e($c['department_name']) ?></span>
                                <span>&bull;</span>
                                <span><i class="bi bi-geo-alt me-1"></i> <?= e($c['location_name'] ?? 'Campus') ?></span>
                                <?php if ((int)$c['image_count'] > 0): ?>
                                    <span>&bull;</span>
                                    <span><i class="bi bi-paperclip me-1"></i> <?= (int)$c['image_count'] ?> photos</span>
                                <?php endif; ?>
                                <?php if ((int)$c['comment_count'] > 0): ?>
                                    <span>&bull;</span>
                                    <span><i class="bi bi-chat-left-text me-1"></i> <?= (int)$c['comment_count'] ?> comments</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex flex-md-column align-items-end justify-content-between h-100 flex-shrink-0">
                            <span class="text-muted small mb-3">Updated <?= format_date($c['updated_at']) ?></span>
                            <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="btn-solvex btn-secondary btn-sm">
                                View Timeline <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
