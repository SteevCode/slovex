<?php
/**
 * Solvex Report — Complaint Detail & Timeline View
 * 
 * Displays full complaint record, photo gallery, immutable audit timeline,
 * comments thread, "I am also affected" button, and owner action panel (Accept / Reopen).
 * 
 * Strictly enforces can_view_complaint() (returns 404 if denied)
 * and sanitize_complaint_for_view() (zero reporter PII or GPS for other students).
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/events.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();
require_login();

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

// Lookup by reference or ID
$ref = get('ref', '');
$id  = (int) get('id', '0');

if (empty($ref) && $id <= 0) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

$query = '
    SELECT c.*,
           cat.name AS category_name, cat.icon AS category_icon, cat.sensitive, cat.target_days,
           dept.name AS department_name,
           loc.name AS location_name,
           u_rep.full_name AS reporter_name, u_rep.register_no AS student_register_no, u_rep.email AS student_email,
           u_staff.full_name AS assigned_staff_name
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    JOIN users u_rep ON u_rep.id = c.student_id
    LEFT JOIN users u_staff ON u_staff.id = c.assigned_to
    WHERE ' . (!empty($ref) ? 'c.reference = ?' : 'c.id = ?');

$stmt = $pdo->prepare($query);
$stmt->execute([!empty($ref) ? $ref : $id]);
$rawComplaint = $stmt->fetch();

if (!$rawComplaint) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

$complaintId = (int) $rawComplaint['id'];

// 1. Enforce View Permissions (Returns 404 if unauthorized)
require_can_view_complaint($user, $rawComplaint);

// 2. Handle POST Actions (Comments, Status Change for Owner)
$postAction = post('action');
if (is_post() && $postAction) {
    csrf_verify();

    // A. Student Owner Actions (Accept / Reopen)
    if ($postAction === 'accept_resolution') {
        if ($userId === (int) $rawComplaint['student_id'] && $rawComplaint['status'] === 'resolved') {
            $changeRes = change_status($complaintId, 'closed', $user);
            if ($changeRes['success']) {
                flash('success', 'Resolution accepted! The complaint has been officially closed.');
            } else {
                flash('danger', $changeRes['message']);
            }
        }
        redirect(base_url('complaint.php?ref=' . urlencode($rawComplaint['reference'])));
    }

    if ($postAction === 'reopen_complaint') {
        $reason = trim(post('reopen_reason', ''));
        if ($userId === (int) $rawComplaint['student_id'] && $rawComplaint['status'] === 'resolved') {
            if (empty($reason)) {
                flash('danger', 'A reason is required when reopening an unresolved complaint.');
            } else {
                $changeRes = change_status($complaintId, 'reopened', $user, $reason);
                if ($changeRes['success']) {
                    flash('warning', 'Complaint reopened. The department has been notified to resume work.');
                } else {
                    flash('danger', $changeRes['message']);
                }
            }
        }
        redirect(base_url('complaint.php?ref=' . urlencode($rawComplaint['reference'])));
    }

    // B. Add Comment
    if ($postAction === 'add_comment') {
        $commentBody = trim(post('body', ''));
        if (empty($commentBody)) {
            flash('danger', 'Comment cannot be empty.');
        } elseif (!can_comment($user, $rawComplaint)) {
            flash('danger', 'You cannot comment on this complaint.');
        } else {
            $insCom = $pdo->prepare('
                INSERT INTO comments (complaint_id, user_id, body, is_internal, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ');
            $insCom->execute([$complaintId, $userId, $commentBody]);

            // Notify relevant party
            if ($userId === (int) $rawComplaint['student_id']) {
                if (!empty($rawComplaint['assigned_to'])) {
                    add_notification((int)$rawComplaint['assigned_to'], $complaintId, "New comment on #{$rawComplaint['reference']} from student.");
                } else {
                    notify_department_staff((int)$rawComplaint['department_id'], $complaintId, "New comment on #{$rawComplaint['reference']}.");
                }
            } else {
                add_notification((int)$rawComplaint['student_id'], $complaintId, "Staff replied to your complaint #{$rawComplaint['reference']}.");
            }

            flash('success', 'Comment posted.');
        }
        redirect(base_url('complaint.php?ref=' . urlencode($rawComplaint['reference']) . '#comments'));
    }
}

// 3. Sanitize Complaint View Model (Strips PII & GPS if not authorized)
$complaint = sanitize_complaint_for_view($rawComplaint, $user);

// 4. Fetch Attached Images
$imgStmt = $pdo->prepare('SELECT id, stored_name, mime, size FROM complaint_images WHERE complaint_id = ? ORDER BY id ASC');
$imgStmt->execute([$complaintId]);
$images = $imgStmt->fetchAll();

// 5. Fetch Audit Events Timeline (Append-only)
$evtStmt = $pdo->prepare('
    SELECT e.*, u.full_name AS actor_name, u.role AS actor_role
    FROM complaint_events e
    LEFT JOIN users u ON u.id = e.actor_id
    WHERE e.complaint_id = ?
    ORDER BY e.created_at ASC, e.id ASC
');
$evtStmt->execute([$complaintId]);
$events = $evtStmt->fetchAll();

// 6. Fetch Comments (Students never see internal notes)
$canSeeInternal = can_view_internal_notes($user, $rawComplaint);
$comQuery = '
    SELECT c.*, u.full_name AS author_name, u.role AS author_role
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.complaint_id = ? ' . ($canSeeInternal ? '' : 'AND c.is_internal = 0') . '
    ORDER BY c.created_at ASC
';
$comStmt = $pdo->prepare($comQuery);
$comStmt->execute([$complaintId]);
$comments = $comStmt->fetchAll();

// 7. Check Affected Count & User Status
$affStmt = $pdo->prepare('SELECT COUNT(*) FROM affected WHERE complaint_id = ?');
$affStmt->execute([$complaintId]);
$affectedCount = (int) $affStmt->fetchColumn();

$userAffected = false;
$userAffStmt = $pdo->prepare('SELECT id FROM affected WHERE complaint_id = ? AND user_id = ?');
$userAffStmt->execute([$complaintId, $userId]);
if ($userAffStmt->fetch()) {
    $userAffected = true;
}

$isOwner = ($userId === (int) $rawComplaint['student_id']);
$daysOpen = days_open($complaint);
$targetDays = (int) $complaint['target_days'];
$isDone = in_array($complaint['status'], ['closed', 'rejected', 'duplicate'], true);
$chipClass = '';
if (!$isDone) {
    if ($daysOpen > $targetDays) {
        $chipClass = 'danger';
    } elseif ($daysOpen > ($targetDays / 2)) {
        $chipClass = 'warning';
    }
}

$pageTitle = $complaint['reference'] . ' — ' . $complaint['title'];
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= role_home($user['role']) ?>">Dashboard</a></li>
            <?php if ($complaint['visibility'] === 'public'): ?>
                <li class="breadcrumb-item"><a href="<?= base_url('feed.php') ?>">Public Feed</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($complaint['reference']) ?></li>
        </ol>
    </nav>

    <!-- Complaint Header Bar -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fs-4 fw-bold font-monospace text-primary"><?= e($complaint['reference']) ?></span>
                    <span class="status-pill <?= e($complaint['status']) ?>"><?= ucwords(str_replace('_', ' ', e($complaint['status']))) ?></span>
                    <span class="priority-tag <?= e($complaint['priority']) ?>"><?= e($complaint['priority']) ?></span>
                    <?php if ($complaint['visibility'] === 'private'): ?>
                        <span class="visibility-badge private"><i class="bi bi-lock-fill"></i> Private</span>
                    <?php else: ?>
                        <span class="visibility-badge public"><i class="bi bi-globe"></i> Public</span>
                    <?php endif; ?>
                </div>

                <div>
                    <span class="days-chip <?= $chipClass ?> fs-6 py-1 px-3">
                        <i class="bi bi-clock-history"></i>
                        <?php if (in_array($complaint['status'], ['resolved', 'closed'])): ?>
                            Resolved in <?= max(1, days_since($complaint['created_at'])) ?> days
                        <?php else: ?>
                            Open for <?= $daysOpen ?> <?= $daysOpen === 1 ? 'day' : 'days' ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <h1 class="h3 fw-bold text-navy mb-2"><?= e($complaint['title']) ?></h1>

            <div class="d-flex align-items-center gap-3 text-muted small flex-wrap">
                <span><i class="bi bi-calendar3 me-1"></i> Reported <?= format_date($complaint['created_at']) ?></span>
                <span>&bull;</span>
                <span><i class="bi bi-person me-1"></i> <?= e($complaint['reporter_name']) ?></span>
                <?php if (!empty($complaint['student_register_no'])): ?>
                    <span class="badge bg-light text-dark border font-monospace"><?= e($complaint['student_register_no']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Owner Resolution Action Panel (Only when status is 'resolved') -->
    <?php if ($isOwner && $complaint['status'] === 'resolved'): ?>
        <div class="card-solvex border-0 shadow-md mb-4 bg-primary-50 border-start border-4 border-success">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success text-white p-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                            <i class="bi bi-check-lg fs-3"></i>
                        </div>
                        <div>
                            <h4 class="h5 fw-bold text-navy mb-1">Department has marked this issue Resolved!</h4>
                            <p class="text-muted small mb-0">Please verify whether the fix is satisfactory on campus. Accept to close or reopen with notes.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <!-- Accept Form -->
                        <form method="POST" action="<?= base_url('complaint.php?ref=' . urlencode($complaint['reference'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="accept_resolution">
                            <button type="submit" class="btn-solvex btn-primary py-2 px-3">
                                <i class="bi bi-check2-circle me-1"></i> Accept &amp; Close
                            </button>
                        </form>
                        <!-- Reopen Trigger -->
                        <button type="button" class="btn-solvex btn-secondary py-2 px-3 text-danger border-danger" data-bs-toggle="modal" data-bs-target="#reopenModal">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Still Broken
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Two-Column Layout -->
    <div class="row g-4">

        <!-- ── Left Column: Description, Photos, Timeline, Comments ── -->
        <div class="col-lg-8">

            <!-- Problem Description -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-file-text me-2 text-primary"></i> Problem Description</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-secondary" style="white-space: pre-line; line-height: 1.7;">
                        <?= e($complaint['description']) ?>
                    </div>

                    <!-- Photo Gallery -->
                    <?php if (!empty($images)): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-navy small mb-3"><i class="bi bi-images me-1"></i> Attached Photos (<?= count($images) ?>)</h6>
                            <div class="d-flex gap-3 flex-wrap">
                                <?php foreach ($images as $img): ?>
                                    <a href="<?= base_url('image.php?id=' . (int)$img['id']) ?>" target="_blank" class="gallery-thumb-link" data-bs-toggle="modal" data-bs-target="#imageModal<?= (int)$img['id'] ?>">
                                        <img src="<?= base_url('image.php?id=' . (int)$img['id'] . '&thumb=1') ?>" alt="Complaint photo" class="rounded-3 border shadow-xs" style="width: 140px; height: 105px; object-fit: cover;">
                                    </a>

                                    <!-- Lightbox Modal -->
                                    <div class="modal fade" id="imageModal<?= (int)$img['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content bg-transparent border-0 text-center">
                                                <div class="modal-body p-0">
                                                    <img src="<?= base_url('image.php?id=' . (int)$img['id']) ?>" alt="Photo full size" class="img-fluid rounded-3 shadow-lg">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vertical Timeline (Append-Only Audit Log) -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-clock-history me-2 text-primary"></i> Lifecycle Timeline</h5>
                </div>
                <div class="card-body p-4">
                    <div class="timeline">
                        <?php foreach ($events as $idx => $evt): ?>
                            <?php
                                $isLast = ($idx === count($events) - 1);
                                $evtIcon = match($evt['type']) {
                                    'created'       => 'bi-plus-circle-fill text-primary',
                                    'status_change' => match($evt['new_value']) {
                                        'assigned'    => 'bi-person-check-fill text-indigo',
                                        'in_progress' => 'bi-gear-wide-connected text-primary',
                                        'on_hold'     => 'bi-pause-circle-fill text-warning',
                                        'resolved'    => 'bi-check-circle-fill text-success',
                                        'closed'      => 'bi-lock-fill text-secondary',
                                        'reopened'    => 'bi-arrow-counterclockwise text-danger',
                                        'rejected'    => 'bi-x-circle-fill text-danger',
                                        default       => 'bi-circle-fill text-muted',
                                    },
                                    default => 'bi-dot text-primary',
                                };
                            ?>
                            <?php
                                $actorRole = $evt['actor_role'] ?? '';
                                if ($actorRole === 'student') {
                                    $actorLabel = can_see_reporter($user, $rawComplaint)
                                        ? ($evt['actor_name'] ?? 'Student')
                                        : 'Anonymous student';
                                } elseif (!empty($evt['actor_name'])) {
                                    $actorLabel = $evt['actor_name'];
                                } else {
                                    $actorLabel = $actorRole !== '' ? ucfirst($actorRole) : 'System';
                                }
                            ?>
                            <div class="timeline-item <?= $isLast ? 'active' : '' ?>">
                                <div class="timeline-time"><?= format_date($evt['created_at']) ?></div>
                                <div class="timeline-content">
                                    <div class="fw-semibold text-navy">
                                        <?php if ($evt['type'] === 'created'): ?>
                                            Complaint submitted by <?= e($actorLabel) ?>
                                            <?php if ($actorRole !== ''): ?>
                                                <span class="badge bg-light text-muted border text-uppercase" style="font-size: 0.65rem;"><?= e($actorRole) ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($evt['type'] === 'status_change'): ?>
                                            Status transitioned from <span class="badge bg-light text-secondary border"><?= e($evt['old_value']) ?></span> to <span class="badge bg-primary-subtle text-primary border"><?= e($evt['new_value']) ?></span>
                                            by <?= e($actorLabel) ?>
                                            <?php if ($actorRole !== ''): ?>
                                                <span class="badge bg-light text-muted border text-uppercase" style="font-size: 0.65rem;"><?= e($actorRole) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?= e(ucfirst(str_replace('_', ' ', $evt['type']))) ?>
                                            <?php if ($actorRole !== ''): ?>
                                                <span class="badge bg-light text-muted border text-uppercase" style="font-size: 0.65rem;"><?= e($actorRole) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($evt['reason'])): ?>
                                        <div class="p-2 bg-light rounded-2 mt-1 small text-secondary border">
                                            <i class="bi bi-chat-left-quote me-1 text-muted"></i> <?= e($evt['reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Comments Thread -->
            <div class="card-solvex border-0 shadow-sm mb-4" id="comments">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-navy mb-0">
                        <i class="bi bi-chat-dots me-2 text-primary"></i> Updates &amp; Comments (<?= count($comments) ?>)
                    </h5>
                </div>
                <div class="card-body p-4">

                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-chat-square fs-3 mb-2 d-block text-muted"></i>
                            No comments yet. Have more information or updates? Leave a comment below.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3 mb-4">
                            <?php foreach ($comments as $com): ?>
                                <div class="p-3 rounded-3 border bg-white shadow-xs">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php
                                                $commentAuthor = $com['author_name'];
                                                if ((int) $com['user_id'] === (int) $rawComplaint['student_id'] && !can_see_reporter($user, $rawComplaint)) {
                                                    $commentAuthor = 'Anonymous student';
                                                }
                                            ?>
                                            <span class="avatar-initials sm" style="background-color: <?= avatar_color($commentAuthor) ?>;">
                                                <?= e(initials($commentAuthor)) ?>
                                            </span>
                                            <span class="fw-bold small text-navy"><?= e($commentAuthor) ?></span>
                                            <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.65rem;"><?= e($com['author_role']) ?></span>
                                        </div>
                                        <span class="text-muted" style="font-size: 0.75rem;"><?= format_date($com['created_at']) ?></span>
                                    </div>
                                    <div class="small text-secondary ps-4 ms-2" style="white-space: pre-line;">
                                        <?= e($com['body']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Post Comment Form -->
                    <?php if (can_comment($user, $rawComplaint)): ?>
                        <form method="POST" action="<?= base_url('complaint.php?ref=' . urlencode($complaint['reference'])) ?>" class="mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_comment">

                            <div class="form-group mb-3">
                                <label for="commentBody" class="form-label-solvex small fw-semibold">Add a public comment / update</label>
                                <textarea name="body" id="commentBody" rows="3" class="form-control-solvex" placeholder="Share updates, follow-ups, or technician notes..." required></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn-solvex btn-primary py-2 px-4">
                                    <i class="bi bi-send me-1"></i> Post Comment
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

        </div>

        <!-- ── Right Column: Meta Card & Affected Action ───────────── -->
        <div class="col-lg-4">

            <!-- "I Am Also Affected" Card (Only for public complaints, not on own complaint) -->
            <?php if ($complaint['visibility'] === 'public'): ?>
                <div class="card-solvex border-0 shadow-sm mb-4">
                    <div class="card-body p-4 text-center">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle bg-primary-light text-primary mb-3">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                        <h4 class="h5 fw-bold text-navy mb-1">
                            <span id="affectedCountBadge"><?= $affectedCount ?></span> <?= $affectedCount === 1 ? 'Student Affected' : 'Students Affected' ?>
                        </h4>
                        <p class="text-muted small mb-3">
                            Experiencing the same problem on campus? Click to prioritize this issue without opening duplicates.
                        </p>

                        <?php if ($isOwner): ?>
                            <div class="badge bg-light text-secondary border py-2 px-3 w-100">
                                <i class="bi bi-info-circle me-1"></i> You are the reporter of this issue
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn-solvex <?= $userAffected ? 'btn-accent' : 'btn-primary' ?> w-100 py-2" id="btnToggleAffected">
                                <i class="bi <?= $userAffected ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' ?> me-1"></i>
                                <span id="btnAffectedText"><?= $userAffected ? 'You are affected (Click to remove)' : 'I am also affected' ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Complaint Metadata Card -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-info-circle me-2 text-primary"></i> Ticket Information</h5>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 small">
                        <li class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <span class="text-muted">Category</span>
                            <span class="fw-semibold text-navy d-inline-flex align-items-center gap-1">
                                <span class="text-primary"><?= category_icon_svg($complaint['category_icon'] ?? (int)($complaint['category_id'] ?? 1), 16) ?></span>
                                <?= e($complaint['category_name']) ?>
                            </span>
                        </li>
                        <li class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <span class="text-muted">Department</span>
                            <span class="fw-semibold text-navy"><?= e($complaint['department_name']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <span class="text-muted">Target SLA</span>
                            <span class="badge bg-light text-dark border"><?= (int)$complaint['target_days'] ?> Days</span>
                        </li>
                        <li class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <span class="text-muted">Campus Location</span>
                            <span class="fw-semibold text-navy text-end"><?= e($complaint['location_name'] ?? 'Main Campus') ?></span>
                        </li>
                        <?php if (!empty($complaint['lat']) && !empty($complaint['lng'])): ?>
                            <li class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">GPS Coordinates</span>
                                <span class="font-monospace text-primary small">
                                    <i class="bi bi-geo-alt-fill"></i> <?= number_format($complaint['lat'], 4) ?>, <?= number_format($complaint['lng'], 4) ?>
                                </span>
                            </li>
                        <?php endif; ?>
                        <li class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <span class="text-muted">Assigned Technician</span>
                            <span class="fw-semibold text-navy">
                                <?= !empty($complaint['assigned_staff_name']) ? e($complaint['assigned_staff_name']) : '<span class="text-muted fst-italic">Pending triage</span>' ?>
                            </span>
                        </li>
                        <li class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Last Activity</span>
                            <span class="fw-semibold text-navy"><?= format_date($complaint['updated_at']) ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Help / Escalation Notice -->
            <div class="card-solvex border-0 shadow-sm p-4 bg-light">
                <h6 class="fw-bold text-navy small mb-1"><i class="bi bi-shield-check text-primary me-1"></i> Campus SLA Policy</h6>
                <p class="text-muted small mb-0" style="font-size: 0.8125rem;">
                    Issues not addressed within the departmental target SLA are automatically highlighted to campus administrative oversight.
                </p>
            </div>

        </div>

    </div>
</div>

<!-- Modal: Reopen Complaint -->
<div class="modal fade" id="reopenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= base_url('complaint.php?ref=' . urlencode($complaint['reference'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reopen_complaint">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-arrow-counterclockwise me-1"></i> Reopen Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Why is this problem still unresolved? Explain what is still broken so the department can resume repair work immediately.
                    </p>
                    <div class="form-group mb-3">
                        <label for="reopen_reason" class="form-label-solvex small fw-semibold">Explanation <span class="text-danger">*</span></label>
                        <textarea name="reopen_reason" id="reopen_reason" rows="4" class="form-control-solvex" placeholder="e.g. The tap was replaced but water is still leaking from the pipe joint..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn-solvex btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-solvex btn-danger">Confirm &amp; Reopen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnAffected = document.getElementById('btnToggleAffected');
    if (btnAffected) {
        btnAffected.addEventListener('click', async function() {
            this.disabled = true;
            const csrf = '<?= csrf_token() ?>';
            const formData = new FormData();
            formData.append('complaint_id', <?= $complaintId ?>);
            formData.append('csrf_token', csrf);

            try {
                const res = await fetch('<?= base_url('api/affected.php') ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('affectedCountBadge').textContent = data.count;
                    if (data.affected) {
                        btnAffected.className = 'btn-solvex btn-accent w-100 py-2';
                        btnAffected.innerHTML = '<i class="bi bi-hand-thumbs-up-fill me-1"></i> You are affected (Click to remove)';
                    } else {
                        btnAffected.className = 'btn-solvex btn-primary w-100 py-2';
                        btnAffected.innerHTML = '<i class="bi bi-hand-thumbs-up me-1"></i> I am also affected';
                    }
                    Solvex.toast.show(data.message, 'success');
                } else {
                    Solvex.toast.show(data.message, 'warning');
                }
            } catch (err) {
                Solvex.toast.show('Error updating affected status.', 'danger');
            } finally {
                btnAffected.disabled = false;
            }
        });
    }
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
