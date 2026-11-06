<?php
/**
 * Solvex Report — Admin Student Master CSV Import
 * 
 * Secure two-phase CSV processing:
 * 1. Upload & Row-by-Row validation (checks register_no, full_name, department, year, hosteller)
 * 2. Preview with detailed errors
 * 3. Transactional bulk UPSERT into student_master + sync with users table
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

$previewData = $_SESSION['csv_import_preview'] ?? null;
$importErrors = $_SESSION['csv_import_errors'] ?? [];

if (is_post()) {
    csrf_verify();
    $action = post('action');

    // 1. Process Upload & Validate
    if ($action === 'upload_csv') {
        unset($_SESSION['csv_import_preview'], $_SESSION['csv_import_errors']);
        
        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Please choose a valid CSV file.');
            redirect(base_url('admin/import.php'));
        }

        $tmpFile = $_FILES['csv_file']['tmp_name'];
        if ($_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
            flash('danger', 'File exceeds the 2 MB limit.');
            redirect(base_url('admin/import.php'));
        }

        $handle = fopen($tmpFile, 'r');
        if (!$handle) {
            flash('danger', 'Could not open uploaded CSV file.');
            redirect(base_url('admin/import.php'));
        }

        $rows = [];
        $errors = [];
        $rowNum = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                // Header row check (skip if header contains register_no)
                if (isset($data[0]) && stripos($data[0], 'register') !== false) {
                    continue;
                }
            }

            if (empty(array_filter($data))) {
                continue; // Skip completely empty lines
            }

            $regNo     = trim($data[0] ?? '');
            $fullName  = trim($data[1] ?? '');
            $dept      = trim($data[2] ?? '');
            $year      = (int) trim($data[3] ?? '1');
            $hosteller = (int) trim($data[4] ?? '0');

            $rowError = null;
            if (empty($regNo) || !preg_match('/^[A-Za-z0-9_-]{3,20}$/', $regNo)) {
                $rowError = 'Invalid or missing Register No.';
            } elseif (empty($fullName)) {
                $rowError = 'Missing Full Name.';
            } elseif ($year < 1 || $year > 5) {
                $rowError = 'Year must be between 1 and 5.';
            } elseif ($hosteller !== 0 && $hosteller !== 1) {
                $rowError = 'Hosteller must be 0 (Day Scholar) or 1 (Hosteller).';
            }

            if ($rowError) {
                $errors[] = "Row {$rowNum} [{$regNo}]: {$rowError}";
            }

            $rows[] = [
                'row'       => $rowNum,
                'reg_no'    => $regNo,
                'name'      => $fullName,
                'dept'      => $dept,
                'year'      => $year,
                'hosteller' => $hosteller,
                'valid'     => ($rowError === null),
                'error'     => $rowError
            ];
        }
        fclose($handle);

        $_SESSION['csv_import_preview'] = $rows;
        $_SESSION['csv_import_errors']  = $errors;

        if (!empty($errors)) {
            flash('danger', 'Found ' . count($errors) . ' validation error(s). Please review below.');
        } else {
            flash('success', 'CSV parsed successfully! ' . count($rows) . ' records ready for import.');
        }
        redirect(base_url('admin/import.php'));
    }

    // 2. Commit Transactional Upsert
    if ($action === 'commit_import') {
        if (empty($previewData)) {
            flash('danger', 'No parsed preview data found. Please upload again.');
            redirect(base_url('admin/import.php'));
        }

        if (!empty($importErrors)) {
            flash('danger', 'Cannot import CSV with validation errors. Please fix and re-upload.');
            redirect(base_url('admin/import.php'));
        }

        $pdo->beginTransaction();
        try {
            $upsertMaster = $pdo->prepare('
                INSERT INTO student_master (register_no, full_name, department, year, hosteller)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    full_name = VALUES(full_name),
                    department = VALUES(department),
                    year = VALUES(year),
                    hosteller = VALUES(hosteller)
            ');

            $syncUser = $pdo->prepare('
                UPDATE users SET
                    full_name = ?, department = ?, year = ?, hosteller = ?
                WHERE register_no = ? AND role = \'student\'
            ');

            $count = 0;
            foreach ($previewData as $item) {
                if (!$item['valid']) continue;
                $upsertMaster->execute([$item['reg_no'], $item['name'], $item['dept'], $item['year'], $item['hosteller']]);
                $syncUser->execute([$item['name'], $item['dept'], $item['year'], $item['hosteller'], $item['reg_no']]);
                $count++;
            }

            $pdo->commit();
            unset($_SESSION['csv_import_preview'], $_SESSION['csv_import_errors']);

            admin_log($user['id'], 'import_student_master', 'student_master', "Upserted {$count} student records via CSV");
            flash('success', "Successfully imported and synced {$count} student records.");
            redirect(base_url('admin/import.php'));

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('CSV import error: ' . $e->getMessage());
            flash('danger', 'Database transaction failed during import: ' . $e->getMessage());
            redirect(base_url('admin/import.php'));
        }
    }

    // 3. Clear Preview
    if ($action === 'clear_preview') {
        unset($_SESSION['csv_import_preview'], $_SESSION['csv_import_errors']);
        flash('info', 'Import preview cleared.');
        redirect(base_url('admin/import.php'));
    }
}

// Current student master count
$currentCount = (int) $pdo->query('SELECT COUNT(*) FROM student_master')->fetchColumn();

$pageTitle = 'Student Master Import';
$activeNav = 'master';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1"><i class="bi bi-file-earmark-spreadsheet-fill text-primary me-2"></i>Student Master CSV Import</h1>
            <p class="text-muted small mb-0">Current database contains <strong><?= $currentCount ?></strong> enrolled students.</p>
        </div>
        <a href="<?= base_url('admin/master.php') ?>" class="btn-solvex btn-ghost btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Master
        </a>
    </div>

    <!-- Upload Card -->
    <div class="card-solvex border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="h6 fw-bold text-navy mb-2"><i class="bi bi-upload text-primary me-1"></i> Upload College Registry CSV</h5>
            <p class="text-muted small mb-3">
                Expected CSV format: <code>register_no, full_name, department, year, hosteller</code> (1 for Hosteller, 0 for Day Scholar).
            </p>

            <form method="POST" action="<?= base_url('admin/import.php') ?>" enctype="multipart/form-data" class="row g-3 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_csv">
                
                <div class="col-md-8">
                    <input type="file" name="csv_file" class="form-control-solvex" accept=".csv" required>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn-solvex btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i> Validate &amp; Preview
                    </button>
                    <?php if (!empty($previewData)): ?>
                        <button type="submit" formaction="<?= base_url('admin/import.php') ?>" name="action" value="clear_preview" class="btn-solvex btn-secondary">
                            Clear
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Table -->
    <?php if (!empty($previewData)): ?>
        <div class="card-solvex border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="h6 fw-bold text-navy mb-0">
                        CSV Preview (<?= count($previewData) ?> rows parsed)
                    </h5>
                    <?php if (!empty($importErrors)): ?>
                        <span class="badge bg-danger mt-1"><?= count($importErrors) ?> validation error(s) &mdash; Import blocked until fixed</span>
                    <?php else: ?>
                        <span class="badge bg-success mt-1">All rows valid &amp; verified</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($importErrors)): ?>
                    <form method="POST" action="<?= base_url('admin/import.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="commit_import">
                        <button type="submit" class="btn-solvex btn-primary btn-sm px-4" onclick="return confirm('Execute upsert of all valid student records?');">
                            <i class="bi bi-check-lg me-1"></i> Confirm &amp; Import All
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                        <thead class="table-light small text-uppercase sticky-top">
                            <tr>
                                <th class="ps-4">Row</th>
                                <th>Register No</th>
                                <th>Full Name</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Hosteller</th>
                                <th class="pe-4 text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData as $item): ?>
                                <tr class="<?= !$item['valid'] ? 'table-danger' : '' ?>">
                                    <td class="ps-4 text-muted small">#<?= (int)$item['row'] ?></td>
                                    <td class="font-monospace fw-bold text-navy"><?= e($item['reg_no']) ?></td>
                                    <td><?= e($item['name']) ?></td>
                                    <td><?= e($item['dept']) ?></td>
                                    <td>Year <?= (int)$item['year'] ?></td>
                                    <td><?= $item['hosteller'] ? 'Hosteller' : 'Day Scholar' ?></td>
                                    <td class="pe-4 text-end">
                                        <?php if ($item['valid']): ?>
                                            <span class="badge bg-success">Valid</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="<?= e($item['error']) ?>"><?= e($item['error']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

