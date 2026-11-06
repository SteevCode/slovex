<?php
/**
 * Solvex Report — Living Design System Styleguide
 * 
 * Demonstrates all tokens, typography, surfaces, buttons, forms, status tags,
 * priority pills, stat cards, empty states, and alert components.
 * Gated to local development environment.
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/ui.php';

// Security Gate: local development only
if (defined('APP_ENV') && APP_ENV === 'production') {
    http_response_code(404);
    require SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

$pageTitle = 'Design System & Component Styleguide';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">
    <?= page_header(
        'Quiet Public-Records Office Styleguide',
        'Living component catalog for the Solvex Report civic design system (WCAG 2.2 AA compliant).',
        [
            ['url' => base_url(), 'label' => 'Back to Application', 'class' => 'btn-secondary']
        ]
    ) ?>

    <!-- 1. Design Tokens & Color Palette -->
    <section class="mb-5">
        <h2 class="h4 mb-3 border-bottom pb-2">1. Color Palette & Surfaces</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="p-3 border rounded-1 bg-white mb-2">
                    <div class="fw-bold">--clr-surface</div>
                    <div class="text-muted small">#FFFFFF / #1A1D23</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded-1 mb-2" style="background: var(--clr-sunken);">
                    <div class="fw-bold">--clr-sunken</div>
                    <div class="text-muted small">#EFEDE7 / #15181E</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded-1 text-white mb-2" style="background: var(--clr-accent-fill);">
                    <div class="fw-bold">--clr-accent-fill</div>
                    <div class="small">#0E9F8E</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded-1 mb-2" style="background: var(--clr-accent-subtle); color: var(--clr-accent);">
                    <div class="fw-bold">--clr-accent-subtle</div>
                    <div class="small">#E6F5F3 / #0B332F</div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. Typography & Numbers -->
    <section class="mb-5">
        <h2 class="h4 mb-3 border-bottom pb-2">2. Typography & Reference Ledger Numbers</h2>
        <div class="card-solvex p-4 mb-3">
            <h1 class="h2 mb-2">Heading 1: Incident Record Ledger (Editorial Serif)</h1>
            <h2 class="h3 text-secondary mb-3">Heading 2: Department Dispatch & Tracking</h2>
            <p>Body text rendered in Inter with high-readability line-height. Numbers use tabular figures for strict vertical alignment across ledger tables: <strong>1,245 open, 38 resolved, 0 overdue</strong>.</p>
            <div class="d-flex align-items-center gap-2">
                <span>Complaint Reference ID:</span>
                <span class="ref-id">SVX-2026-00042</span>
                <span class="ref-id">SVX-2026-00109</span>
            </div>
        </div>
    </section>

    <!-- 3. Status Tags & Priority Pills -->
    <section class="mb-5">
        <h2 class="h4 mb-3 border-bottom pb-2">3. Status Tags & Priority Badges</h2>
        <div class="card-solvex p-4 mb-3">
            <div class="mb-4">
                <h5 class="small text-uppercase text-muted fw-bold mb-2">Complaint Status Lifecycle</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?= status_tag('submitted') ?>
                    <?= status_tag('assigned') ?>
                    <?= status_tag('in_progress') ?>
                    <?= status_tag('on_hold') ?>
                    <?= status_tag('resolved') ?>
                    <?= status_tag('closed') ?>
                    <?= status_tag('reopened') ?>
                    <?= status_tag('rejected') ?>
                    <?= status_tag('duplicate') ?>
                </div>
            </div>

            <div class="mb-4">
                <h5 class="small text-uppercase text-muted fw-bold mb-2">Priority Levels</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?= priority_tag('critical') ?>
                    <?= priority_tag('high') ?>
                    <?= priority_tag('medium') ?>
                    <?= priority_tag('low') ?>
                </div>
            </div>

            <div>
                <h5 class="small text-uppercase text-muted fw-bold mb-2">Civic Ageing & SLA Indicators</h5>
                <div class="d-flex align-items-center gap-3">
                    <?= days_chip(1, 3) ?>
                    <?= days_chip(2, 3) ?>
                    <?= days_chip(5, 3) ?>
                    <div style="width: 140px;">
                        <?= sla_bar(2, 3) ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Stat Blocks -->
    <section class="mb-5">
        <h2 class="h4 mb-3 border-bottom pb-2">4. Civic Ledger Stat Cards</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <?= stat_block('Open Incidents', '142', 'inbox', 'primary') ?>
            </div>
            <div class="col-md-3">
                <?= stat_block('Resolved (30d)', '1,028', 'check-circle', 'success') ?>
            </div>
            <div class="col-md-3">
                <?= stat_block('SLA At Risk', '7', 'clock', 'warning') ?>
            </div>
            <div class="col-md-3">
                <?= stat_block('Overdue Incidents', '2', 'alert-triangle', 'danger') ?>
            </div>
        </div>
    </section>

    <!-- 5. Buttons & Form Controls -->
    <section class="mb-5">
        <h2 class="h4 mb-3 border-bottom pb-2">5. Buttons & Form Inputs</h2>
        <div class="card-solvex p-4 mb-3">
            <div class="d-flex flex-wrap gap-2 mb-4">
                <button class="btn-solvex btn-primary"><?= icon('plus', 16) ?> Primary Action</button>
                <button class="btn-solvex btn-secondary"><?= icon('filter', 16) ?> Secondary Filter</button>
                <button class="btn-solvex btn-ghost"><?= icon('settings', 16) ?> Ghost Utility</button>
                <button class="btn-solvex btn-accent"><?= icon('alert-triangle', 16) ?> Warning Notice</button>
                <button class="btn-solvex btn-danger"><?= icon('trash', 16) ?> Destructive</button>
                <button class="btn-solvex btn-primary btn-sm">Small Button</button>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-solvex">Campus Problem Title</label>
                    <input type="text" class="form-control-solvex" value="Ceiling water seepage in Room 302" placeholder="Brief summary of the issue">
                </div>
                <div class="col-md-6">
                    <label class="form-label-solvex">Triage Department</label>
                    <select class="form-select-solvex">
                        <option>Maintenance & Civil</option>
                        <option>Electrical Works</option>
                        <option>IT & Network Services</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Empty State -->
    <section class="mb-5">
        <h2 class="h4 mb-3 border-bottom pb-2">6. Empty States & Alerts</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card-solvex">
                    <?= empty_state('Queue All Clear', 'There are currently zero open complaints assigned to your department.', 'check-circle', '#', 'Refresh Queue') ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert-solvex success mb-2">
                    <?= icon('check-circle', 18) ?>
                    <div><strong>Resolution Accepted:</strong> Student accepted ticket resolution. Case marked closed.</div>
                </div>
                <div class="alert-solvex warning mb-2">
                    <?= icon('alert-triangle', 18) ?>
                    <div><strong>SLA Warning:</strong> This incident has been open 2 days past the standard department target.</div>
                </div>
                <div class="alert-solvex danger mb-2">
                    <?= icon('x-circle', 18) ?>
                    <div><strong>Action Refused:</strong> Only assigned staff or administrators may mutate this incident.</div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

