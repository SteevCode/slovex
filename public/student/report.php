<?php
/**
 * Solvex Report — Student Problem Reporting Flow
 * 
 * 3-Stage Guided Intake:
 * Step 1: WHAT (Visual category selection with safety alerts)
 * Step 2: WHERE (Cascading campus locations + Geolocation API)
 * Step 3: DETAILS (Title, description, photo uploads, visibility toggle)
 * Pre-submit duplicate prevention check with "I am also affected" prompt.
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';
require_once SOLVEX_ROOT . '/includes/upload.php';
require_once SOLVEX_ROOT . '/includes/events.php';

init_session();
require_role('student');

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

// Fetch active categories with department names
$catStmt = $pdo->query('
    SELECT c.id, c.name, c.icon, c.department_id, c.default_priority,
           c.default_visibility, c.force_private, c.sensitive, c.target_days, c.location_required,
           d.name AS department_name
    FROM categories c
    JOIN departments d ON d.id = c.department_id
    WHERE c.is_active = 1
    ORDER BY c.id ASC
');
$categories = $catStmt->fetchAll();

// Fetch root campus locations for initial dropdown
$locStmt = $pdo->query('SELECT id, name, type FROM locations WHERE parent_id IS NULL ORDER BY name ASC');
$rootLocations = $locStmt->fetchAll();

$error      = null;
$successRef = get('ok', '');
$categoryId = (int) post('category_id', '0');
$locationId = (int) post('location_id', '0');
$title      = post('title', '');
$description= post('description', '');
$visibility = post('visibility', 'public');
$isUrgent   = post('is_urgent', '0') === '1';
$lat        = post('lat', '') !== '' ? (float) post('lat') : null;
$lng        = post('lng', '') !== '' ? (float) post('lng') : null;
$accuracy   = post('gps_accuracy_m', '') !== '' ? (float) post('gps_accuracy_m') : null;

// Handle Form Submission
if (is_post()) {
    csrf_verify();

    // 1. Rate Limit: Max 5 complaints per student per day
    $rateStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE student_id = ? AND created_at >= CURDATE()');
    $rateStmt->execute([$userId]);
    $dailyCount = (int) $rateStmt->fetchColumn();

    if ($dailyCount >= 5) {
        $error = 'You have reached the maximum limit of 5 complaints per day. Please check back tomorrow or contact your warden for urgent assistance.';
    } elseif ($categoryId <= 0) {
        $error = 'Please select a valid problem category.';
    } elseif (empty(trim($title)) || mb_strlen(trim($title)) > 100) {
        $error = 'Title is required and must not exceed 100 characters.';
    } elseif (empty(trim($description)) || mb_strlen(trim($description)) > 1500) {
        $error = 'Description is required and must not exceed 1500 characters.';
    } else {
        // Find category metadata
        $selectedCat = null;
        foreach ($categories as $cat) {
            if ((int) $cat['id'] === $categoryId) {
                $selectedCat = $cat;
                break;
            }
        }

        if (!$selectedCat) {
            $error = 'Selected category was not found.';
        } elseif (!empty($selectedCat['location_required']) && $locationId <= 0) {
            $error = 'Location is required for ' . htmlspecialchars($selectedCat['name']) . '. Please select your campus location.';
        } else {
            $photoPack = collect_photo_uploads($_FILES['photos'] ?? []);
            if (!$photoPack['ok']) {
                $error = $photoPack['message'];
            }
            $uploadedFiles = $photoPack['files'];

            if (!$error) {
                // Determine visibility: force private if category requires it
                if (!empty($selectedCat['force_private']) || !empty($selectedCat['sensitive'])) {
                    $finalVisibility = 'private';
                } else {
                    $finalVisibility = ($visibility === 'private') ? 'private' : 'public';
                }

                // Default priority from category
                $priority = $selectedCat['default_priority'];
                $deptId   = (int) $selectedCat['department_id'];

                $savedImageRecords = [];
                $savedFilePaths    = [];

                // Process uploaded images before DB transaction
                $imageError = false;
                foreach ($uploadedFiles as $fileItem) {
                    $uploadRes = process_image_upload($fileItem);
                    if (!$uploadRes['success']) {
                        $error = 'Image upload error: ' . $uploadRes['message'];
                        $imageError = true;
                        break;
                    }
                    $savedImageRecords[] = [
                        'stored_name' => $uploadRes['stored_name'],
                        'mime'        => $uploadRes['mime'],
                        'size'        => $uploadRes['size'],
                    ];
                    $savedFilePaths[] = UPLOAD_PATH . '/' . $uploadRes['stored_name'];
                }

                if ($imageError) {
                    // Clean up any files that were already saved
                    foreach ($savedFilePaths as $p) {
                        if (file_exists($p)) @unlink($p);
                    }
                } else {
                    // Start Database Transaction
                    $pdo->beginTransaction();
                    try {
                        // Generate Reference: SVX-YYYY-NNNNN
                        $year = date('Y');
                        $seqStmt = $pdo->prepare(
                            'SELECT MAX(CAST(SUBSTRING(reference, 10) AS UNSIGNED))
                             FROM complaints
                             WHERE reference LIKE ?
                             FOR UPDATE'
                        );
                        $seqStmt->execute(["SVX-{$year}-%"]);
                        $maxSeq = (int) $seqStmt->fetchColumn();
                        $nextSeq = $maxSeq + 1;
                        $reference = sprintf('SVX-%s-%05d', $year, $nextSeq);

                        // Insert complaint
                        $insStmt = $pdo->prepare('
                            INSERT INTO complaints (
                                reference, student_id, title, description, category_id,
                                location_id, department_id, lat, lng, gps_accuracy_m,
                                visibility, priority, status, created_at, updated_at
                            ) VALUES (
                                :ref, :student_id, :title, :desc, :cat_id,
                                :loc_id, :dept_id, :lat, :lng, :acc,
                                :visibility, :priority, \'submitted\', NOW(), NOW()
                            )
                        ');

                        $insStmt->execute([
                            'ref'        => $reference,
                            'student_id' => $userId,
                            'title'      => trim($title),
                            'desc'       => trim($description),
                            'cat_id'     => $categoryId,
                            'loc_id'     => $locationId > 0 ? $locationId : null,
                            'dept_id'    => $deptId,
                            'lat'        => $lat,
                            'lng'        => $lng,
                            'acc'        => $accuracy,
                            'visibility' => $finalVisibility,
                            'priority'   => $priority,
                        ]);

                        $complaintId = (int) $pdo->lastInsertId();

                        // Insert images
                        $imgStmt = $pdo->prepare('
                            INSERT INTO complaint_images (complaint_id, stored_name, mime, size, created_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ');
                        foreach ($savedImageRecords as $img) {
                            $imgStmt->execute([
                                $complaintId,
                                $img['stored_name'],
                                $img['mime'],
                                $img['size'],
                            ]);
                        }

                        $urgentNote = $isUrgent ? 'Student flagged this as urgent.' : null;

                        // Insert initial audit event (strictly append-only)
                        $evtStmt = $pdo->prepare('
                            INSERT INTO complaint_events (complaint_id, actor_id, type, old_value, new_value, reason, created_at)
                            VALUES (?, ?, \'created\', NULL, \'submitted\', ?, NOW())
                        ');
                        $evtStmt->execute([$complaintId, $userId, $urgentNote]);

                        // Confirmation for the reporter
                        add_notification(
                            $userId,
                            $complaintId,
                            "Your complaint #{$reference} was successfully submitted to " . $selectedCat['department_name'] . "."
                        );

                        $staffMsg = ($isUrgent ? '[URGENT] ' : '') . "New {$priority} complaint #{$reference}: " . trim($title);
                        notify_department_staff($deptId, $complaintId, $staffMsg);

                        $pdo->commit();

                        flash('success', "Complaint #{$reference} submitted successfully.");
                        redirect(base_url('student/report.php?ok=' . urlencode($reference)));

                    } catch (Exception $ex) {
                        $pdo->rollBack();
                        // Delete uploaded images on rollback
                        foreach ($savedFilePaths as $p) {
                            if (file_exists($p)) @unlink($p);
                        }
                        error_log('Complaint submission failed: ' . $ex->getMessage());
                        $error = 'Failed to submit complaint. Database error occurred. Please try again.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Report a Campus Problem';
$activeNav = 'dashboard';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">

            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="<?= base_url('student/dashboard.php') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Report Problem</li>
                </ol>
            </nav>

            <?php if ($successRef !== '' && preg_match('/^SVX-\d{4}-\d{5}$/', $successRef)): ?>
            <div class="card-solvex shadow-sm border-0 mb-5">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                        <i class="bi bi-check-lg fs-2"></i>
                    </div>
                    <h1 class="h3 fw-bold text-navy mb-2">Report submitted</h1>
                    <p class="text-muted mb-3">Your ticket reference is</p>
                    <div class="fs-3 fw-bold font-monospace text-primary mb-4"><?= e($successRef) ?></div>
                    <div class="text-start mx-auto" style="max-width: 460px;">
                        <h2 class="h6 fw-bold text-navy">What happens next</h2>
                        <ol class="small text-secondary mb-4">
                            <li>The responsible department is notified and will triage your report.</li>
                            <li>You will get a notification when status changes or staff replies.</li>
                            <li>If the department marks it resolved, accept the fix or reopen it with a note.</li>
                        </ol>
                    </div>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <a href="<?= base_url('complaint.php?ref=' . urlencode($successRef)) ?>" class="btn-solvex btn-primary">
                            View timeline
                        </a>
                        <a href="<?= base_url('student/my_complaints.php') ?>" class="btn-solvex btn-secondary">
                            My complaints
                        </a>
                        <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-ghost">
                            Report another
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card-solvex shadow-sm border-0 mb-5">
                <div class="card-header bg-white border-bottom p-4">
                    <h1 class="h3 fw-bold text-navy mb-1">Report a Campus Problem</h1>
                    <p class="text-muted small mb-3">Submit your issue to the responsible campus department with transparent resolution tracking.</p>

                    <!-- Step Progress Bar -->
                    <div class="progress-bar-solvex mb-0" id="reportProgressBar">
                        <div class="step active" data-step="1"></div>
                        <div class="step" data-step="2"></div>
                        <div class="step" data-step="3"></div>
                    </div>
                    <div class="d-flex justify-content-between small fw-semibold text-muted mt-2">
                        <span id="labelStep1" class="text-primary">1. WHAT</span>
                        <span id="labelStep2">2. WHERE</span>
                        <span id="labelStep3">3. DETAILS</span>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert-solvex danger mb-4" role="alert">
                            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Emergency Banner (dynamic for high-risk categories) -->
                    <div class="footer-emergency d-none mb-4" id="emergencyNoticeBanner">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-2 flex-shrink-0"></i>
                            <div>
                                <div class="emergency-title">Immediate Hazard / Personal Safety Warning</div>
                                <p class="mb-0">
                                    In immediate danger or active fire/electrical sparking, call 
                                    <strong>Campus Security Control (+91 44 2257 8888)</strong> or <strong>112</strong> immediately first.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="<?= base_url('student/report.php') ?>" id="reportComplaintForm" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="lat" id="gpsLat" value="<?= e($lat ?? '') ?>">
                        <input type="hidden" name="lng" id="gpsLng" value="<?= e($lng ?? '') ?>">
                        <input type="hidden" name="gps_accuracy_m" id="gpsAcc" value="<?= e($accuracy ?? '') ?>">
                        <input type="hidden" name="category_id" id="selectedCategoryId" value="<?= e($categoryId ?: '') ?>">
                        <input type="hidden" name="location_id" id="selectedLocationId" value="<?= e($locationId ?: '') ?>">

                        <!-- ══════════════════════════════════════════════ -->
                        <!-- STEP 1: WHAT (Category Tag Cards)             -->
                        <!-- ══════════════════════════════════════════════ -->
                        <div class="form-step" data-step="1">
                            <div class="mb-4">
                                <label class="form-label-solvex h5 fw-bold mb-1">
                                    What type of problem are you experiencing? <span class="text-danger">*</span>
                                </label>
                                <p class="text-muted small">Choose the primary category. Each category is routed directly to its departmental SLA desk.</p>
                            </div>

                            <div class="row g-3 mb-4" id="categoryCardsGrid">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="col-sm-6">
                                        <div class="category-card p-3 rounded-3 border h-100 cursor-pointer transition-all <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>"
                                             data-category-id="<?= (int)$cat['id'] ?>"
                                             data-force-private="<?= (int)$cat['force_private'] ?>"
                                             data-sensitive="<?= (int)$cat['sensitive'] ?>"
                                             data-location-required="<?= (int)$cat['location_required'] ?>"
                                             style="cursor: pointer;">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="category-icon-box rounded-2 p-2 bg-light text-primary d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <?= category_icon_svg((int)$cat['id'], 20) ?>
                                                    </div>
                                                    <span class="fw-bold text-navy"><?= e($cat['name']) ?></span>
                                                </div>
                                                <?php if (!empty($cat['force_private']) || !empty($cat['sensitive'])): ?>
                                                    <span class="visibility-badge private"><i class="bi bi-lock-fill"></i> Private</span>
                                                <?php else: ?>
                                                    <span class="visibility-badge public"><i class="bi bi-globe"></i> Public</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                                <span><i class="bi bi-building me-1"></i> <?= e($cat['department_name']) ?></span>
                                                <span class="badge bg-light text-secondary border">Target: <?= (int)$cat['target_days'] ?>d</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn-solvex btn-primary px-4 py-2" id="step1ContinueBtn" disabled>
                                    Next: Specify Location <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ══════════════════════════════════════════════ -->
                        <!-- STEP 2: WHERE (Cascading Location + GPS)      -->
                        <!-- ══════════════════════════════════════════════ -->
                        <div class="form-step" data-step="2" style="display: none;">
                            <div class="mb-4">
                                <label class="form-label-solvex h5 fw-bold mb-1">
                                    Where is this problem located?
                                </label>
                                <p class="text-muted small">Select your campus block, building, floor, or specific room.</p>
                            </div>

                            <div class="row g-3 mb-4">
                                <!-- Level 1: Campus / Zone -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Campus Area / Hostel</label>
                                    <select class="form-select-solvex" id="locLevel1">
                                        <option value="">-- Select Campus / Hostel --</option>
                                        <?php foreach ($rootLocations as $rLoc): ?>
                                            <option value="<?= (int)$rLoc['id'] ?>"><?= e($rLoc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Level 2: Building / Block -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Building / Academic Block</label>
                                    <select class="form-select-solvex" id="locLevel2" disabled>
                                        <option value="">-- Select Building --</option>
                                    </select>
                                </div>

                                <!-- Level 3: Floor -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Floor</label>
                                    <select class="form-select-solvex" id="locLevel3" disabled>
                                        <option value="">-- Select Floor --</option>
                                    </select>
                                </div>

                                <!-- Level 4: Specific Area / Room -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Specific Room / Corridor / Lab</label>
                                    <select class="form-select-solvex" id="locLevel4" disabled>
                                        <option value="">-- Select Room / Area --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Optional Geolocation API Button -->
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-semibold text-navy small">Pin Exact GPS Coordinates (Optional)</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Helps maintenance staff pinpoint outdoor streetlights, water taps, or pathways.</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-solvex btn-secondary btn-sm" id="btnGetLocation">
                                        <i class="bi bi-crosshair"></i> <span id="btnGpsText">Use My Location</span>
                                    </button>
                                </div>
                                <div class="small mt-2 d-none" id="gpsStatusMessage"></div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn-solvex btn-secondary px-4 py-2" id="step2BackBtn">
                                    <i class="bi bi-arrow-left me-1"></i> Back
                                </button>
                                <button type="button" class="btn-solvex btn-primary px-4 py-2" id="step2ContinueBtn">
                                    Next: Describe Problem <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ══════════════════════════════════════════════ -->
                        <!-- STEP 3: DETAILS (Title, Desc, Upload, Submit) -->
                        <!-- ══════════════════════════════════════════════ -->
                        <div class="form-step" data-step="3" style="display: none;">
                            <div class="mb-4">
                                <label class="form-label-solvex h5 fw-bold mb-1">
                                    Provide the details
                                </label>
                                <p class="text-muted small">Be specific so the maintenance technician arrives with the right tools.</p>
                            </div>

                            <!-- Title -->
                            <div class="form-group mb-3">
                                <label for="complaintTitle" class="form-label-solvex">
                                    Problem Summary (Title) <span class="text-danger">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control-solvex" 
                                    id="complaintTitle" 
                                    name="title" 
                                    maxlength="100" 
                                    value="<?= e($title) ?>" 
                                    placeholder="e.g. Broken water tap leaking on 2nd floor washroom" 
                                    required
                                >
                                <div class="d-flex justify-content-between form-text">
                                    <span>Max 100 characters</span>
                                    <span id="titleCounter">0/100</span>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!-- Description -->
                            <div class="form-group mb-4">
                                <label for="complaintDescription" class="form-label-solvex">
                                    Detailed Description <span class="text-danger">*</span>
                                </label>
                                <textarea 
                                    class="form-control-solvex" 
                                    id="complaintDescription" 
                                    name="description" 
                                    rows="5" 
                                    maxlength="1500" 
                                    placeholder="Explain the issue, when you noticed it, and any hazards..." 
                                    required
                                ><?= e($description) ?></textarea>
                                <div class="d-flex justify-content-between form-text">
                                    <span>Clear details expedite resolution.</span>
                                    <span id="descCounter">0/1500</span>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!-- Photo Dropzone (Max 3) -->
                            <div class="mb-4">
                                <label class="form-label-solvex">
                                    Photos of the Problem (Up to 3 images, max 5 MB each)
                                </label>
                                <div class="file-dropzone" id="photoDropzone">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <div class="fw-semibold text-navy">Drag &amp; drop photos here or <span class="text-primary text-decoration-underline">browse</span></div>
                                    <div class="small text-muted">Supports JPG, PNG, WebP (EXIF and GPS metadata will be safely stripped)</div>
                                    <input type="file" id="photoFileInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="d-none">
                                </div>
                                <!-- Thumbnail Previews -->
                                <div class="d-flex gap-3 mt-3 flex-wrap" id="previewContainer"></div>
                            </div>

                            <!-- Urgent hint checkbox -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="is_urgent" value="1" id="urgentCheckbox" <?= $isUrgent ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-semibold text-navy" for="urgentCheckbox">
                                    <i class="bi bi-exclamation-diamond text-warning me-1"></i> Flag as urgent requirement
                                </label>
                                <div class="form-text mt-0">
                                    Provides priority notice to department duty staff (not for life emergencies).
                                </div>
                            </div>

                            <!-- Visibility Selection -->
                            <div class="p-3 bg-light rounded-3 border mb-4" id="visibilityControlBox">
                                <label class="form-label-solvex mb-2">Visibility Settings</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="visibility" id="visPublic" value="public" <?= ($visibility === 'public') ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="visPublic">
                                            <i class="bi bi-globe me-1 text-primary"></i> Public
                                        </label>
                                        <div class="small text-muted">Visible on campus feed (reporter remains anonymous to peers).</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="visibility" id="visPrivate" value="private" <?= ($visibility === 'private') ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="visPrivate">
                                            <i class="bi bi-lock-fill me-1 text-secondary"></i> Private
                                        </label>
                                        <div class="small text-muted">Restricted exclusively to you, department staff, and admin.</div>
                                    </div>
                                </div>
                                <div class="small text-warning-dark mt-2 d-none" id="forcedPrivateNotice">
                                    <i class="bi bi-info-circle-fill me-1"></i> Locked to <strong>Private</strong> because this category handles sensitive or private matters.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn-solvex btn-secondary px-4 py-2" id="step3BackBtn">
                                    <i class="bi bi-arrow-left me-1"></i> Back
                                </button>
                                <button type="button" class="btn-solvex btn-primary px-5 py-2" id="preSubmitCheckBtn">
                                    <i class="bi bi-send-fill me-1"></i> Submit Report
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- Similar Open Complaints Detection Modal                       -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="similarComplaintsModal" tabindex="-1" aria-labelledby="similarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-navy" id="similarModalLabel">
                    <i class="bi bi-info-circle-fill text-primary me-2"></i> Similar Open Complaints Found
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Other students have already reported open issues matching your category and location in the last 14 days. 
                    If this is the same problem, click <strong>"I am also affected"</strong> instead of creating a duplicate ticket.
                </p>

                <div class="d-flex flex-column gap-3 mb-4" id="similarListContainer">
                    <!-- Populated via JavaScript -->
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <button type="button" class="btn-solvex btn-secondary" id="btnContinueSubmitAnyway">
                    No, mine is different &mdash; Submit anyway
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.category-card {
    border: 2px solid var(--clr-border) !important;
    background: var(--clr-surface);
    transition: all 0.2s ease-in-out;
}
.category-card:hover {
    border-color: var(--clr-primary) !important;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.category-card.selected {
    border-color: var(--clr-primary) !important;
    background: var(--clr-primary-50) !important;
    box-shadow: 0 0 0 3px rgba(14, 159, 142, 0.15);
}
.preview-thumb-box {
    position: relative;
    width: 90px;
    height: 90px;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--clr-border);
}
.preview-thumb-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.preview-thumb-box .btn-remove-thumb {
    position: absolute;
    top: 4px;
    right: 4px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    let selectedCatData = null;
    let selectedLocationId = null;
    let uploadedFileObjects = [];

    const categoryCards = document.querySelectorAll('.category-card');
    const step1Btn = document.getElementById('step1ContinueBtn');
    const step2Btn = document.getElementById('step2ContinueBtn');
    const step2Back = document.getElementById('step2BackBtn');
    const step3Back = document.getElementById('step3BackBtn');
    const preSubmitBtn = document.getElementById('preSubmitCheckBtn');
    const form = document.getElementById('reportComplaintForm');
    if (!form) {
        return;
    }

    // Counters
    const titleInput = document.getElementById('complaintTitle');
    const descInput = document.getElementById('complaintDescription');
    const titleCounter = document.getElementById('titleCounter');
    const descCounter = document.getElementById('descCounter');

    titleInput.addEventListener('input', () => {
        titleCounter.textContent = `${titleInput.value.length}/100`;
    });
    descInput.addEventListener('input', () => {
        descCounter.textContent = `${descInput.value.length}/1500`;
    });

    // ── STEP NAVIGATION ─────────────────────────────────────
    function setStep(step) {
        currentStep = step;
        document.querySelectorAll('.form-step').forEach(el => el.style.display = 'none');
        document.querySelector(`.form-step[data-step="${step}"]`).style.display = 'block';

        // Update progress bar
        document.querySelectorAll('#reportProgressBar .step').forEach((el, idx) => {
            el.classList.toggle('active', idx + 1 <= step);
            el.classList.toggle('completed', idx + 1 < step);
        });

        // Update labels
        ['1', '2', '3'].forEach(i => {
            const lbl = document.getElementById(`labelStep${i}`);
            if (lbl) {
                lbl.classList.toggle('text-primary', i == step);
                lbl.classList.toggle('fw-bold', i == step);
            }
        });

        window.scrollTo({ top: 120, behavior: 'smooth' });
    }

    // ── STEP 1: Category Card Selection ─────────────────────
    categoryCards.forEach(card => {
        card.addEventListener('click', function() {
            categoryCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            const catId = this.dataset.categoryId;
            const forcePriv = this.dataset.forcePrivate === '1';
            const sensitive = this.dataset.sensitive === '1';
            const locReq = this.dataset.locationRequired === '1';

            selectedCatData = { id: catId, forcePriv, sensitive, locReq };
            document.getElementById('selectedCategoryId').value = catId;
            step1Btn.removeAttribute('disabled');

            // Emergency Notice banner for Safety/Harassment (10) and Electrical (3)
            const emergencyBanner = document.getElementById('emergencyNoticeBanner');
            if (catId === '3' || catId === '10') {
                emergencyBanner.classList.remove('d-none');
            } else {
                emergencyBanner.classList.add('d-none');
            }

            // Lock to Private if force_private or sensitive
            const visPublic = document.getElementById('visPublic');
            const visPrivate = document.getElementById('visPrivate');
            const forcedNotice = document.getElementById('forcedPrivateNotice');

            if (forcePriv || sensitive) {
                visPrivate.checked = true;
                visPublic.disabled = true;
                forcedNotice.classList.remove('d-none');
            } else {
                visPublic.disabled = false;
                forcedNotice.classList.add('d-none');
            }
        });
    });

    step1Btn.addEventListener('click', () => {
        if (!selectedCatData) return;
        setStep(2);
    });

    // ── STEP 2: Cascading Locations ─────────────────────────
    const loc1 = document.getElementById('locLevel1');
    const loc2 = document.getElementById('locLevel2');
    const loc3 = document.getElementById('locLevel3');
    const loc4 = document.getElementById('locLevel4');

    async function loadLocationChildren(parentId, targetSelect, placeholder) {
        targetSelect.innerHTML = `<option value="">-- Loading... --</option>`;
        targetSelect.disabled = true;

        try {
            const res = await fetch(`<?= base_url('api/locations.php') ?>?parent_id=${parentId}`);
            const data = await res.json();
            targetSelect.innerHTML = `<option value="">-- ${placeholder} --</option>`;

            if (data.success && data.locations.length > 0) {
                data.locations.forEach(loc => {
                    const opt = document.createElement('option');
                    opt.value = loc.id;
                    opt.textContent = loc.name;
                    targetSelect.appendChild(opt);
                });
                targetSelect.disabled = false;
            }
        } catch (e) {
            targetSelect.innerHTML = `<option value="">-- Error loading locations --</option>`;
        }
    }

    loc1.addEventListener('change', function() {
        loc2.innerHTML = '<option value="">-- Select Building --</option>'; loc2.disabled = true;
        loc3.innerHTML = '<option value="">-- Select Floor --</option>';    loc3.disabled = true;
        loc4.innerHTML = '<option value="">-- Select Room / Area --</option>'; loc4.disabled = true;
        selectedLocationId = this.value || null;
        document.getElementById('selectedLocationId').value = selectedLocationId || '';

        if (this.value) {
            loadLocationChildren(this.value, loc2, 'Select Building');
        }
    });

    loc2.addEventListener('change', function() {
        loc3.innerHTML = '<option value="">-- Select Floor --</option>';    loc3.disabled = true;
        loc4.innerHTML = '<option value="">-- Select Room / Area --</option>'; loc4.disabled = true;
        selectedLocationId = this.value || loc1.value;
        document.getElementById('selectedLocationId').value = selectedLocationId;

        if (this.value) {
            loadLocationChildren(this.value, loc3, 'Select Floor');
        }
    });

    loc3.addEventListener('change', function() {
        loc4.innerHTML = '<option value="">-- Select Room / Area --</option>'; loc4.disabled = true;
        selectedLocationId = this.value || loc2.value;
        document.getElementById('selectedLocationId').value = selectedLocationId;

        if (this.value) {
            loadLocationChildren(this.value, loc4, 'Select Room / Area');
        }
    });

    loc4.addEventListener('change', function() {
        selectedLocationId = this.value || loc3.value;
        document.getElementById('selectedLocationId').value = selectedLocationId;
    });

    // Geolocation API
    const btnGps = document.getElementById('btnGetLocation');
    const gpsMsg = document.getElementById('gpsStatusMessage');

    btnGps.addEventListener('click', function() {
        if (!navigator.geolocation) {
            gpsMsg.className = 'small mt-2 text-danger';
            gpsMsg.textContent = 'Geolocation is not supported by your browser.';
            gpsMsg.classList.remove('d-none');
            return;
        }

        btnGps.disabled = true;
        document.getElementById('btnGpsText').textContent = 'Acquiring GPS...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const acc = Math.round(pos.coords.accuracy);

                document.getElementById('gpsLat').value = lat;
                document.getElementById('gpsLng').value = lng;
                document.getElementById('gpsAcc').value = acc;

                gpsMsg.className = 'small mt-2 text-success fw-semibold';
                gpsMsg.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Pinned: ${lat.toFixed(5)}, ${lng.toFixed(5)} (&plusmn;${acc}m)`;
                gpsMsg.classList.remove('d-none');
                document.getElementById('btnGpsText').textContent = 'GPS Saved';
                btnGps.disabled = false;
            },
            (err) => {
                gpsMsg.className = 'small mt-2 text-muted';
                gpsMsg.textContent = 'Location access was not granted. You can still submit without GPS.';
                gpsMsg.classList.remove('d-none');
                document.getElementById('btnGpsText').textContent = 'Use My Location';
                btnGps.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });

    step2Back.addEventListener('click', () => setStep(1));
    step2Btn.addEventListener('click', () => {
        if (selectedCatData && selectedCatData.locReq && !selectedLocationId) {
            alert('Location is required for this category. Please select your campus area.');
            return;
        }
        setStep(3);
    });

    step3Back.addEventListener('click', () => setStep(2));

    // ── Photo Dropzone & Client Thumbnails ───────────────────
    const dropzone = document.getElementById('photoDropzone');
    const fileInput = document.getElementById('photoFileInput');
    const previewContainer = document.getElementById('previewContainer');

    let syncingFiles = false;

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        handleAddFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
        if (syncingFiles) return;
        handleAddFiles(fileInput.files);
    });

    function syncFileInput() {
        const dt = new DataTransfer();
        uploadedFileObjects.forEach(f => dt.items.add(f));
        syncingFiles = true;
        fileInput.files = dt.files;
        syncingFiles = false;
    }

    function handleAddFiles(files) {
        if (!files.length) return;

        Array.from(files).forEach((file) => {
            if (uploadedFileObjects.length >= 3) {
                alert('You can upload a maximum of 3 photos.');
                return;
            }
            if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                alert(`File "${file.name}" is not a supported format (JPG, PNG, WebP).`);
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert(`File "${file.name}" exceeds 5 MB.`);
                return;
            }
            uploadedFileObjects.push(file);
        });

        renderPreviews();
        syncFileInput();
    }

    function renderPreviews() {
        previewContainer.innerHTML = '';
        uploadedFileObjects.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const box = document.createElement('div');
                box.className = 'preview-thumb-box';
                box.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <button type="button" class="btn-remove-thumb" data-index="${index}" aria-label="Remove photo">&times;</button>
                `;
                const removeBtn = box.querySelector('.btn-remove-thumb');
                removeBtn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    uploadedFileObjects.splice(index, 1);
                    renderPreviews();
                    syncFileInput();
                });
                previewContainer.appendChild(box);
            };
            reader.readAsDataURL(file);
        });
    }

    // ── Similar Open Complaints Duplicate Detection ─────────
    const similarModalEl = document.getElementById('similarComplaintsModal');
    const similarModal = new bootstrap.Modal(similarModalEl);
    const similarContainer = document.getElementById('similarListContainer');
    const btnSubmitAnyway = document.getElementById('btnContinueSubmitAnyway');

    btnSubmitAnyway.addEventListener('click', () => {
        similarModal.hide();
        form.submit();
    });

    preSubmitBtn.addEventListener('click', async function() {
        const titleVal = titleInput.value.trim();
        const descVal = descInput.value.trim();

        if (!titleVal) {
            titleInput.classList.add('is-invalid');
            titleInput.focus();
            return;
        }
        titleInput.classList.remove('is-invalid');

        if (!descVal) {
            descInput.classList.add('is-invalid');
            descInput.focus();
            return;
        }
        descInput.classList.remove('is-invalid');

        // Query similar complaints
        if (selectedCatData && selectedCatData.id) {
            try {
                preSubmitBtn.disabled = true;
                preSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Checking campus reports...';

                const locParam = selectedLocationId ? `&location_id=${selectedLocationId}` : '';
                const checkUrl = `<?= base_url('api/similar.php') ?>?category_id=${selectedCatData.id}${locParam}`;
                const res = await fetch(checkUrl);
                const data = await res.json();

                preSubmitBtn.disabled = false;
                preSubmitBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Submit Report';

                if (data.success && data.count > 0) {
                    similarContainer.innerHTML = '';
                    data.complaints.forEach(item => {
                        const card = document.createElement('div');
                        card.className = 'p-3 bg-white rounded-3 border';
                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary-subtle text-primary fw-bold">${item.reference}</span>
                                <span class="days-chip"><i class="bi bi-clock-history"></i> Open ${item.days_open} days</span>
                            </div>
                            <h6 class="fw-bold text-navy mb-1">${Solvex.toast.escapeHtml(item.title)}</h6>
                            <div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i> ${item.location_name}</div>
                            <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                                <span class="small text-secondary"><i class="bi bi-people me-1"></i> <strong>${item.affected_count}</strong> students affected</span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn-solvex btn-accent btn-sm btn-mark-affected" data-id="${item.id}">
                                        <i class="bi bi-hand-thumbs-up me-1"></i> I am also affected
                                    </button>
                                    <a href="${item.view_url}" target="_blank" class="btn-solvex btn-ghost btn-sm">
                                        View Details <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        `;
                        similarContainer.appendChild(card);
                    });

                    // Bind affected buttons in modal
                    similarContainer.querySelectorAll('.btn-mark-affected').forEach(btn => {
                        btn.addEventListener('click', async function() {
                            const compId = this.dataset.id;
                            const csrf = document.querySelector('input[name="csrf_token"]').value;
                            this.disabled = true;

                            const formData = new FormData();
                            formData.append('complaint_id', compId);
                            formData.append('csrf_token', csrf);

                            try {
                                const resp = await fetch('<?= base_url('api/affected.php') ?>', {
                                    method: 'POST',
                                    body: formData
                                });
                                const rData = await resp.json();
                                if (rData.success) {
                                    alert(rData.message);
                                    similarModal.hide();
                                    window.location.href = `<?= base_url('complaint.php') ?>?id=${compId}`;
                                } else {
                                    alert(rData.message);
                                    this.disabled = false;
                                }
                            } catch (err) {
                                alert('Could not register affected status.');
                                this.disabled = false;
                            }
                        });
                    });

                    similarModal.show();
                    return;
                }
            } catch (e) {
                preSubmitBtn.disabled = false;
                preSubmitBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Submit Report';
            }
        }

        // No duplicates found, submit form directly
        form.submit();
    });
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
