<?php
/**
 * Solvex Report — Admin Master Data Management
 * 
 * Manage Departments, Categories, and Campus Locations:
 * - Full CRUD with soft-disable for categories (is_active=0)
 * - Tree structure hierarchy for locations
 * - Department head mapping
 * - Append-only logging to admin_log table
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

$section = get('section', 'categories');

// Handle POST actions
if (is_post()) {
    csrf_verify();
    $action = post('action');

    // 1. Add / Update Category
    if ($action === 'save_category') {
        $catId       = (int) post('category_id', '0');
        $name        = trim(post('name'));
        $icon        = trim(post('icon'));
        $deptId      = (int) post('department_id');
        $defPriority = post('default_priority');
        $defVis      = post('default_visibility');
        $forcePriv   = (post('force_private') === '1') ? 1 : 0;
        $sensitive   = (post('sensitive') === '1') ? 1 : 0;
        $targetDays  = max(1, (int) post('target_days', '3'));
        $locReq      = (post('location_required') === '1') ? 1 : 0;

        if (empty($name) || $deptId <= 0) {
            flash('danger', 'Category name and department are required.');
        } else {
            if ($catId > 0) {
                // Update
                $upd = $pdo->prepare('
                    UPDATE categories SET
                        name = ?, icon = ?, department_id = ?, default_priority = ?,
                        default_visibility = ?, force_private = ?, sensitive = ?,
                        target_days = ?, location_required = ?
                    WHERE id = ?
                ');
                $upd->execute([$name, $icon ?: 'bi-tag', $deptId, $defPriority, $defVis, $forcePriv, $sensitive, $targetDays, $locReq, $catId]);
                admin_log($user['id'], 'update_category', "category:{$catId}", "Updated category {$name}");
                flash('success', "Category '{$name}' updated.");
            } else {
                // Insert
                $ins = $pdo->prepare('
                    INSERT INTO categories (name, icon, department_id, default_priority, default_visibility, force_private, sensitive, target_days, location_required, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ');
                $ins->execute([$name, $icon ?: 'bi-tag', $deptId, $defPriority, $defVis, $forcePriv, $sensitive, $targetDays, $locReq]);
                $newId = (int)$pdo->lastInsertId();
                admin_log($user['id'], 'create_category', "category:{$newId}", "Created category {$name}");
                flash('success', "New category '{$name}' added.");
            }
        }
        redirect(base_url('admin/master.php?section=categories'));
    }

    // 2. Toggle Category Active State
    if ($action === 'toggle_category') {
        $catId     = (int) post('category_id');
        $targetAct = (post('target_active') === '1') ? 1 : 0;
        $pdo->prepare('UPDATE categories SET is_active = ? WHERE id = ?')->execute([$targetAct, $catId]);
        admin_log($user['id'], 'toggle_category', "category:{$catId}", "Set is_active={$targetAct}");
        flash('success', "Category status updated.");
        redirect(base_url('admin/master.php?section=categories'));
    }

    // 3. Add / Update Department
    if ($action === 'save_department') {
        $deptId   = (int) post('department_id', '0');
        $name     = trim(post('name'));
        $headId   = (int) post('head_user_id', '0');

        if (empty($name)) {
            flash('danger', 'Department name is required.');
        } else {
            if ($deptId > 0) {
                $pdo->prepare('UPDATE departments SET name = ?, head_user_id = ? WHERE id = ?')
                    ->execute([$name, $headId > 0 ? $headId : null, $deptId]);
                admin_log($user['id'], 'update_department', "department:{$deptId}", "Renamed to {$name}");
                flash('success', "Department '{$name}' updated.");
            } else {
                $pdo->prepare('INSERT INTO departments (name, head_user_id) VALUES (?, ?)')
                    ->execute([$name, $headId > 0 ? $headId : null]);
                $newId = (int)$pdo->lastInsertId();
                admin_log($user['id'], 'create_department', "department:{$newId}", "Created {$name}");
                flash('success', "Department '{$name}' created.");
            }
        }
        redirect(base_url('admin/master.php?section=departments'));
    }

    // 4. Add Location
    if ($action === 'save_location') {
        $parentId = (int) post('parent_id', '0');
        $locName  = trim(post('name'));
        $locType  = trim(post('type', 'area'));

        if (empty($locName)) {
            flash('danger', 'Location name is required.');
        } else {
            $pdo->prepare('INSERT INTO locations (parent_id, name, type) VALUES (?, ?, ?)')
                ->execute([$parentId > 0 ? $parentId : null, $locName, $locType]);
            $newId = (int)$pdo->lastInsertId();
            admin_log($user['id'], 'create_location', "location:{$newId}", "Added {$locName} ({$locType})");
            flash('success', "Location '{$locName}' added to campus map.");
        }
        redirect(base_url('admin/master.php?section=locations'));
    }
}

// Fetch Data for Sections
$departments = $pdo->query('
    SELECT d.id, d.name, d.head_user_id, u.full_name AS head_name,
           (SELECT COUNT(*) FROM complaints c WHERE c.department_id = d.id) AS ticket_count
    FROM departments d
    LEFT JOIN users u ON u.id = d.head_user_id
    ORDER BY d.name ASC
')->fetchAll();

$categories = $pdo->query('
    SELECT c.*, d.name AS department_name,
           (SELECT COUNT(*) FROM complaints comp WHERE comp.category_id = c.id) AS ticket_count
    FROM categories c
    JOIN departments d ON d.id = c.department_id
    ORDER BY c.name ASC
')->fetchAll();

$locations = $pdo->query('
    SELECT loc.id, loc.name, loc.type, loc.parent_id, p.name AS parent_name,
           (SELECT COUNT(*) FROM complaints comp WHERE comp.location_id = loc.id) AS ticket_count
    FROM locations loc
    LEFT JOIN locations p ON p.id = loc.parent_id
    ORDER BY loc.parent_id ASC, loc.name ASC
')->fetchAll();

$pageTitle = 'Master Data Management';
$activeNav = 'master';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1"><i class="bi bi-gear-fill text-primary me-2"></i>Master Data Configuration</h1>
            <p class="text-muted small mb-0">Maintain service level agreements (SLAs), routing categories, and campus locations.</p>
        </div>
    </div>

    <!-- Section Navigation -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($section === 'categories') ? 'active fw-bold' : '' ?>" href="<?= base_url('admin/master.php?section=categories') ?>">
                <i class="bi bi-tags me-1"></i> Problem Categories (<?= count($categories) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($section === 'departments') ? 'active fw-bold' : '' ?>" href="<?= base_url('admin/master.php?section=departments') ?>">
                <i class="bi bi-building me-1"></i> Departments (<?= count($departments) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($section === 'locations') ? 'active fw-bold' : '' ?>" href="<?= base_url('admin/master.php?section=locations') ?>">
                <i class="bi bi-geo-alt me-1"></i> Campus Locations (<?= count($locations) ?>)
            </a>
        </li>
    </ul>

    <!-- 1. CATEGORIES SECTION -->
    <?php if ($section === 'categories'): ?>
        
        <div class="row g-4">
            <!-- Add Category Form -->
            <div class="col-lg-4">
                <div class="card-solvex border-0 shadow-sm p-4 bg-light">
                    <h5 class="h6 fw-bold text-navy mb-3"><i class="bi bi-plus-circle text-primary me-1"></i> Add / Edit Category</h5>
                    <form method="POST" action="<?= base_url('admin/master.php?section=categories') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="category_id" id="catFormId" value="0">

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Category Name</label>
                            <input type="text" class="form-control-solvex form-control-sm" name="name" id="catFormName" placeholder="e.g. AC / HVAC" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Icon (Bootstrap Icon class)</label>
                            <input type="text" class="form-control-solvex form-control-sm" name="icon" id="catFormIcon" placeholder="bi-snow" value="bi-tag">
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Responsible Department</label>
                            <select class="form-select-solvex form-select-sm" name="department_id" id="catFormDept" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Default Priority</label>
                                <select class="form-select-solvex form-select-sm" name="default_priority" id="catFormPriority">
                                    <option value="critical">Critical</option>
                                    <option value="high">High</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Target SLA (Days)</label>
                                <input type="number" class="form-control-solvex form-control-sm" name="target_days" id="catFormTarget" value="3" min="1" max="30" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check small mb-1">
                                <input class="form-check-input" type="checkbox" name="force_private" value="1" id="catFormForcePriv">
                                <label class="form-check-label" for="catFormForcePriv">Force Private Visibility</label>
                            </div>
                            <div class="form-check small mb-1">
                                <input class="form-check-input" type="checkbox" name="sensitive" value="1" id="catFormSensitive">
                                <label class="form-check-label" for="catFormSensitive">Sensitive Incident (Owner/Admin Only)</label>
                            </div>
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" name="location_required" value="1" id="catFormLocReq" checked>
                                <label class="form-check-label" for="catFormLocReq">Location Required</label>
                            </div>
                        </div>

                        <button type="submit" class="btn-solvex btn-primary btn-sm w-100" id="catSubmitBtn">Save Category</button>
                    </form>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="col-lg-8">
                <div class="card-solvex border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th class="ps-4">Category</th>
                                        <th>Department</th>
                                        <th>SLA Target</th>
                                        <th>Rules</th>
                                        <th>Status</th>
                                        <th class="pe-4 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr class="<?= empty($cat['is_active']) ? 'opacity-50' : '' ?>">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi <?= e($cat['icon']) ?> text-primary fs-5"></i>
                                                    <div>
                                                        <div class="fw-bold text-navy"><?= e($cat['name']) ?></div>
                                                        <span class="priority-tag <?= e($cat['default_priority']) ?>"><?= e($cat['default_priority']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= e($cat['department_name']) ?></span></td>
                                            <td><strong><?= (int)$cat['target_days'] ?> days</strong></td>
                                            <td>
                                                <?php if ($cat['sensitive']): ?>
                                                    <span class="badge bg-danger" title="Owner and Admin only">Sensitive</span>
                                                <?php elseif ($cat['force_private']): ?>
                                                    <span class="badge bg-secondary" title="Locked private">Private</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-secondary border">Public</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $cat['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= $cat['is_active'] ? 'Active' : 'Disabled' ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <!-- Toggle is_active -->
                                                <form method="POST" action="<?= base_url('admin/master.php?section=categories') ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_category">
                                                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                                    <input type="hidden" name="target_active" value="<?= $cat['is_active'] ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Toggle active status">
                                                        <?= $cat['is_active'] ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- 2. DEPARTMENTS SECTION -->
    <?php elseif ($section === 'departments'): ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card-solvex border-0 shadow-sm p-4 bg-light">
                    <h5 class="h6 fw-bold text-navy mb-3"><i class="bi bi-building-add text-primary me-1"></i> Add Department</h5>
                    <form method="POST" action="<?= base_url('admin/master.php?section=departments') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_department">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Department Name</label>
                            <input type="text" class="form-control-solvex form-control-sm" name="name" placeholder="e.g. Sports Facilities" required>
                        </div>
                        <button type="submit" class="btn-solvex btn-primary btn-sm w-100">Create Department</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-solvex border-0 shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Department Name</th>
                                    <th>Department Head</th>
                                    <th>Workload</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $d): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace">#<?= (int)$d['id'] ?></td>
                                        <td class="fw-bold text-navy"><?= e($d['name']) ?></td>
                                        <td><?= e($d['head_name'] ?? 'Not Assigned') ?></td>
                                        <td><span class="badge bg-light text-primary border"><?= (int)$d['ticket_count'] ?> tickets</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <!-- 3. LOCATIONS SECTION -->
    <?php elseif ($section === 'locations'): ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card-solvex border-0 shadow-sm p-4 bg-light">
                    <h5 class="h6 fw-bold text-navy mb-3"><i class="bi bi-geo-alt-fill text-primary me-1"></i> Add Campus Location</h5>
                    <form method="POST" action="<?= base_url('admin/master.php?section=locations') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_location">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Location Name</label>
                            <input type="text" class="form-control-solvex form-control-sm" name="name" placeholder="e.g. Mechanical Workshop" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Parent Location (Hierarchy)</label>
                            <select class="form-select-solvex form-select-sm" name="parent_id">
                                <option value="0">-- Top Level (Campus/Zone) --</option>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?> (<?= e($l['type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Location Type</label>
                            <select class="form-select-solvex form-select-sm" name="type">
                                <option value="building">Building</option>
                                <option value="hostel">Hostel</option>
                                <option value="floor">Floor</option>
                                <option value="room">Room / Lab</option>
                                <option value="washroom">Washroom</option>
                                <option value="area">Outdoor Area</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-solvex btn-primary btn-sm w-100">Add to Map</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-solvex border-0 shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Location</th>
                                    <th>Type</th>
                                    <th>Parent Zone</th>
                                    <th>Incidents</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $loc): ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold text-navy">
                                            <?= $loc['parent_id'] ? '&bull; ' : '' ?><?= e($loc['name']) ?>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?= e($loc['type']) ?></span></td>
                                        <td><?= e($loc['parent_name'] ?? 'Root Campus') ?></td>
                                        <td><span class="small text-muted"><?= (int)$loc['ticket_count'] ?> reports</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

