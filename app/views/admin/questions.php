<?php $pageTitle = 'Question Builder'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary"><?= e($exam['group_name']) ?></span>
        <h1 class="h4 fw-bold mb-1 mt-2"><?= e($exam['title']) ?></h1>
        <p class="text-muted small mb-0">
            <?= count($questions) ?> question(s) &middot;
            +<?= e((string)$exam['positive_marks']) ?> correct /
            -<?= e((string)$exam['negative_marks']) ?> wrong &middot;
            <?= (int)$exam['duration_minutes'] ?> minutes
        </p>
    </div>

    <div>
        <a href="<?= url('index.php?page=admin_exams') ?>" class="btn btn-outline-secondary btn-sm">← Back to Exams</a>
        <button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">⬆ Bulk Upload CSV</button>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#qModal" data-mode="create">+ Add Question</button>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($questions as $i => $q): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <span class="badge bg-dark mb-2">Q<?= $i + 1 ?></span>
                            <p class="fw-semibold mb-2"><?= nl2br(e($q['question_text'])) ?></p>

                            <?php if (!empty($q['diagram_path'])): ?>
                                <a href="<?= url(ltrim($q['diagram_path'], '/')) ?>" target="_blank" class="d-inline-block mb-2">
                                    <img src="<?= url(ltrim($q['diagram_path'], '/')) ?>" alt="Diagram"
                                         class="border rounded" style="max-height:140px">
                                </a>
                                <div class="text-muted small font-monospace mb-2"><?= e($q['diagram_path']) ?></div>
                            <?php endif; ?>

                            <div class="row g-2">
                                <?php foreach ($q['options'] as $o): ?>
                                    <div class="col-md-6">
                                        <!-- Use !empty() to safely check if is_correct exists and is true -->
                                        <div class="border rounded p-2 small <?= !empty($o['is_correct']) ? 'border-success bg-success-subtle fw-semibold' : 'bg-light' ?>">
                                            <strong><?= e($o['option_letter']) ?>.</strong> <?= e($o['option_text']) ?>
                                            <?php if (!empty($o['is_correct'])): ?><span class="badge bg-success float-end">Correct</span><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($q['explanation'])): ?>
                                <p class="text-muted small fst-italic mt-2 mb-0">💡 <?= e($q['explanation']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary js-edit-question"
                                    data-question='<?= json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button>
                            <form method="post" action="<?= url('index.php?page=admin_questions&exam_id=' . (int)$exam['id']) ?>"
                                  class="d-inline js-confirm" data-message="Delete this question?">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($questions)): ?>
        <div class="col-12"><div class="alert alert-warning">No questions yet. Add at least one MCQ so examinees can sit this exam.</div></div>
    <?php endif; ?>
</div>

<!-- Create / Edit Question Modal (with diagram upload) -->
<div class="modal fade" id="qModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="post" enctype="multipart/form-data"
              action="<?= url('index.php?page=admin_questions&exam_id=' . (int)$exam['id']) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" id="q_action" value="create">
            <input type="hidden" name="id" id="q_id">
            <input type="hidden" name="existing_diagram" id="q_existing_diagram">

            <div class="modal-header">
                <h5 class="modal-title" id="qModalLabel">Add MCQ Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Question Text *</label>
                    <textarea name="question_text" id="q_text" class="form-control form-control-sm" rows="3" required></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Diagram / Image (PNG, JPG, WEBP, GIF, SVG)</label>
                        <input type="file" name="diagram" id="q_diagram" class="form-control form-control-sm" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
                        <div class="form-text">Stored in <code>public/uploads/diagrams/</code> with a secure hashed filename.</div>
                        <div id="q_diagram_current" class="mt-2 d-none">
                            <img id="q_diagram_preview" src="" alt="Current diagram" class="border rounded" style="max-height:110px">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Explanation (optional)</label>
                        <textarea name="explanation" id="q_explanation" class="form-control form-control-sm" rows="4"></textarea>
                    </div>
                </div>

                <label class="form-label small fw-semibold">Options (select the correct answer) *</label>
                <div id="q_options">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group input-group-sm mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 js-correct" type="radio" name="correct_index" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            </div>
                            <span class="input-group-text fw-bold"><?= chr(65 + $i) ?></span>
                            <input type="text" name="option_text[]" class="form-control js-option" placeholder="Option <?= chr(65 + $i) ?>">
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="q_add_option">+ Add 5th Option</button>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-success">Save Question</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" enctype="multipart/form-data" action="<?= url('index.php?page=admin_questions&exam_id=' . (int)$exam['id']) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="bulk_upload">
            <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">

            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload Questions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info small">
                    <strong>Instructions:</strong><br>
                    1. Download the <a href="<?= url('sample_questions.csv') ?>" download class="alert-link">Sample CSV Template</a>.<br>
                    2. Fill in your questions (do not change the header row).<br>
                    3. Save the file as <strong>UTF-8 CSV</strong>.<br>
                    4. <em>Note: Bulk upload does not support image uploads. Add diagrams individually if needed.</em>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select CSV File *</label>
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-info text-white">Upload Questions</button>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
