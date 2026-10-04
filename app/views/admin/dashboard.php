<?php $pageTitle = 'Admin Dashboard'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Admin Dashboard</h1>
        <p class="text-muted small mb-0">System analytics, examinee groups and examination performance.</p>
    </div>
    <a href="<?= url('index.php?page=admin_export_csv') ?>" class="btn btn-success btn-sm">⬇ Export CSV</a>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Active Exam Groups',   $stats['active_groups'],        'primary'],
        ['Registered Examinees', $stats['registered_examinees'], 'success'],
        ['Scheduled Tests',      $stats['scheduled_tests'],      'warning'],
        ['Completed Attempts',   $stats['completed_attempts'],   'info'],
        ['Overall Pass Rate',    $stats['pass_rate'] . '%',      'danger'],
    ];
    foreach ($cards as [$label, $value, $color]): ?>
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold"><?= e($label) ?></div>
                    <div class="fs-3 fw-bold text-<?= e($color) ?>"><?= e((string)$value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Recent Examination Submissions</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Examinee</th><th>Reg No</th><th>Exam</th>
                            <th class="text-center">Score</th><th class="text-center">%</th>
                            <th class="text-center">Outcome</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No submissions recorded yet.</td></tr>
                        <?php else: foreach ($recent as $r): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($r['full_name']) ?></td>
                                <td class="font-monospace"><?= e($r['reg_number']) ?></td>
                                <td><?= e($r['exam_title']) ?></td>
                                <td class="text-center fw-bold"><?= e((string)$r['final_score']) ?></td>
                                <td class="text-center text-primary fw-bold"><?= e((string)$r['percentage']) ?>%</td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $r['passed'] ? 'success' : 'danger' ?>">
                                        <?= $r['passed'] ? 'PASSED' : 'FAILED' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= url('index.php?page=admin_results&attempt_id=' . (int)$r['attempt_id']) ?>">Log</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Exam Groups</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($groups as $g): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold small"><?= e($g['name']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e((string)$g['description']) ?></div>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?= (int)$g['member_count'] ?> students</span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($groups)): ?>
                    <li class="list-group-item text-muted small">No groups created yet.</li>
                <?php endif; ?>
            </ul>
            <div class="card-footer bg-white">
                <a href="<?= url('index.php?page=admin_groups') ?>" class="btn btn-sm btn-primary w-100">Manage Groups</a>
            </div>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
