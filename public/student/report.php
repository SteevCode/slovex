<?php
/**
 * Solvex Report — Hostel Complaint Intake
 * 
 * Clean, single-page hostel maintenance submission form:
 * - Category selection (6 core hostel categories)
 * - Location selection (student's room or hostel common facilities)
 * - Priority selection (low, medium, high, critical)
 * - Title and description
 * - Optional photo attachments (up to 2 photos)
 * - Automatic private visibility & pending status
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

// Fetch student profile details (hostel, room)
$profileStmt = $pdo->prepare('
    SELECT u.id, u.full_name, u.reg_no, u.hostel_id, u.room_no,
           h.name AS hostel_name
    FROM users u
    LEFT JOIN locations h ON h.id = u.hostel_id
    WHERE u.id = ?
');
$profileStmt->execute([$userId]);
$studentProfile = $profileStmt->fetch();

// Fetch 6 active hostel categories
$catStmt = $pdo->query('
    SELECT c.id, c.name, c.icon, c.department_id, c.default_priority,
           c.target_days, d.name AS department_name
    FROM categories c
    JOIN departments d ON d.id = c.department_id
    WHERE c.is_active = 1
    ORDER BY c.id ASC
');
$categories = $catStmt->fetchAll();

// Fetch hostel facilities and rooms for location dropdown
$locationsStmt = $pdo->query('
    SELECT l.id, l.name, l.type,
           COALESCE(h.name, l.name) AS hostel_heading,
           CASE
               WHEN f.name IS NOT NULL THEN CONCAT(f.name, " — ", l.name)
               ELSE l.name
           END AS area_label
    FROM locations l
    LEFT JOIN locations f ON f.id = l.parent_id
    LEFT JOIN locations h ON h.id = COALESCE(f.parent_id, l.parent_id)
    WHERE l.type IN ("room", "washroom", "corridor", "room_area")
       OR l.parent_id IS NOT NULL
    ORDER BY hostel_heading ASC, area_label ASC
');
$allLocations = $locationsStmt->fetchAll();

$locationsByHostel = [];
foreach ($allLocations as $loc) {
    $heading = $loc['hostel_heading'] ?? 'Hostel Facilities';
    $locationsByHostel[$heading][] = $loc;
}

$error       = null;
$successRef  = get('ok', '');
$categoryId  = (int) post('category_id', '0');
$locationId  = (int) post('location_id', '0');
$priority    = post('priority', 'medium');
$title       = post('title', '');
$description = post('description', '');

// Pre-fill student's assigned room location if available
$myHostelId   = (int) ($studentProfile['hostel_id'] ?? 0);
$myRoomNo     = trim($studentProfile['room_no'] ?? '');
$myHostelName = $studentProfile['hostel_name'] ?? '';

// Handle Form Submission
if (is_post()) {
    csrf_verify();

    // 1. Rate Limit: Max 5 complaints per student per day
    $rateStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE student_id = ? AND created_at >= CURDATE()');
    $rateStmt->execute([$userId]);
    $dailyCount = (int) $rateStmt->fetchColumn();

    if ($dailyCount >= 5) {
        $error = 'You have reached the maximum limit of 5 complaints per day. Please check with your warden for urgent requests.';
    } elseif ($categoryId <= 0) {
        $error = 'Please select a complaint category.';
    } elseif (empty(trim($title)) || mb_strlen(trim($title)) > 100) {
        $error = 'Title is required and must not exceed 100 characters.';
    } elseif (empty(trim($description)) || mb_strlen(trim($description)) > 1500) {
        $error = 'Description is required and must not exceed 1500 characters.';
    } else {
        // Validate category
        $selectedCat = null;
        foreach ($categories as $cat) {
            if ((int) $cat['id'] === $categoryId) {
                $selectedCat = $cat;
                break;
            }
        }

        if (!$selectedCat) {
            $error = 'Selected category was not found.';
        } else {
            // Priority check
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            if (!in_array($priority, $validPriorities, true)) {
                $priority = $selectedCat['default_priority'] ?? 'medium';
            }

            // Photo upload handling (max 2 photos)
            $photoPack = collect_photo_uploads($_FILES['photos'] ?? []);
            if (!$photoPack['ok']) {
                $error = $photoPack['message'];
            } elseif (count($photoPack['files']) > 2) {
                $error = 'You may upload a maximum of 2 photos per complaint.';
            }

            $uploadedFiles = $photoPack['files'] ?? [];

            if (!$error) {
                $savedImageRecords = [];
                $savedFilePaths    = [];
                $imageError        = false;

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
                    foreach ($savedFilePaths as $p) {
                        if (file_exists($p)) @unlink($p);
                    }
                } else {
                    $deptId = (int) $selectedCat['department_id'];

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

                        // If student selected their own room and no explicit locationId was chosen
                        $finalLocationId = $locationId > 0 ? $locationId : ($myHostelId > 0 ? $myHostelId : null);

                        // Insert complaint (Hostel complaints default to private & pending)
                        $insStmt = $pdo->prepare('
                            INSERT INTO complaints (
                                reference, student_id, title, description, category_id,
                                location_id, department_id, visibility, priority, status,
                                created_at, updated_at
                            ) VALUES (
                                :ref, :student_id, :title, :desc, :cat_id,
                                :loc_id, :dept_id, "private", :priority, "pending",
                                NOW(), NOW()
                            )
                        ');

                        $insStmt->execute([
                            'ref'        => $reference,
                            'student_id' => $userId,
                            'title'      => trim($title),
                            'desc'       => trim($description),
                            'cat_id'     => $categoryId,
                            'loc_id'     => $finalLocationId,
                            'dept_id'    => $deptId,
                            'priority'   => $priority,
                        ]);

                        $complaintId = (int) $pdo->lastInsertId();

                        // Insert images
                        if (!empty($savedImageRecords)) {
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
                        }

                        // Insert initial audit event
                        $roomDetails = $myRoomNo !== '' ? " (Student Room: {$myRoomNo})" : '';
                        $evtStmt = $pdo->prepare('
                            INSERT INTO complaint_events (complaint_id, actor_id, type, old_value, new_value, reason, created_at)
                            VALUES (?, ?, "created", NULL, "pending", ?, NOW())
                        ');
                        $evtStmt->execute([
                            $complaintId,
                            $userId,
                            "Hostel complaint submitted by {$user['full_name']}{$roomDetails}"
                        ]);

                        // Confirmation notification for the student
                        add_notification(
                            $userId,
                            $complaintId,
                            "Your hostel complaint #{$reference} has been received and queued for warden review."
                        );

                        // Escalation notice for warden
                        notify_all_admins_event(
                            $complaintId,
                            "New hostel complaint #{$reference} ({$selectedCat['name']}) submitted by {$user['full_name']}."
                        );

                        $pdo->commit();

                        flash('success', "Complaint #{$reference} submitted successfully.");
                        redirect(base_url('student/report.php?ok=' . urlencode($reference)));

                    } catch (Exception $ex) {
                        $pdo->rollBack();
                        foreach ($savedFilePaths as $p) {
                            if (file_exists($p)) @unlink($p);
                        }
                        error_log('Hostel complaint submission error: ' . $ex->getMessage());
                        $error = 'Database error while registering your complaint. Please try again.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Submit Hostel Complaint';
$activeNav = 'dashboard';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">

            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="<?= base_url('student/dashboard.php') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('student/my_complaints.php') ?>">My Complaints</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Submit Complaint</li>
                </ol>
            </nav>

            <?php if ($successRef !== '' && preg_match('/^SVX-\d{4}-\d{5}$/', $successRef)): ?>
            <!-- Success Confirmation Card -->
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                        <i class="bi bi-check-lg fs-2"></i>
                    </div>
                    <h1 class="h3 fw-bold text-dark mb-2">Complaint Submitted</h1>
                    <p class="text-secondary mb-3">Your tracking reference number is</p>
                    <div class="fs-2 fw-bold font-monospace text-primary mb-4"><?= e($successRef) ?></div>
                    
                    <div class="text-start mx-auto p-3 rounded-2 bg-light border mb-4" style="max-width: 480px;">
                        <div class="fw-bold text-dark mb-2"><i class="bi bi-info-circle me-1 text-primary"></i> Maintenance Workflow</div>
                        <ol class="small text-secondary mb-0 ps-3">
                            <li class="mb-1">The hostel warden reviews your complaint and assigns maintenance staff.</li>
                            <li class="mb-1">Assigned staff inspects the problem and performs the repair.</li>
                            <li>Once marked resolved, you will verify and confirm resolution.</li>
                        </ol>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <a href="<?= base_url('complaint.php?ref=' . urlencode($successRef)) ?>" class="btn-solvex btn-primary">
                            <i class="bi bi-eye me-1"></i> Track Complaint Status
                        </a>
                        <a href="<?= base_url('student/my_complaints.php') ?>" class="btn-solvex btn-secondary">
                            View All My Complaints
                        </a>
                        <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-ghost">
                            Submit Another
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>

            <!-- Complaint Submission Form Card -->
            <div class="card shadow-sm border mb-5">
                <div class="card-header bg-white border-0 p-4 pb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h4 fw-bold text-dark mb-1">Hostel Maintenance Request</h1>
                            <p class="text-secondary small mb-0">Report facility, electrical, plumbing, or room issues to the hostel warden.</p>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary border">Private Ticket</span>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= base_url('student/report.php') ?>" enctype="multipart/form-data" novalidate id="hostelReportForm">
                        <?= csrf_field() ?>

                        <!-- 1. Category Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark mb-2">
                                1. Issue Category <span class="text-danger">*</span>
                            </label>
                            <div class="row g-2">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="col-md-6">
                                        <label class="category-select-card p-3 rounded-2 border d-flex align-items-center gap-3 w-100 cursor-pointer h-100 <?= ($categoryId === (int)$cat['id']) ? 'border-primary bg-light' : '' ?>" style="cursor: pointer;">
                                            <input type="radio" name="category_id" value="<?= (int)$cat['id'] ?>" class="form-check-input mt-0 flex-shrink-0" <?= ($categoryId === (int)$cat['id']) ? 'checked' : '' ?> required>
                                            <div>
                                                <div class="fw-semibold text-dark"><?= e($cat['name']) ?></div>
                                                <div class="small text-secondary">Target SLA: <?= (int)$cat['target_days'] ?> working days</div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2. Location Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark mb-1">
                                2. Hostel Location & Room <span class="text-danger">*</span>
                            </label>
                            
                            <?php if ($myHostelName || $myRoomNo): ?>
                                <div class="p-3 bg-light rounded-2 border mb-3">
                                    <div class="small fw-semibold text-secondary text-uppercase mb-1">Your Registered Room</div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="text-dark fw-bold">
                                            <i class="bi bi-building me-1 text-primary"></i> <?= e($myHostelName ?: 'Hostel') ?> &bull; Room <?= e($myRoomNo ?: 'Assigned') ?>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border">Registered</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-2">
                                <label for="locationSelect" class="form-label small text-secondary">Specific Room, Floor, or Common Facility</label>
                                <select class="form-select" id="locationSelect" name="location_id">
                                    <option value="0">-- Select Specific Area / Common Facility --</option>
                                    <?php foreach ($locationsByHostel as $hostelName => $locItems): ?>
                                        <optgroup label="<?= e($hostelName) ?>">
                                            <?php foreach ($locItems as $item): ?>
                                                <option value="<?= (int)$item['id'] ?>" <?= ($locationId === (int)$item['id']) ? 'selected' : '' ?>>
                                                    <?= e($item['area_label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small text-secondary">
                                    Select the facility (e.g. Dining Hall, Common Washroom) or leave as default for your room.
                                </div>
                            </div>
                        </div>

                        <!-- 3. Priority Selection -->
                        <div class="mb-4">
                            <label for="prioritySelect" class="form-label fw-bold text-dark mb-1">
                                3. Problem Priority
                            </label>
                            <select class="form-select" id="prioritySelect" name="priority">
                                <option value="low" <?= ($priority === 'low') ? 'selected' : '' ?>>Low — Routine issue, minor inconvenience</option>
                                <option value="medium" <?= ($priority === 'medium') ? 'selected' : '' ?>>Medium — Normal maintenance (Standard)</option>
                                <option value="high" <?= ($priority === 'high') ? 'selected' : '' ?>>High — Significant disruption (No water / no power in room)</option>
                                <option value="critical" <?= ($priority === 'critical') ? 'selected' : '' ?>>Critical — Safety hazard or major leak (Emergency)</option>
                            </select>
                        </div>

                        <!-- 4. Title -->
                        <div class="mb-4">
                            <label for="titleInput" class="form-label fw-bold text-dark mb-1">
                                4. Problem Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="titleInput" name="title" maxlength="100" placeholder="e.g. Broken water tap, fan not working, door latch stuck" value="<?= e($title) ?>" required>
                            <div class="form-text small text-secondary">Brief summary in 100 characters or less.</div>
                        </div>

                        <!-- 5. Description -->
                        <div class="mb-4">
                            <label for="descInput" class="form-label fw-bold text-dark mb-1">
                                5. Problem Details <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="descInput" name="description" rows="5" maxlength="1500" placeholder="Please provide specific details: when the problem started, exact position in the room, and any relevant observations..." required><?= e($description) ?></textarea>
                            <div class="d-flex justify-content-between small text-secondary mt-1">
                                <span>Be precise to help maintenance staff prepare replacement parts.</span>
                                <span id="charCount">0 / 1500</span>
                            </div>
                        </div>

                        <!-- 6. Photos (Up to 2 optional photos) -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark mb-1">
                                6. Photos of Problem (Optional, up to 2)
                            </label>
                            <input type="file" class="form-control" name="photos[]" id="photosInput" accept="image/jpeg,image/png,image/webp" multiple>
                            <div class="form-text small text-secondary">
                                Supported formats: JPG, PNG, WebP. Maximum 2 photos, up to 5 MB each.
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                            <a href="<?= base_url('student/dashboard.php') ?>" class="btn-solvex btn-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn-solvex btn-primary px-4 py-2" id="submitBtn">
                                <i class="bi bi-send-fill me-1"></i> Submit Complaint
                            </button>
                        </div>
                    </form>

                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const descInput = document.getElementById('descInput');
    const charCount = document.getElementById('charCount');
    if (descInput && charCount) {
        function updateCount() {
            charCount.textContent = descInput.value.length + ' / 1500';
        }
        descInput.addEventListener('input', updateCount);
        updateCount();
    }

    // Highlight category card on click
    const categoryRadios = document.querySelectorAll('input[name="category_id"]');
    categoryRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.category-select-card').forEach(function(card) {
                card.classList.remove('border-primary', 'bg-light');
            });
            if (this.checked) {
                this.closest('.category-select-card').classList.add('border-primary', 'bg-light');
            }
        });
    });

    // Enforce max 2 photos on client side
    const photosInput = document.getElementById('photosInput');
    if (photosInput) {
        photosInput.addEventListener('change', function() {
            if (this.files && this.files.length > 2) {
                alert('You can upload a maximum of 2 photos.');
                this.value = '';
            }
        });
    }
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
