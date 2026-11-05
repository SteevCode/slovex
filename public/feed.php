<?php
/**
 * Solvex Report — Public Complaint Feed
 * 
 * Civic card-based overview of public campus problems:
 * - Photo thumbnail or category icon
 * - Status pill, days-open ageing chip, category & location badges
 * - "I Am Also Affected" toggle button (AJAX POST with CSRF)
 * - Multi-filter: category, location, status, and sorting
 * - Pagination (12 cards per page)
 * - Strict reporter anonymity: no PII or GPS coordinates exposed
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();
require_login();

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

// Filters & Pagination
$catFilter = (int) get('category', '0');
$locFilter = (int) get('location', '0');
$statusFilter = get('status', '');
$sort = get('sort', 'newest');
$page = max(1, (int) get('page', '1'));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Base conditions: ONLY public complaints
$where = ['c.visibility = \'public\'', 'cat.sensitive = 0'];
$params = [];

if ($catFilter > 0) {
    $where[] = 'c.category_id = :cat_id';
    $params['cat_id'] = $catFilter;
}

if ($locFilter > 0) {
    $where[] = 'c.location_id = :loc_id';
    $params['loc_id'] = $locFilter;
}

if (!empty($statusFilter)) {
    $where[] = 'c.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = implode(' AND ', $where);

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c JOIN categories cat ON cat.id = c.category_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages   = max(1, (int) ceil($totalRecords / $perPage));

// Sorting
$orderSql = match ($sort) {
    'longest'       => 'c.created_at ASC',
    'most_affected' => 'affected_count DESC, c.created_at DESC',
    'priority'      => 'FIELD(c.priority, \'critical\', \'high\', \'medium\', \'low\'), c.created_at DESC',
    default         => 'c.created_at DESC',
};

// Main Query with JOINs and Subqueries for zero N+1
$sql = "
    SELECT c.id, c.reference, c.title, c.description, c.status, c.priority, c.created_at,
           c.student_id,
           cat.name AS category_name, cat.icon AS category_icon, cat.target_days,
           dept.name AS department_name,
           loc.name AS location_name,
           (SELECT ci.id FROM complaint_images ci WHERE ci.complaint_id = c.id LIMIT 1) AS thumb_image_id,
           (SELECT COUNT(*) FROM affected a WHERE a.complaint_id = c.id) AS affected_count,
           (SELECT COUNT(*) FROM affected a_user WHERE a_user.complaint_id = c.id AND a_user.user_id = :curr_user_id) AS is_user_affected
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    WHERE {$whereSql}
    ORDER BY {$orderSql}
    LIMIT {$perPage} OFFSET {$offset}
";

$params['curr_user_id'] = $userId;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Fetch Categories for filter dropdown
$allCategories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
// Fetch Locations for filter dropdown
$allLocations = $pdo->query('SELECT id, name FROM locations WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();

$pageTitle = 'Campus Problem Feed';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">

    <!-- Top Headline -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1">Public Campus Feed</h1>
            <p class="text-muted small mb-0">Browse open and resolved issues reported across campus blocks, hostels, and facilities.</p>
        </div>
        <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Report a Problem
        </a>
    </div>

    <!-- Filter & Sort Controls -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="<?= base_url('feed.php') ?>" class="row g-3 align-items-center">

                <!-- Category Filter -->
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                    <select name="category" class="form-select-solvex" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= ($catFilter === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Location Filter -->
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Campus Area</label>
                    <select name="location" class="form-select-solvex" onchange="this.form.submit()">
                        <option value="0">All Locations</option>
                        <?php foreach ($allLocations as $loc): ?>
                            <option value="<?= (int)$loc['id'] ?>" <?= ($locFilter === (int)$loc['id']) ? 'selected' : '' ?>>
                                <?= e($loc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select-solvex" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="submitted" <?= ($statusFilter === 'submitted') ? 'selected' : '' ?>>Submitted</option>
                        <option value="assigned" <?= ($statusFilter === 'assigned') ? 'selected' : '' ?>>Assigned</option>
                        <option value="in_progress" <?= ($statusFilter === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="on_hold" <?= ($statusFilter === 'on_hold') ? 'selected' : '' ?>>On Hold</option>
                        <option value="resolved" <?= ($statusFilter === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= ($statusFilter === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <!-- Sort -->
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Sort By</label>
                    <select name="sort" class="form-select-solvex" onchange="this.form.submit()">
                        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest Reports First</option>
                        <option value="longest" <?= ($sort === 'longest') ? 'selected' : '' ?>>Longest Unresolved</option>
                        <option value="most_affected" <?= ($sort === 'most_affected') ? 'selected' : '' ?>>Most Students Affected</option>
                        <option value="priority" <?= ($sort === 'priority') ? 'selected' : '' ?>>Highest Priority</option>
                    </select>
                </div>

            </form>
        </div>
    </div>

    <!-- Complaint Cards Grid (FixMyStreet / SeeClickFix Inspired) -->
    <?php if (empty($complaints)): ?>
        <div class="card-solvex border-0 shadow-sm p-5 text-center">
            <div class="empty-icon mx-auto mb-3">
                <i class="bi bi-inbox fs-2 text-muted"></i>
            </div>
            <h4 class="h5 fw-bold text-navy">No public complaints found</h4>
            <p class="text-muted small">No issues match your current filter settings.</p>
            <a href="<?= base_url('feed.php') ?>" class="btn-solvex btn-secondary mx-auto mt-2">
                Clear Filters
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($complaints as $c): ?>
                <?php
                    $comp = sanitize_complaint_for_view($c, $user);
                    $daysOpen = days_since($comp['created_at']);
                    $targetDays = (int)$comp['target_days'];
                    $chipClass = ($daysOpen > $targetDays) ? 'danger' : (($daysOpen > ($targetDays / 2)) ? 'warning' : '');
                    $isUserAffected = ((int)$c['is_user_affected'] > 0);
                    $isOwner = ((int)$c['student_id'] === $userId);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card-solvex border-0 shadow-sm h-100 d-flex flex-column">

                        <!-- Thumbnail or Placeholder -->
                        <div class="position-relative bg-light border-bottom text-center" style="height: 180px; overflow: hidden;">
                            <?php if (!empty($comp['thumb_image_id'])): ?>
                                <img src="<?= base_url('image.php?id=' . (int)$comp['thumb_image_id'] . '&thumb=1') ?>" alt="<?= e($comp['title']) ?>" class="w-100 h-100 object-fit-cover">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted bg-neutral-100">
                                    <i class="bi <?= e($comp['category_icon']) ?> display-4 text-primary opacity-50"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Floating Reference & Ageing Chip -->
                            <div class="position-absolute top-0 start-0 m-2">
                                <span class="badge bg-dark bg-opacity-75 font-monospace text-white py-1 px-2" style="font-size: 0.75rem;">
                                    <?= e($comp['reference']) ?>
                                </span>
                            </div>

                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="days-chip <?= $chipClass ?> shadow-xs">
                                    <i class="bi bi-clock-history"></i> <?= $daysOpen ?>d open
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-4 d-flex flex-column flex-grow-1">

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="status-pill <?= e($comp['status']) ?>"><?= ucwords(str_replace('_', ' ', e($comp['status']))) ?></span>
                                <span class="priority-tag <?= e($comp['priority']) ?>"><?= e($comp['priority']) ?></span>
                            </div>

                            <h5 class="fw-bold text-navy mb-2" style="font-size: var(--fs-md);">
                                <a href="<?= base_url('complaint.php?ref=' . urlencode($comp['reference'])) ?>" class="text-navy text-decoration-none hover-teal text-truncate-2">
                                    <?= e($comp['title']) ?>
                                </a>
                            </h5>

                            <p class="text-secondary small mb-3 flex-grow-1" style="font-size: 0.8125rem;">
                                <?= e(truncate($comp['description'], 110)) ?>
                            </p>

                            <div class="d-flex align-items-center justify-content-between pt-2 border-top text-muted small mt-auto" style="font-size: 0.75rem;">
                                <span><i class="bi <?= e($comp['category_icon']) ?> me-1 text-primary"></i> <?= e($comp['category_name']) ?></span>
                                <span class="text-truncate" style="max-width: 140px;"><i class="bi bi-geo-alt me-1"></i> <?= e($comp['location_name'] ?? 'Main Campus') ?></span>
                            </div>

                            <!-- "I am also affected" button -->
                            <div class="d-flex align-items-center justify-content-between mt-3 pt-2">
                                <span class="small text-secondary fw-semibold">
                                    <i class="bi bi-people me-1"></i> <span class="count-val" data-comp-id="<?= (int)$comp['id'] ?>"><?= (int)$comp['affected_count'] ?></span> affected
                                </span>

                                <?php if (!$isOwner): ?>
                                    <button 
                                        type="button" 
                                        class="btn-solvex <?= $isUserAffected ? 'btn-accent' : 'btn-secondary' ?> btn-sm py-1 px-2 btn-feed-affected"
                                        data-id="<?= (int)$comp['id'] ?>"
                                        data-affected="<?= $isUserAffected ? '1' : '0' ?>"
                                    >
                                        <i class="bi <?= $isUserAffected ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' ?>"></i>
                                        <span class="btn-label"><?= $isUserAffected ? 'Affected' : '+ Affected' ?></span>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Your Report</span>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer bg-white border-top p-3 text-center">
                            <a href="<?= base_url('complaint.php?ref=' . urlencode($comp['reference'])) ?>" class="btn-solvex btn-ghost btn-sm w-100">
                                View Details &amp; History <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="d-flex justify-content-center">
                <ul class="pagination">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('feed.php?page=' . ($page - 1) . '&category=' . $catFilter . '&location=' . $locFilter . '&status=' . urlencode($statusFilter) . '&sort=' . urlencode($sort)) ?>">
                            &laquo; Previous
                        </a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('feed.php?page=' . $p . '&category=' . $catFilter . '&location=' . $locFilter . '&status=' . urlencode($statusFilter) . '&sort=' . urlencode($sort)) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('feed.php?page=' . ($page + 1) . '&category=' . $catFilter . '&location=' . $locFilter . '&status=' . urlencode($statusFilter) . '&sort=' . urlencode($sort)) ?>">
                            Next &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const affectedButtons = document.querySelectorAll('.btn-feed-affected');
    const csrf = '<?= csrf_token() ?>';

    affectedButtons.forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const compId = this.dataset.id;
            this.disabled = true;

            const formData = new FormData();
            formData.append('complaint_id', compId);
            formData.append('csrf_token', csrf);

            try {
                const res = await fetch('<?= base_url('api/affected.php') ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    const countEl = document.querySelector(`.count-val[data-comp-id="${compId}"]`);
                    if (countEl) countEl.textContent = data.count;

                    if (data.affected) {
                        this.className = 'btn-solvex btn-accent btn-sm py-1 px-2 btn-feed-affected';
                        this.innerHTML = '<i class="bi bi-hand-thumbs-up-fill"></i> <span class="btn-label">Affected</span>';
                        Solvex.toast.show('Marked as also affected!', 'success');
                    } else {
                        this.className = 'btn-solvex btn-secondary btn-sm py-1 px-2 btn-feed-affected';
                        this.innerHTML = '<i class="bi bi-hand-thumbs-up"></i> <span class="btn-label">+ Affected</span>';
                        Solvex.toast.show('Removed affected status.', 'info');
                    }
                } else {
                    Solvex.toast.show(data.message, 'warning');
                }
            } catch (err) {
                Solvex.toast.show('Could not update affected count.', 'danger');
            } finally {
                this.disabled = false;
            }
        });
    });
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
