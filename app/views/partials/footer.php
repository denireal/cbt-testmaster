</div><!-- /.container-fluid -->

<footer class="py-4 border-top bg-white mt-4">
    <div class="container-fluid px-4">
        <div class="row align-items-center text-center text-md-start">
            
            <!-- Left: System Name & Copyright -->
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="fw-semibold" style="color: #002147;">
                    🎓 CBT System <span class="badge bg-primary ms-1 align-middle">TESTMASTER</span>
                </div>
                <div class="small text-muted mt-1" style="opacity: 0.7;">
                    &copy; <?= date('Y') ?> All Rights Reserved.
                </div>
            </div>

            <!-- Center: Powered By Attribution -->
            <div class="col-md-4 mb-3 mb-md-0 text-center">
                <div class="small text-muted">
                    Powered by <strong class="text-dark">Wari Denyefa</strong><br>
                    <span class="fw-semibold" style="color: #002147;">Department of Computer Science</span>
                </div>
            </div>

            <!-- Right: Contact Links -->
            <div class="col-md-4 text-center text-md-end">
                <div class="d-flex flex-column flex-md-row justify-content-center justify-content-md-end gap-2 gap-md-3" style="font-size: 0.85rem;">
                    <a href="mailto:denny.wari@gmail.com" class="text-decoration-none text-muted" 
                       style="transition: color 0.2s;" 
                       onmouseover="this.style.color='#002147'" 
                       onmouseout="this.style.color=''">
                        <i class="bi bi-envelope-fill me-1"></i> denny.wari@gmail.com
                    </a>
                    <a href="https://mrwari.com.ng" target="_blank" class="text-decoration-none text-muted" 
                       style="transition: color 0.2s;" 
                       onmouseover="this.style.color='#002147'" 
                       onmouseout="this.style.color=''">
                        <i class="bi bi-briefcase-fill me-1"></i> Developer Portfolio
                    </a>
                </div>
            </div>

        </div>
    </div>
</footer>

<!-- Ensure Bootstrap Icons are loaded -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<?php $localJquery = $localJquery ?? file_exists(ASSETS_PATH . 'js/jquery.min.js'); ?>
<?php $localBundle = $localBundle ?? file_exists(ASSETS_PATH . 'js/bootstrap.bundle.min.js'); ?>

<?php if ($localJquery): ?>
    <script src="<?= url('assets/js/jquery.min.js') ?>"></script>
<?php else: ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php endif; ?>

<?php if ($localBundle): ?>
    <script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
<?php else: ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<script>
    window.CBT_BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.CBT_CSRF     = <?= json_encode(csrf_token()) ?>;
</script>
<script src="<?= url('assets/js/cbt.js') ?>"></script>
</body>
</html>