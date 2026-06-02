<?php
// includes/footer.php
// APP_VERSION constants are already loaded via header.php → config/app.php
// For pages that include footer without header (edge cases), guard with a check.
if (!defined('APP_VERSION')) {
    require_once __DIR__ . '/../config/app.php';
}
?>
            </div> <!-- End Main Content Container -->

            <!-- ── Global Footer Bar ─────────────────────────────────────── -->
            <footer class="pos-global-footer" style="
                border-top: 1px solid rgba(255,255,255,0.07);
                background: #0c0d12;
                padding: 10px 35px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 6px;
                font-size: 0.75rem;
                color: #64748b;
            ">
                <span>
                    <?= APP_COPYRIGHT ?>
                </span>
                <span class="d-flex align-items-center gap-2">
                    <span style="color:#334155;">
                        <i class="fas fa-code-branch me-1" style="color:#0f62fe;font-size:.65rem;"></i><?= APP_SHORT_VERSION ?>
                    </span>
                    <span style="color:#334155;">|</span>
                    <span style="color:#<?= APP_ENVIRONMENT === 'Production' ? '22c55e' : 'f97316' ?>;">
                        <i class="fas fa-circle" style="font-size:.45rem;vertical-align:middle;"></i>
                        <?= APP_ENVIRONMENT ?>
                    </span>
                    <span style="color:#334155;">|</span>
                    <span>Build <?= APP_BUILD_DATE ?></span>
                </span>
            </footer>

        </div> <!-- End Page Content -->
    </div> <!-- End Wrapper -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
