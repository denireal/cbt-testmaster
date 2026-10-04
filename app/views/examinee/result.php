<?php
/**
 * app/views/examinee/result.php — Student scorecard (immediate score display)
 * Provided by ExamController::result(): $attempt, $log
 */
$pageTitle = 'Examination Result';
require APP_ROOT . '/app/views/partials/header.php';

// Generate a unique verification reference for the printed copy
$verifyRef = 'CBT-' . date('Y') . '-' . str_pad((string)$attempt['id'], 5, '0', STR_PAD_LEFT);

// Precompute values used more than once / that drive branching, so we don't
// re-run casts and lookups on every reference below.
$passed         = (bool)$attempt['passed'];
$showImmediate  = (int)$attempt['show_immediate_score'] === 1;
$showLog        = $showImmediate && !empty($log);

$statCards = [
    ['label' => 'Correct',     'value' => (int)$attempt['total_correct'],    'class' => 'text-success'],
    ['label' => 'Incorrect',   'value' => (int)$attempt['total_incorrect'],  'class' => 'text-danger'],
    ['label' => 'Unanswered',  'value' => (int)$attempt['total_unanswered'], 'class' => 'text-secondary'],
];

$submittedLine = $attempt['status'] === 'timed_out'
    ? 'Auto-submitted (time expired)'
    : 'Submitted ' . (string)$attempt['submitted_at'];
?>

<!-- PRINT-SPECIFIC CSS -->
<style>
    @media print {
        /* Hide navigation, buttons, and non-essential UI */
        .no-print, nav, footer, .btn, .alert-info, .alert-secondary, .modal {
            display: none !important;
        }
        /* Force clean white background and black text */
        body {
            background: white !important;
            color: black !important;
            font-family: 'Montserrat', sans-serif !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        /* Make cards look like official document sections */
        .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
            break-inside: avoid;
            margin-bottom: 20px !important;
        }
        .card-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            border-bottom: 1px solid #000 !important;
            font-weight: bold !important;
        }
        /* Ensure tables print with borders */
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        .table th, .table td {
            border: 1px solid #dee2e6 !important;
            padding: 8px !important;
        }
        /* Show the verification footer only on print */
        .print-verification-footer,
        .print-official-stamp {
            display: block !important;
        }
        .print-verification-footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 2px dashed #000;
            font-size: 10pt;
            text-align: center;
            color: #333;
        }
        .print-official-stamp {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border: 2px solid #002147;
            font-weight: bold;
            color: #002147;
        }
    }
    /* Hide print-only elements on screen */
    .print-verification-footer, .print-official-stamp {
        display: none;
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <!-- Action Buttons (Hidden when printing) -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="<?= url('index.php?page=student_dashboard') ?>" class="btn btn-sm btn-outline-secondary">← Back to My Dashboard</a>
            <button onclick="window.print()" class="btn btn-sm btn-primary">
                <i class="bi bi-printer-fill me-1"></i> Print Scorecard
            </button>
        </div>

        <!-- Official Stamp (Only visible when printing) -->
        <div class="print-official-stamp">
            🎓 EXAMINATION SCORECARD<br>
            <small>CBT System</small>
        </div>

        <div class="card border-0 shadow-sm text-center mb-4 <?= $passed ? 'border-success' : 'border-danger' ?>"
             style="border-width:2px !important">
            <div class="card-body p-4">
                <div class="display-4"><?= $passed ? '🎉' : '📘' ?></div>
                <h1 class="h4 fw-bold"><?= e($attempt['exam_title']) ?></h1>
                <p class="text-muted small mb-3">
                    <?= e($attempt['full_name']) ?> &middot; <span class="font-monospace"><?= e($attempt['reg_number']) ?></span>
                    &middot; <?= e($submittedLine) ?>
                </p>

                <div class="display-5 fw-bold"><?= e((string)$attempt['final_score']) ?>
                    <span class="fs-6 text-muted fw-normal">points</span>
                </div>
                <div class="fs-5 fw-bold <?= $passed ? 'text-success' : 'text-danger' ?>">
                    <?= e((string)$attempt['percentage']) ?>% — <?= $passed ? 'PASSED' : 'FAILED' ?>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    Pass mark <?= e((string)$attempt['passing_percentage']) ?>% &middot;
                    <span class="text-success">+<?= e((string)$attempt['positive_marks']) ?></span> per correct &middot;
                    <span class="text-danger">-<?= e((string)$attempt['negative_marks']) ?></span> per wrong
                </p>
            </div>
        </div>

        <div class="row g-3 mb-4 text-center">
            <?php foreach ($statCards as $stat): ?>
                <div class="col-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small"><?= e($stat['label']) ?></div>
                            <div class="fs-4 fw-bold <?= $stat['class'] ?>"><?= $stat['value'] ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($showLog): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Question-by-Question Review</div>
                <div class="card-body">
                    <?php foreach ($log as $i => $row): ?>
                        <?php
                        // Resolve the answer badge once instead of a
                        // three-way ternary chain inline in the markup.
                        if ($row['is_correct']) {
                            $badgeClass = 'bg-success';
                            $badgeText  = 'CORRECT';
                        } elseif ($row['selected_option_id']) {
                            $badgeClass = 'bg-danger';
                            $badgeText  = 'WRONG';
                        } else {
                            $badgeClass = 'bg-secondary';
                            $badgeText  = 'UNANSWERED';
                        }
                        $answerClass = $row['is_correct'] ? 'text-success' : 'text-danger';
                        ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <p class="fw-semibold mb-2">Q<?= $i + 1 ?>. <?= nl2br(e($row['question_text'])) ?></p>
                                <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                            </div>

                            <?php if (!empty($row['diagram_path'])): ?>
                                <img src="<?= url(ltrim($row['diagram_path'], '/')) ?>" alt="Diagram"
                                     class="border rounded mb-2" style="max-height:150px">
                            <?php endif; ?>

                            <div class="row g-2 small">
                                <div class="col-md-6">
                                    <div class="border rounded p-2 bg-light">
                                        <div class="text-muted" style="font-size:.7rem">YOUR ANSWER</div>
                                        <span class="<?= $answerClass ?> fw-semibold">
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
        <?php elseif (!$showImmediate): ?>
            <div class="alert alert-secondary small no-print">
                Immediate score review is disabled for this examination. Your result has been recorded and will be
                released by the administrator.
            </div>
        <?php endif; ?>

        <!-- PRINT-ONLY VERIFICATION FOOTER -->
        <div class="print-verification-footer">
            <p class="mb-1"><strong>Document Verification:</strong> This is the computer-generated scorecard.</p>
            <p class="mb-0">To verify the authenticity of this result, an administrator can cross-reference <strong>Attempt ID: <?= (int)$attempt['id'] ?></strong> or Reference Code: <strong><?= e($verifyRef) ?></strong> in the CBT System database.</p>
            <p class="mt-2 text-muted" style="font-size: 8pt;">Generated on <?= date('F d, Y \a\t h:i A') ?> | 🎓 CBT System | Powered by Wari Denyefa</p>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>