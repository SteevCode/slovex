<?php
/**
 * Solvex Report — Staff Complaint Workspace
 * 
 * Comprehensive operational interface for resolving tickets:
 * - Assigned triage actions: assign, status change, priority change
 * - Comments & internal notes (internal notes never visible to students)
 * - "Ask Student for details" action
 * - Resolution with note
 * - Department re-assignment & duplicate linking
 * - On-hold scheduling (past 7 days requires admin approval)
 * - Full append-only timeline & reporter identity verification
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/events.php';
require_once SOLVEX_ROOT . '/includes/upload.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';
require_once SOLVEX_ROOT . '/includes/maintenance.php';

init_session();
require_role('staff', 'admin');

run_maintenance();

$user = current_user();
$userId = (int) $user['id'];
$userRole = $user['role'];
$userDeptId = !empty($user['department_id']) ? (int) $user['department_id'] : null;
$pdo = db();

$id = (int) get('id', '0');
if ($id <= 0) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

// Load complaint record
$stmt = $pdo->prepare('
    SELECT c.*,
           cat.name AS category_name, cat.icon AS category_icon, cat.sensitive, cat.target_days,
           dept.name AS department_name,
           loc.name AS location_name,
           u_rep.full_name AS reporter_name, u_rep.register_no AS student_register_no, 
           u_rep.email AS student_email, u_rep.department AS student_dept,
           u_staff.full_name AS assigned_staff_name
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    JOIN users u_rep ON u_rep.id = c.student_id
    LEFT JOIN users u_staff ON u_staff.id = c.assigned_to
    WHERE c.id = ?
');
$stmt->execute([$id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

// Department check: staff can only see their department's complaints (admin can view all)
if ($userRole === 'staff' && (int)$complaint['department_id'] !== $userDeptId) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

// Handle POST actions
if (is_post()) {
    csrf_verify();
    $action = post('action');

    // 1. Assign staff member
    if ($action === 'assign') {
        $staffId = (int) post('staff_id');
        if ($staffId > 0) {
            // Verify staff member belongs to same department
            $stfCheck = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND department_id = ? AND role IN ('staff', 'admin') AND status = 'active'");
            $stfCheck->execute([$staffId, $complaint['department_id']]);
            $staffUser = $stfCheck->fetch();

            if ($staffUser) {
                $prevStaffName = $complaint['assigned_staff_name'] ?? 'Unassigned';
                $pdo->prepare("UPDATE complaints SET assigned_to = ?, updated_at = NOW() WHERE id = ?")->execute([$staffId, $id]);
                add_event($id, $userId, 'assigned', $prevStaffName, $staffUser['full_name'], 'Assigned by ' . $user['full_name']);
                add_notification($staffId, $id, "Ticket #{$complaint['reference']} has been assigned to you.");
                flash('success', "Ticket assigned to {$staffUser['full_name']}.");
            } else {
                flash('danger', "Invalid staff member selection.");
            }
        }
        redirect(base_url("staff/complaint.php?id={$id}"));
    }

    // 2. Change status
    if ($action === 'status_change') {
        $newStatus = post('new_status');
        $reason = post('reason');
        $holdUntil = post('hold_until');

        $actorData = $user;
        if ($newStatus === 'on_hold') {
            if (empty($holdUntil) || strtotime($holdUntil) <= time()) {
                flash('danger', 'Please provide a valid future expected date for placing the ticket on hold.');
                redirect(base_url("staff/complaint.php?id={$id}"));
            }
            $actorData['hold_until'] = $holdUntil;
        }

        $res = change_status($id, $newStatus, $actorData, $reason);
        if ($res['success']) {
            flash('success', $res['message']);
        } else {
            flash('danger', $res['message']);
        }
        redirect(base_url("staff/complaint.php?id={$id}"));
    }

    // 3. Priority change
    if ($action === 'priority_change') {
        $newPriority = post('new_priority');
        $reason = trim(post('reason'));
        if (empty($reason)) {
            flash('danger', 'A reason is required to change priority.');
        } elseif (in_array($newPriority, ['critical', 'high', 'medium', 'low'], true)) {
            $oldPriority = $complaint['priority'];
            $pdo->prepare("UPDATE complaints SET priority = ?, updated_at = NOW() WHERE id = ?")->execute([$newPriority, $id]);
            add_event($id, $userId, 'priority_change', $oldPriority, $newPriority, $reason);
            add_notification((int)$complaint['student_id'], $id, "Priority of #{$complaint['reference']} changed to " . ucfirst($newPriority) . ".");
            flash('success', "Priority updated to " . ucfirst($newPriority) . ".");
        }
        redirect(base_url("staff/complaint.php?id={$id}"));
    }

    // 4. Add Comment or Internal Note
    if ($action === 'add_comment') {
        $body = trim(post('body'));
        $isInternal = (post('is_internal') === '1') ? 1 : 0;

        if (!empty($body)) {
            $pdo->prepare("INSERT INTO comments (complaint_id, user_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$id, $userId, $body, $isInternal]);

            if ($isInternal) {
                flash('success', 'Internal note added (visible to staff/admin only).');
            } else {
                add_notification((int)$complaint['student_id'], $id, "New update on your ticket #{$complaint['reference']}: " . truncate($body, 80));
                flash('success', 'Public response posted to student.');
            }
        }
        redirect(base_url("staff/complaint.php?id={$id}#comments"));
    }

    // 5. Ask Student for Details
    if ($action === 'ask_student') {
        $question = trim(post('question'));
        if (!empty($question)) {
            $fullQuestion = "[Clarification Requested]: " . $question;
            $pdo->prepare("INSERT INTO comments (complaint_id, user_id, body, is_internal, created_at) VALUES (?, ?, ?, 0, NOW())")
                ->execute([$id, $userId, $fullQuestion]);
            add_notification((int)$complaint['student_id'], $id, "Staff has requested clarification on #{$complaint['reference']}. Please check and reply.");
            flash('success', 'Clarification question sent to the student.');
        }
        redirect(base_url("staff/complaint.php?id={$id}#comments"));
    }

    // 6. Mark Duplicate
    if ($action === 'mark_duplicate') {
        $origRef = trim(post('duplicate_of_ref'));
        $reason  = trim(post('reason'));

        if (empty($origRef) || empty($reason)) {
            flash('danger', 'Original ticket reference and reason are required.');
        } else {
            $origStmt = $pdo->prepare("SELECT id, reference, student_id FROM complaints WHERE reference = ? AND id != ?");
            $origStmt->execute([$origRef, $id]);
            $orig = $origStmt->fetch();

            if (!$orig) {
                flash('danger', "Target ticket reference #{$origRef} was not found.");
            } else {
                $dupRes = change_status($id, 'duplicate', $user, "Marked duplicate of #{$origRef}. {$reason}");
                if ($dupRes['success']) {
                    $pdo->prepare("UPDATE complaints SET duplicate_of_id = ? WHERE id = ?")->execute([$orig['id'], $id]);
                    add_event((int)$orig['id'], $userId, 'duplicate_linked', null, $complaint['reference'], "Ticket #{$complaint['reference']} linked as duplicate.");
                    add_notification((int)$orig['student_id'], (int)$orig['id'], "Ticket #{$complaint['reference']} was linked as a duplicate to your report.");
                    flash('success', "Ticket marked duplicate of #{$origRef}.");
                } else {
                    flash('danger', $dupRes['message']);
                }
            }
        }
        redirect(base_url("staff/complaint.php?id={$id}"));
    }

    // 7. Reassign Department
    if ($action === 'reassign_dept') {
        $newDeptId = (int) post('new_dept_id');
        $reason    = trim(post('reason'));

        if ($newDeptId <= 0 || empty($reason)) {
            flash('danger', 'Please select a new department and specify a reason.');
        } else {
            $deptCheck = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $deptCheck->execute([$newDeptId]);
            $newDeptName = $deptCheck->fetchColumn();

            if ($newDeptName) {
                $oldDeptName = $complaint['department_name'];
                $pdo->prepare("UPDATE complaints SET department_id = ?, assigned_to = NULL, updated_at = NOW() WHERE id = ?")
                    ->execute([$newDeptId, $id]);
                add_event($id, $userId, 'reassigned', $oldDeptName, $newDeptName, $reason);
                notify_department_staff($newDeptId, $id, "Ticket #{$complaint['reference']} reassigned to your department: {$reason}");
                flash('success', "Ticket reassigned to {$newDeptName}.");
            }
        }
        redirect(base_url("staff/complaint.php?id={$id}"));
    }
}

// Fetch images
$imgStmt = $pdo->prepare('SELECT id, stored_name, mime, size FROM complaint_images WHERE complaint_id = ? ORDER BY id ASC');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

// Fetch timeline events (strictly append-only)
$evtStmt = $pdo->prepare('
    SELECT ce.*, u.full_name AS actor_name, u.role AS actor_role
    FROM complaint_events ce
    LEFT JOIN users u ON u.id = ce.actor_id
    WHERE ce.complaint_id = ?
    ORDER BY ce.created_at ASC
');
$evtStmt->execute([$id]);
$events = $evtStmt->fetchAll();

// Fetch comments (both public and internal for staff)
$comStmt = $pdo->prepare('
    SELECT c.*, u.full_name AS author_name, u.role AS author_role
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.complaint_id = ?
    ORDER BY c.created_at ASC
');
$comStmt->execute([$id]);
$comments = $comStmt->fetchAll();

// Department colleagues list for assignment
$colleagueStmt = $pdo->prepare("SELECT id, full_name, staff_code FROM users WHERE department_id = ? AND role IN ('staff', 'admin') AND status = 'active' ORDER BY full_name ASC");
$colleagueStmt->execute([$complaint['department_id']]);
$colleagues = $colleagueStmt->fetchAll();

// All departments for reassignment
$allDepts = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();

// Transitions matrix lookup
$matrix = get_transition_matrix();
$allowedTransitions = $matrix[$complaint['status']] ?? [];

$daysOpen = days_since($complaint['created_at']);
$targetDays = (int)$complaint['target_days'];
$isOverdue = ($daysOpen > $targetDays && !in_array($complaint['status'], ['closed', 'rejected', 'duplicate'], true));

$pageTitle = "Ticket #{$complaint['reference']} — Workspace";
$activeNav = 'queue';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="<?= base_url('staff/queue.php') ?>">Queue</a></li>
                <li class="breadcrumb-item active"><?= e($complaint['reference']) ?></li>
            </ol>
        </nav>
        <a href="<?= base_url('staff/queue.php') ?>" class="btn-solvex btn-ghost btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Queue
        </a>
    </div>

    <!-- Ticket Banner Header -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <span class="fs-4 fw-bold font-monospace text-primary"><?= e($complaint['reference']) ?></span>
                        <span class="status-pill <?= e($complaint['status']) ?>"><?= ucwords(str_replace('_', ' ', e($complaint['status']))) ?></span>
                        <span class="priority-tag <?= e($complaint['priority']) ?>"><?= e($complaint['priority']) ?></span>
                        <?php if ($complaint['visibility'] === 'private'): ?>
                            <span class="visibility-badge private"><i class="bi bi-lock-fill"></i> Private</span>
                        <?php else: ?>
                            <span class="visibility-badge public"><i class="bi bi-globe"></i> Public</span>
                        <?php endif; ?>
                        <span class="days-chip <?= $isOverdue ? 'danger' : '' ?>">
                            <i class="bi bi-clock-history"></i> <?= $daysOpen ?>d open (Target: <?= $targetDays ?>d)
                        </span>
                    </div>
                    <h1 class="h4 fw-bold text-navy mb-1"><?= e($complaint['title']) ?></h1>
                    <div class="text-muted small">
                        <i class="bi <?= e($complaint['category_icon']) ?> me-1"></i> <?= e($complaint['category_name']) ?> &bull;
                        <i class="bi bi-geo-alt me-1"></i> <?= e($complaint['location_name'] ?? 'Main Campus') ?> &bull;
                        Dept: <strong><?= e($complaint['department_name']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- Left Column: Details, Photos, Timeline, Comments -->
        <div class="col-lg-8">
            
            <!-- Description -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-text-left me-2 text-primary"></i>Description</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-secondary mb-0" style="white-space: pre-line;"><?= e($complaint['description']) ?></p>
                </div>
            </div>

            <!-- Photos -->
            <?php if (!empty($images)): ?>
                <div class="card-solvex border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom p-3">
                        <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-images me-2 text-primary"></i>Attached Photos (<?= count($images) ?>)</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex gap-3 flex-wrap">
                            <?php foreach ($images as $img): ?>
                                <a href="<?= base_url('image.php?id=' . (int)$img['id']) ?>" target="_blank" class="border rounded p-1 d-inline-block shadow-sm">
                                    <img src="<?= base_url('image.php?id=' . (int)$img['id'] . '&thumb=1') ?>" alt="Photo" style="width: 120px; height: 90px; object-fit: cover;" class="rounded">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Audit Timeline</h5>
                </div>
                <div class="card-body p-4">
                    <div class="timeline-solvex">
                        <?php foreach ($events as $evt): ?>
                            <div class="timeline-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark border text-uppercase" style="font-size:0.7rem;">
                                            <?= e($evt['actor_role'] ?? 'SYSTEM') ?>
                                        </span>
                                        <strong class="text-navy small"><?= e($evt['actor_name'] ?? 'Automated Engine') ?></strong>
                                    </div>
                                    <span class="small text-muted" style="font-size:0.75rem;"><?= format_date($evt['created_at']) ?></span>
                                </div>
                                <div class="small fw-semibold text-secondary">
                                    <?php
                                    $typeMap = [
                                        'created'             => 'Complaint submitted by student',
                                        'status_change'       => "Status changed from '{$evt['old_value']}' to '{$evt['new_value']}'",
                                        'assigned'            => "Assigned to {$evt['new_value']}",
                                        'priority_change'     => "Priority adjusted from {$evt['old_value']} to {$evt['new_value']}",
                                        'reassigned'          => "Reassigned department to {$evt['new_value']}",
                                        'duplicate_linked'    => "Linked duplicate ticket #{$evt['new_value']}",
                                        'hold_overdue'        => "Hold period exceeded — flagged for admin review",
                                        'notified_unassigned' => "24h unassigned escalation alert sent",
                                    ];
                                    echo e($typeMap[$evt['type']] ?? $evt['type']);
                                    ?>
                                </div>
                                <?php if (!empty($evt['reason'])): ?>
                                    <div class="small text-muted fst-italic mt-1 bg-light p-2 rounded">
                                        &ldquo;<?= e($evt['reason']) ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Comments & Discussion -->
            <div class="card-solvex border-0 shadow-sm mb-4" id="comments">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-chat-dots me-2 text-primary"></i>Communication Thread</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($comments)): ?>
                        <p class="text-muted small">No comments or notes recorded yet.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3 mb-4">
                            <?php foreach ($comments as $com): ?>
                                <div class="p-3 rounded-3 <?= $com['is_internal'] ? 'bg-dark-subtle border-start border-4 border-dark' : 'bg-light border' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold small text-navy"><?= e($com['author_name']) ?></span>
                                            <span class="badge bg-secondary text-uppercase" style="font-size:0.65rem;"><?= e($com['author_role']) ?></span>
                                            <?php if ($com['is_internal']): ?>
                                                <span class="badge bg-dark text-white"><i class="bi bi-shield-lock me-1"></i>Internal Staff Note</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-muted small" style="font-size:0.75rem;"><?= format_date($com['created_at']) ?></span>
                                    </div>
                                    <p class="small text-secondary mb-0" style="white-space: pre-line;"><?= e($com['body']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add Comment / Internal Note Form -->
                    <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_comment">
                        <div class="mb-3">
                            <label for="comBody" class="form-label small fw-semibold">Add Response or Note</label>
                            <textarea class="form-control-solvex form-control-sm" id="comBody" name="body" rows="3" placeholder="Write message..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="checkInternal">
                                <label class="form-check-label small fw-semibold text-danger" for="checkInternal">
                                    <i class="bi bi-shield-lock-fill me-1"></i> Make Internal Note (hidden from student)
                                </label>
                            </div>
                            <button type="submit" class="btn-solvex btn-primary btn-sm px-3">Post Note / Reply</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right Column: Meta, Reporter PII, Action Panel -->
        <div class="col-lg-4">
            
            <!-- Action Panel -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white p-3">
                    <h5 class="h6 fw-bold mb-0"><i class="bi bi-sliders me-2"></i>Staff Resolution Panel</h5>
                </div>
                <div class="card-body p-3">

                    <!-- Assign Section -->
                    <div class="mb-4 pb-3 border-bottom">
                        <label class="form-label small fw-bold text-navy">Assignment</label>
                        <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>" class="d-flex flex-column gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="assign">
                            <select class="form-select-solvex form-select-sm" name="staff_id">
                                <option value="">-- Select Colleague --</option>
                                <?php foreach ($colleagues as $cl): ?>
                                    <option value="<?= (int)$cl['id'] ?>" <?= (int)$complaint['assigned_to'] === (int)$cl['id'] ? 'selected' : '' ?>>
                                        <?= e($cl['full_name']) ?> (<?= e($cl['staff_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn-solvex btn-secondary btn-sm flex-grow-1">Reassign</button>
                                <button type="submit" name="staff_id" value="<?= $userId ?>" class="btn-solvex btn-ghost btn-sm border">Assign to Me</button>
                            </div>
                        </form>
                    </div>

                    <!-- Status Change Workflow -->
                    <div class="mb-4 pb-3 border-bottom">
                        <label class="form-label small fw-bold text-navy mb-2">Change Status (Current: <?= ucwords(str_replace('_', ' ', e($complaint['status']))) ?>)</label>
                        <?php if (empty($allowedTransitions)): ?>
                            <p class="small text-muted mb-0">No further transitions permitted from current status.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($allowedTransitions as $targetStatus => $rule): ?>
                                    <?php if (in_array($userRole, $rule['roles'], true)): ?>
                                        <button class="btn btn-sm btn-outline-primary text-start d-flex justify-content-between align-items-center"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseStatus<?= $targetStatus ?>">
                                            <span>Move to <strong><?= ucwords(str_replace('_', ' ', $targetStatus)) ?></strong></span>
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <div class="collapse p-2 bg-light rounded" id="collapseStatus<?= $targetStatus ?>">
                                            <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="status_change">
                                                <input type="hidden" name="new_status" value="<?= e($targetStatus) ?>">

                                                <?php if ($targetStatus === 'on_hold'): ?>
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Expected Hold Date <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control-solvex form-control-sm" name="hold_until" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($rule['require_reason']) || $targetStatus === 'resolved'): ?>
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Note / Reason <span class="text-danger">*</span></label>
                                                        <textarea class="form-control-solvex form-control-sm" name="reason" rows="2" placeholder="Explain action..." required></textarea>
                                                    </div>
                                                <?php endif; ?>

                                                <button type="submit" class="btn-solvex btn-primary btn-sm w-100">
                                                    Confirm <?= ucwords(str_replace('_', ' ', $targetStatus)) ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Priority Change -->
                    <div class="mb-4 pb-3 border-bottom">
                        <label class="form-label small fw-bold text-navy">Priority Adjustment</label>
                        <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="priority_change">
                            <div class="mb-2">
                                <select class="form-select-solvex form-select-sm" name="new_priority">
                                    <option value="critical" <?= $complaint['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                                    <option value="high" <?= $complaint['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="medium" <?= $complaint['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="low" <?= $complaint['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <input type="text" class="form-control-solvex form-control-sm" name="reason" placeholder="Reason for change..." required>
                            </div>
                            <button type="submit" class="btn-solvex btn-secondary btn-sm w-100">Update Priority</button>
                        </form>
                    </div>

                    <!-- Escalation & Routing Accordion -->
                    <div class="accordion accordion-flush" id="routingAccordion">
                        
                        <!-- Ask Student -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed px-0 py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#accAsk">
                                    <i class="bi bi-question-circle me-1 text-primary"></i> Ask Student for Details
                                </button>
                            </h2>
                            <div id="accAsk" class="accordion-collapse collapse" data-bs-parent="#routingAccordion">
                                <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>" class="py-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="ask_student">
                                    <textarea class="form-control-solvex form-control-sm mb-2" name="question" rows="2" placeholder="What extra info is needed?" required></textarea>
                                    <button type="submit" class="btn-solvex btn-primary btn-sm w-100">Send Question</button>
                                </form>
                            </div>
                        </div>

                        <!-- Mark Duplicate -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed px-0 py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#accDup">
                                    <i class="bi bi-files me-1 text-primary"></i> Mark as Duplicate
                                </button>
                            </h2>
                            <div id="accDup" class="accordion-collapse collapse" data-bs-parent="#routingAccordion">
                                <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>" class="py-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_duplicate">
                                    <input type="text" class="form-control-solvex form-control-sm mb-2" name="duplicate_of_ref" placeholder="Original Ref (e.g. SVX-2026-00001)" required>
                                    <textarea class="form-control-solvex form-control-sm mb-2" name="reason" rows="2" placeholder="Reason..." required></textarea>
                                    <button type="submit" class="btn-solvex btn-danger btn-sm w-100" onclick="return confirm('Mark this complaint duplicate and link records?');">Mark Duplicate</button>
                                </form>
                            </div>
                        </div>

                        <!-- Reassign Dept -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed px-0 py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#accReassign">
                                    <i class="bi bi-arrow-left-right me-1 text-primary"></i> Reassign Department
                                </button>
                            </h2>
                            <div id="accReassign" class="accordion-collapse collapse" data-bs-parent="#routingAccordion">
                                <form method="POST" action="<?= base_url('staff/complaint.php?id=' . $id) ?>" class="py-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reassign_dept">
                                    <select class="form-select-solvex form-select-sm mb-2" name="new_dept_id" required>
                                        <option value="">-- New Department --</option>
                                        <?php foreach ($allDepts as $d): ?>
                                            <?php if ((int)$d['id'] !== (int)$complaint['department_id']): ?>
                                                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea class="form-control-solvex form-control-sm mb-2" name="reason" rows="2" placeholder="Reassignment reason..." required></textarea>
                                    <button type="submit" class="btn-solvex btn-warning btn-sm w-100 text-dark" onclick="return confirm('Transfer ticket to another department?');">Reassign</button>
                                </form>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            <!-- Reporter Identity Verification (Protected) -->
            <?php if (can_see_reporter($user, $complaint)): ?>
                <div class="card-solvex border-0 shadow-sm mb-4 bg-light">
                    <div class="card-header bg-white border-bottom p-3">
                        <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Reporter Identity (Restricted)</h5>
                    </div>
                    <div class="card-body p-3 small">
                        <div class="mb-2"><strong>Name:</strong> <?= e($complaint['reporter_name']) ?></div>
                        <div class="mb-2"><strong>Register No:</strong> <?= e($complaint['student_register_no']) ?></div>
                        <div class="mb-2"><strong>Email:</strong> <?= e($complaint['student_email']) ?></div>
                        <div class="mb-2"><strong>Student Dept:</strong> <?= e($complaint['student_dept'] ?? 'N/A') ?></div>
                        <?php if (!empty($complaint['lat']) && !empty($complaint['lng'])): ?>
                            <div class="pt-2 border-top">
                                <strong>GPS Location:</strong> <?= e($complaint['lat']) ?>, <?= e($complaint['lng']) ?>
                                <a href="https://maps.google.com/?q=<?= e($complaint['lat']) ?>,<?= e($complaint['lng']) ?>" target="_blank" class="d-block mt-1 text-primary fw-semibold">
                                    <i class="bi bi-geo-alt-fill"></i> Open in Google Maps
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ticket Meta -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-info-circle me-2 text-primary"></i>Properties</h5>
                </div>
                <div class="card-body p-3 small text-secondary">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Category:</span>
                        <span class="fw-semibold text-navy"><?= e($complaint['category_name']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Department:</span>
                        <span class="fw-semibold text-navy"><?= e($complaint['department_name']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Target SLA:</span>
                        <span class="fw-semibold text-navy"><?= (int)$complaint['target_days'] ?> days</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Created:</span>
                        <span><?= format_date($complaint['created_at']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-0">
                        <span>Last Updated:</span>
                        <span><?= format_date($complaint['updated_at']) ?></span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

