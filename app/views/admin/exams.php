<?php 
$pageTitle = 'Exam Builder'; 
require APP_ROOT . '/app/views/partials/header.php'; 

// Calculate summary statistics for the header
$totalExams = count($exams);
$totalQuestions = array_sum(array_column($exams, 'question_count'));
$activeExams = count(array_filter($exams, fn($e) => $e['is_active']));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1" style="color: #002147;">Exams &amp; Marking Scheme</h1>
        <p class="text-muted small mb-0">Set duration, pass mark, positive/negative marks, and shuffling toggles.</p>
    </div>
    <button class="btn btn-primary btn-sm" style="background-color: #002147; border-color: #002147;" data-bs-toggle="modal" data-bs-target="#examModal" data-mode="create">
        <i class="bi bi-plus-circle me-1"></i> New Exam
    </button>
</div>

<!-- ✅ UPDATED: Clean White Background with Thin Borders -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border h-100" style="border-color: #e2e8f0 !important; background-color: #ffffff;">
            <div class="card-body d-flex align-items-center">
                <div class="display-6 me-3" style="color: #002147;">📝</div>
                <div>
                    <div class="h3 fw-bold mb-0" style="color: #002147;"><?= $totalExams ?></div>
                    <div class="small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Total Exams</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border h-100" style="border-color: #e2e8f0 !important; background-color: #ffffff;">
            <div class="card-body d-flex align-items-center">
                <div class="display-6 me-3" style="color: #004d00;">❓</div>
                <div>
                    <div class="h3 fw-bold mb-0" style="color: #004d00;"><?= $totalQuestions ?></div>
                    <div class="small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Total Questions</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border h-100" style="border-color: #e2e8f0 !important; background-color: #ffffff;">
            <div class="card-body d-flex align-items-center">
                <div class="display-6 me-3" style="color: #0d6efd;">✅</div>
                <div>
                    <div class="h3 fw-bold mb-0" style="color: #0d6efd;"><?= $activeExams ?></div>
                    <div class="small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Active Exams</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Exams Grid -->
<div class="row g-3">
    <?php foreach ($exams as $ex): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge" style="background-color: #002147;"><?= e($ex['group_name']) ?></span>
                        <span class="badge bg-<?= $ex['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $ex['is_active'] ? 'ACTIVE' : 'INACTIVE' ?>
                        </span>
                    </div>

                    <h5 class="fw-bold" style="color: #002147;"><?= e($ex['title']) ?></h5>
                    <p class="text-muted small"><?= e((string)$ex['description']) ?></p>

                    <table class="table table-sm small mb-3">
                        <tbody>
                            <tr><td class="text-muted">Duration</td><td class="text-end fw-semibold"><?= (int)$ex['duration_minutes'] ?> min</td></tr>
                            <tr><td class="text-muted">Pass mark</td><td class="text-end fw-semibold"><?= e((string)$ex['passing_percentage']) ?>%</td></tr>
                            <tr><td class="text-muted">Correct / Wrong</td>
                                <td class="text-end fw-semibold">
                                    <span class="text-success">+<?= e((string)$ex['positive_marks']) ?></span> /
                                    <span class="text-danger">-<?= e((string)$ex['negative_marks']) ?></span>
                                </td></tr>
                            <tr><td class="text-muted">Questions</td><td class="text-end fw-semibold"><?= (int)$ex['question_count'] ?></td></tr>
                            <tr><td class="text-muted">Shuffling</td>
                                <td class="text-end">
                                    <?= $ex['shuffle_questions'] ? 'Q' : '' ?><?= $ex['shuffle_options'] ? '+O' : '' ?>
                                    <?= (!$ex['shuffle_questions'] && !$ex['shuffle_options']) ? 'Off' : '' ?>
                                </td></tr>
                        </tbody>
                    </table>

                    <a class="btn btn-sm btn-primary w-100 mb-2" style="background-color: #002147; border-color: #002147;"
                       href="<?= url('index.php?page=admin_questions&exam_id=' . (int)$ex['id']) ?>">
                        <i class="bi bi-journal-text me-1"></i> Manage Questions (<?= (int)$ex['question_count'] ?>)
                    </a>

                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary flex-fill js-edit-exam"
                                data-exam='<?= json_encode($ex, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>

                        <form method="post" action="<?= url('index.php?page=admin_exams') ?>" class="js-confirm flex-fill"
                              data-message="Delete exam &quot;<?= e($ex['title']) ?>&quot; with all its questions?">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger w-100">
                                <i class="bi bi-trash me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($exams)): ?>
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center">
                <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                <div>No exams created yet. Click <strong>+ New Exam</strong> to begin.</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Create / Edit Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('index.php?page=admin_exams') ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" id="exam_action" value="create">
            <input type="hidden" name="id" id="exam_id">

            <div class="modal-header" style="background-color: #f8f9fa; border-bottom: 2px solid #002147;">
                <h5 class="modal-title fw-bold" style="color: #002147;" id="examModalLabel">Create Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Exam Title *</label>
                        <input type="text" name="title" id="exam_title" class="form-control form-control-sm" required
                               placeholder="e.g. CSC 101: Introduction to Computer Science">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Exam Group *</label>
                        <select name="group_id" id="exam_group" class="form-select form-select-sm" required>
                            <option value="">-- Select Group --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" id="exam_description" class="form-control form-control-sm" rows="2" placeholder="Optional instructions or notes for this exam"></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Duration (min)</label>
                        <input type="number" min="1" name="duration_minutes" id="exam_duration" class="form-control form-control-sm" value="15">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Pass %</label>
                        <input type="number" step="0.1" min="0" max="100" name="passing_percentage" id="exam_pass" class="form-control form-control-sm" value="50">
                    </div>
                    <!-- Questions to Answer Field -->
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-primary">Questions to Answer</label>
                        <input type="number" min="0" name="questions_to_answer" id="exam_qta" class="form-control form-control-sm" value="0" placeholder="0 = All">
                        <div class="form-text" style="font-size: 0.65rem;">Leave 0 to use all questions in the pool.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-success">Positive Marks (+)</label>
                        <input type="number" step="0.01" min="0" name="positive_marks" id="exam_pos" class="form-control form-control-sm" value="1.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-danger">Negative Marks (-)</label>
                        <input type="number" step="0.01" min="0" name="negative_marks" id="exam_neg" class="form-control form-control-sm" value="0.25">
                    </div>

                    <div class="col-12 border-top pt-3 mt-2">
                        <h6 class="small fw-bold text-muted mb-3 text-uppercase" style="letter-spacing: 1px;">Exam Settings</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="shuffle_questions" id="exam_sq" value="1" checked>
                                    <label class="form-check-label small" for="exam_sq">Enable Question Shuffling</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="shuffle_options" id="exam_so" value="1" checked>
                                    <label class="form-check-label small" for="exam_so">Enable Answer / Option Shuffling</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_immediate_score" id="exam_score" value="1" checked>
                                    <label class="form-check-label small" for="exam_score">Enable Immediate Score Display</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="exam_active" value="1" checked>
                                    <label class="form-check-label small fw-bold text-success" for="exam_active">Exam is Active (visible to students)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background-color: #002147; border-color: #002147;">Save Exam</button>
            </div>
        </form>
    </div>
</div>

<!-- REQUIRED JAVASCRIPT FOR MODAL & CONFIRMATIONS -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Handle Edit Exam Modal Population
    document.querySelectorAll('.js-edit-exam').forEach(btn => {
        btn.addEventListener('click', () => {
            const exam = JSON.parse(btn.dataset.exam);
            document.getElementById('exam_action').value = 'update';
            document.getElementById('exam_id').value = exam.id;
            document.getElementById('exam_title').value = exam.title;
            document.getElementById('exam_group').value = exam.group_id;
            document.getElementById('exam_description').value = exam.description || '';
            document.getElementById('exam_duration').value = exam.duration_minutes;
            document.getElementById('exam_pass').value = exam.passing_percentage;
            document.getElementById('exam_qta').value = exam.questions_to_answer || 0;
            document.getElementById('exam_pos').value = exam.positive_marks;
            document.getElementById('exam_neg').value = exam.negative_marks;
            document.getElementById('exam_sq').checked = !!exam.shuffle_questions;
            document.getElementById('exam_so').checked = !!exam.shuffle_options;
            document.getElementById('exam_score').checked = !!exam.show_immediate_score;
            document.getElementById('exam_active').checked = !!exam.is_active;
            
            document.getElementById('examModalLabel').textContent = 'Edit Exam';
            new bootstrap.Modal(document.getElementById('examModal')).show();
        });
    });

    // 2. Reset Modal on Close (so "Create" mode works perfectly after "Edit")
    document.getElementById('examModal').addEventListener('hidden.bs.modal', () => {
        document.getElementById('exam_action').value = 'create';
        document.getElementById('exam_id').value = '';
        document.querySelector('#examModal form').reset();
        document.getElementById('examModalLabel').textContent = 'Create Exam';
        
        // Reset to sensible defaults
        document.getElementById('exam_duration').value = 15;
        document.getElementById('exam_pass').value = 50;
        document.getElementById('exam_qta').value = 0;
        document.getElementById('exam_pos').value = 1.00;
        document.getElementById('exam_neg').value = 0.25;
        document.getElementById('exam_sq').checked = true;
        document.getElementById('exam_so').checked = true;
        document.getElementById('exam_score').checked = true;
        document.getElementById('exam_active').checked = true;
    });

    // 3. Delete Confirmation
    document.querySelectorAll('.js-confirm').forEach(form => {
        form.addEventListener('submit', (e) => {
            const message = form.dataset.message || 'Are you sure you want to delete this?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>