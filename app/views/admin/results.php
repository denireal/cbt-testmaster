<?php $pageTitle = 'Results & Reports'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Results &amp; Reports Engine</h1>
        <p class="text-muted small mb-0">Review scorecards, inspect question-by-question logs, export to CSV/Excel.</p>
    </div>
    <a href="<?= url('index.php?page=admin_export_csv') ?>" class="btn btn-success btn-sm">⬇ Export Results CSV</a>
</div>

<?php if ($attempt): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                Scorecard: <?= e($attempt['full_name']) ?> (<?= e($attempt['reg_number']) ?>) — <?= e($attempt['exam_title']) ?>
            </span>
            <a href="<?= url('index.php?page=admin_results') ?>" class="btn btn-sm btn-outline-secondary">Close Log</a>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center mb-4">
                <div class="col"><div class="border rounded p-3"><div class="text-muted small">Final Score</div>
                    <div class="fs-4 fw-bold"><?= e((string)$attempt['final_score']) ?></div></div></div>
                <div class="col"><div class="border rounded p-3"><div class="text-muted small">Percentage</div>
                    <div class="fs-4 fw-bold text-primary"><?= e((string)$attempt['percentage']) ?>%</div></div></div>
                <div class="col"><div class="border rounded p-3"><div class="text-muted small">Correct</div>
                    <div class="fs-4 fw-bold text-success"><?= (int)$attempt['total_correct'] ?></div></div></div>
                <div class="col"><div class="border rounded p-3"><div class="text-muted small">Incorrect</div>
                    <div class="fs-4 fw-bold text-danger"><?= (int)$attempt['total_incorrect'] ?></div></div></div>
                <div class="col"><div class="border rounded p-3"><div class="text-muted small">Unanswered</div>
                    <div class="fs-4 fw-bold text-secondary"><?= (int)$attempt['total_unanswered'] ?></div></div></div>
                <div class="col"><div class="border rounded p-3"><div class="text-muted small">Outcome</div>
                    <div class="fs-5 fw-bold <?= $attempt['passed'] ? 'text-success' : 'text-danger' ?>">
                        <?= $attempt['passed'] ? 'PASSED' : 'FAILED' ?></div></div></div>
            </div>

            <p class="text-muted small">
                Marking: <span class="text-success">+<?= e((string)$attempt['positive_marks']) ?></span> correct,
                <span class="text-danger">-<?= e((string)$attempt['negative_marks']) ?></span> wrong,
                pass mark <?= e((string)$attempt['passing_percentage']) ?>% &middot;
                Submitted <?= e((string)$attempt['submitted_at']) ?> (<?= e($attempt['status']) ?>)
            </p>

            <?php foreach ($log as $i => $row): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <p class="fw-semibold mb-2">Q<?= $i + 1 ?>. <?= nl2br(e($row['question_text'])) ?></p>
                        <?php if ($row['is_correct']): ?>
                            <span class="badge bg-success">CORRECT +<?= e((string)$attempt['positive_marks']) ?></span>
                        <?php elseif ($row['selected_option_id']): ?>
                            <span class="badge bg-danger">WRONG -<?= e((string)$attempt['negative_marks']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">UNANSWERED 0</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($row['diagram_path'])): ?>
                        <img src="<?= url(ltrim($row['diagram_path'], '/')) ?>" alt="Diagram" class="border rounded mb-2" style="max-height:120px">
                    <?php endif; ?>

                    <div class="row g-2 small">
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted" style="font-size:.7rem">EXAMINEE CHOICE</div>
                                <span class="<?= $row['is_correct'] ? 'text-success' : 'text-danger' ?> fw-semibold">
                                    <?= e($row['selected_text']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted" style="font-size:.7rem">CORRECT ANSWER</div>
                                <span class="text-success fw-semibold"><?= e($row['correct_text']) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($row['explanation'])): ?>
                        <p class="text-muted small fst-italic mt-2 mb-0">💡 <?= e($row['explanation']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">All Scorecards (<?= count($results) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Examinee</th><th>Reg No</th><th>Group</th><th>Exam</th>
                    <th class="text-center">C / W / U</th><th class="text-center">Score</th>
                    <th class="text-center">%</th><th class="text-center">Outcome</th>
                    <th>Submitted</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['full_name']) ?></td>
                    <td class="font-monospace"><?= e($r['reg_number']) ?></td>
                    <td><?= e($r['group_name']) ?></td>
                    <td><?= e($r['exam_title']) ?></td>
                    <td class="text-center">
                        <span class="text-success"><?= (int)$r['total_correct'] ?></span> /
                        <span class="text-danger"><?= (int)$r['total_incorrect'] ?></span> /
                        <span class="text-secondary"><?= (int)$r['total_unanswered'] ?></span>
                    </td>
                    <td class="text-center fw-bold"><?= e((string)$r['final_score']) ?></td>
                    <td class="text-center text-primary fw-bold"><?= e((string)$r['percentage']) ?>%</td>
                    <td class="text-center">
                        <span class="badge bg-<?= $r['passed'] ? 'success' : 'danger' ?>">
                            <?= $r['passed'] ? 'PASS' : 'FAIL' ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= e((string)$r['submitted_at']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= url('index.php?page=admin_results&attempt_id=' . (int)$r['attempt_id']) ?>">Log</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($results)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No completed attempts yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
