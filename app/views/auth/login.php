<?php $pageTitle = 'CBT Login'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow border-0 mt-5">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="display-6">🎓</div>
                    <h1 class="h5 fw-bold mb-1">CBT System</h1>
                    <span class="badge bg-primary ms-1 align-middle">TESTMASTER</span>
                    <p class="text-muted small mb-0">Sign in with your assigned File No. / Matric Number</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= url('index.php?page=login') ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">File No. / Matric Number</label>
                        <input type="text" name="reg_number" class="form-control" required autofocus
                               placeholder="FPE/xxx/xxx"
                               value="<?= e($_POST['reg_number'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold">Sign In</button>
                </form>

                <hr class="my-4">

                <p class="small text-muted fw-semibold mb-2">Demo credentials (password: <code>password</code>)</p>
                <table class="table table-sm small mb-0">
                    <tbody>
                        <tr><td><span class="badge bg-primary">Admin</span></td><td class="font-monospace">ADMIN001</td></tr>
                     
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

