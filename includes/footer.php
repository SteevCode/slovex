    </main>

    <!-- App Footer -->
    <footer class="footer-solvex">
        <div class="container">
            <!-- Emergency Notice Banner -->
            <div class="footer-emergency d-flex align-items-center gap-3">
                <i class="bi bi-exclamation-octagon-fill text-danger fs-2 flex-shrink-0"></i>
                <div>
                    <div class="emergency-title">Emergency Notice & Urgent Life Safety</div>
                    <p>
                        For life-threatening incidents, active fires, electrical shocks, or immediate physical harassment, call 
                        <strong>Campus Security Control (<?= e(EMERGENCY_CONTACT_PHONE) ?>)</strong> or <strong>Emergency (112)</strong> immediately. 
                        Do not wait for ticket triage for life safety emergencies.
                    </p>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <!-- Brand Summary -->
                <div class="col-lg-4 col-md-6">
                    <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="34" class="mb-3">
                    <p class="text-secondary small mb-3">
                        Campus problem reporting, department triage, and transparent resolution tracking for hostel and academic facilities.
                    </p>
                    <div class="d-flex align-items-center gap-2 text-secondary small">
                        <span class="ref-id">SVX-YYYY-NNNNN</span>
                        <span>Civic Incident Tracking</span>
                    </div>
                </div>

                <!-- Fast Navigation -->
                <div class="col-lg-2 col-md-3 col-6">
                    <h5>Navigation</h5>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="<?= base_url() ?>">Home Ledger</a></li>
                        <li><a href="<?= base_url('feed.php') ?>">Public Feed</a></li>
                        <li><a href="<?= base_url('accountability.php') ?>">Accountability</a></li>
                        <li><a href="<?= base_url('login.php') ?>">Sign In</a></li>
                        <li><a href="<?= base_url('register.php') ?>">Student Registration</a></li>
                    </ul>
                </div>

                <!-- Departments Handled -->
                <div class="col-lg-3 col-md-3 col-6">
                    <h5>Departments</h5>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><span class="text-secondary"><i class="bi bi-wrench me-1"></i> Maintenance & Civil</span></li>
                        <li><span class="text-secondary"><i class="bi bi-lightning-charge me-1"></i> Electrical Works</span></li>
                        <li><span class="text-secondary"><i class="bi bi-wifi me-1"></i> IT & Network Services</span></li>
                        <li><span class="text-secondary"><i class="bi bi-cup-hot me-1"></i> Mess Administration</span></li>
                        <li><span class="text-secondary"><i class="bi bi-shield-check me-1"></i> Hostel Warden & Security</span></li>
                    </ul>
                </div>

                <!-- Student & Campus Help -->
                <div class="col-lg-3 col-md-12">
                    <h5>Campus Helpdesk</h5>
                    <p class="text-secondary small mb-2">
                        Need assistance setting up your student profile or finding your register number?
                    </p>
                    <div class="small text-secondary mb-3">
                        <div><i class="bi bi-envelope me-1 text-primary"></i> helpdesk@solvexreport.edu</div>
                        <div><i class="bi bi-geo-alt me-1 text-primary"></i> Student Affairs, Admin Block, Floor 1</div>
                    </div>
                    <div class="small text-muted">
                        Operating Hours: 24×7 Automated Intake
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <div>
                    &copy; <?= date('Y') ?> <strong>Solvex Report</strong>. Built for student welfare and institutional transparency.
                </div>
                <div class="text-secondary">
                    PHP 8+ &bull; MySQL &bull; WCAG AA Compliant
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Solvex Core JS -->
    <script src="<?= asset_url('js/solvex.js') ?>"></script>
</body>
</html>
