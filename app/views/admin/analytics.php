<?php $pageTitle = 'Item Analysis'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0" style="color: #002147;">Item Analysis Report</h1>
</div>

<!-- Exam Selector -->
<form method="GET" class="card border-0 shadow-sm p-3 mb-4">
    <input type="hidden" name="page" value="admin_analytics">
    <div class="row g-2 align-items-end">
        <div class="col-md-8">
            <label class="form-label small fw-semibold">Select Examination</label>
            <select name="exam_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Choose an Exam to Analyze --</option>
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= $ex['id'] ?>" <?= $examId == $ex['id'] ? 'selected' : '' ?>><?= e($ex['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($exam && !empty($analysis['questions'])): ?>
    <div class="alert alert-info d-flex justify-content-between">
        <span><strong>Total Attempts:</strong> <?= $analysis['total_attempts'] ?></span>
        <span><strong>Top 27% Group:</strong> <?= $analysis['top_count'] ?> students</span>
        <span><strong>Bottom 27% Group:</strong> <?= $analysis['bottom_count'] ?> students</span>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 40%">Question Text</th>
                        <th class="text-center" style="width: 15%">Difficulty Index</th>
                        <th class="text-center" style="width: 15%">Discrimination</th>
                        <th class="text-center" style="width: 25%">Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analysis['questions'] as $i => $q): 
                        $diff = $q['difficulty'];
                        $disc = $q['discrimination'];
                        
                        // Difficulty Classification
                        if ($diff > 0.8) $diffClass = 'bg-success-subtle text-success';
                        elseif ($diff >= 0.3) $diffClass = 'bg-warning-subtle text-warning';
                        else $diffClass = 'bg-danger-subtle text-danger';
                        
                        // Discrimination Classification
                        if ($disc >= 0.3) $discClass = 'text-success fw-bold';
                        elseif ($disc >= 0.1) $discClass = 'text-warning fw-bold';
                        else $discClass = 'text-danger fw-bold';
                        
                        // Recommendation
                        if ($diff < 0.3 && $disc < 0.1) $rec = 'Review/Replace (Too hard & poor discriminator)';
                        elseif ($diff > 0.8) $rec = 'Review (Too easy)';
                        elseif ($disc < 0.1) $rec = 'Review (Poor discriminator)';
                        else $rec = 'Good Question';
                    ?>
                        <tr>
                            <td class="fw-bold"><?= $i + 1 ?></td>
                            <td class="small"><?= e(substr($q['question_text'], 0, 80)) ?>...</td>
                            <td class="text-center"><span class="badge <?= $diffClass ?> px-2 py-1"><?= number_format($diff, 2) ?></span></td>
                            <td class="text-center <?= $discClass ?>"><?= number_format($disc, 2) ?></td>
                            <td class="text-center small fst-italic text-muted"><?= $rec ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($exam): ?>
    <div class="alert alert-warning"><?= $analysis['message'] ?? 'No data available for analysis.' ?></div>
<?php endif; ?>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>