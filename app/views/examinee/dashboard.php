<?php $pageTitle = 'My Examinations'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }
    .dashboard-hero::before {
        content: '';
        position: absolute;
        top: 0; right: 0;
        width: 200px; height: 200px;
        background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(50%, -50%);
    }
    .dashboard-hero h1 {
        font-family: 'Georgia', serif;
        font-size: 1.75rem;
        margin-bottom: 0.25rem;
    }
    .dashboard-hero .reg-number {
        font-family: 'Courier New', monospace;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        border: 1px solid rgba(212, 175, 55, 0.4); /* Slightly brighter gold border for black bg */
        color: #d4af37;
        font-weight: 600;
        display: inline-block;
        margin-top: 0.5rem;
    }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        text-align: center;
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #002147;
        line-height: 1;
    }
    .stat-card .stat-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6c757d;
        margin-top: 0.5rem;
    }
    .exam-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        transition: all 0.3s;
        overflow: hidden;
        height: 100%;
    }
    .exam-card:hover {
        box-shadow: 0 10px 25px rgba(0, 33, 71, 0.1);
        transform: translateY(-3px);
        border-color: #002147;
    }
    .exam-card-header {
        padding: 1rem 1.25rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .exam-card-body {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    .exam-title {
        font-family: 'Georgia', serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: #002147;
        margin-bottom: 0.5rem;
    }
    .exam-meta-table { font-size: 0.85rem; margin: 1rem 0; }
    .exam-meta-table td { padding: 0.35rem 0; border: none; }
    .exam-meta-table td:first-child { color: #6c757d; width: 45%; }
    .exam-meta-table td:last-child { font-weight: 600; text-align: right; }
    
    .status-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-completed { background: #d4edda; color: #155724; }
    .status-timed-out { background: #fff3cd; color: #856404; }
    .status-in-progress { background: #cce5ff; color: #004085; }
    .status-not-started { background: #e2e3e5; color: #383d41; }
    
    .group-badge {
        background: #002147;
        color: white;
        padding: 0.25rem 0.6rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .btn-start-exam {
        background: linear-gradient(135deg, #002147 0%, #003366 100%);
        color: white;
        border: none;
        font-weight: 600;
        padding: 0.6rem 1.25rem;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(0, 33, 71, 0.2);
    }
    .btn-start-exam:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 33, 71, 0.3);
        color: white;
    }
    .btn-view-scorecard {
        background: white;
        color: #002147;
        border: 2px solid #002147;
        font-weight: 600;
        padding: 0.5rem 1.25rem;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
        transition: all 0.3s;
    }
    .btn-view-scorecard:hover { background: #002147; color: white; }
    
    .score-display {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        padding: 1rem;
        margin: 1rem 0;
        text-align: center;
        border: 1px solid #dee2e6;
    }
    .score-display.passed { border-left: 4px solid #28a745; }
    .score-display.failed { border-left: 4px solid #dc3545; }
    .score-value { font-size: 1.75rem; font-weight: 700; line-height: 1; }
    .score-value.passed { color: #28a745; }
    .score-value.failed { color: #dc3545; }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 12px;
        border: 2px dashed #dee2e6;
    }
    .empty-state-icon { font-size: 4rem; opacity: 0.3; margin-bottom: 1rem; }
</style>

<!-- HERO SECTION -->
<div class="dashboard-hero">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1>Welcome, <?= e($me['full_name']) ?> 👋</h1>
            <p class="mb-2 opacity-75">Your examination portal is ready. Here's your dashboard.</p>
            <div class="reg-number">
                <i class="bi bi-person-badge me-1"></i> <?= e($me['reg_number']) ?>
            </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div style="font-size: 0.8rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">
                <i class="bi bi-check-circle-fill me-1"></i> CBT Engine Running
            </div>
        </div>
    </div>
</div>

<!-- ✅ FIXED STATS ROW -->
<?php 
    $totalExams = count($exams);
    // A finished exam is either 'completed' or 'timed_out'
    $isFinished = fn($e) => in_array($e['last_attempt_status'], ['completed', 'timed_out']);
    $completedExams = count(array_filter($exams, $isFinished));
    $pendingExams = $totalExams - $completedExams;
    $passedExams = count(array_filter($exams, fn($e) => $isFinished($e) && $e['last_passed']));
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= $totalExams ?></div>
            <div class="stat-label">Total Exams</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color: #28a745;"><?= $completedExams ?></div>
            <div class="stat-label">Finished</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color: #ffc107;"><?= $pendingExams ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color: #002147;"><?= $passedExams ?></div>
            <div class="stat-label">Passed</div>
        </div>
    </div>
</div>

<!-- EXAMS LIST -->
<?php if (empty($exams)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📚</div>
        <h4 class="fw-bold" style="color: #002147;">No Examinations Assigned</h4>
        <p class="text-muted mb-0">Your exam group doesn't have any active examinations yet. Please check back later or contact your administrator.</p>
    </div>
<?php else: ?>
    <h3 class="h5 fw-bold mb-3" style="color: #002147;">
        <i class="bi bi-journal-bookmark-fill me-2"></i>Assigned Examinations
    </h3>
    <div class="row g-3">
        <?php foreach ($exams as $ex): 
            // ✅ Define both flags for clean logic
            $done = in_array($ex['last_attempt_status'], ['completed', 'timed_out']);
            $inProgress = ($ex['last_attempt_status'] === 'in_progress');
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="exam-card">
                    <div class="exam-card-header">
                        <span class="group-badge"><?= e($ex['group_name']) ?></span>
                        <?php if ($ex['last_attempt_status'] === 'completed'): ?>
                            <span class="status-badge status-completed">✓ Completed</span>
                        <?php elseif ($ex['last_attempt_status'] === 'timed_out'): ?>
                            <span class="status-badge status-timed-out">⏱ Time Expired</span>
                        <?php elseif ($inProgress): ?>
                            <span class="status-badge status-in-progress">In Progress</span>
                        <?php else: ?>
                            <span class="status-badge status-not-started">Not Started</span>
                        <?php endif; ?>
                    </div>
                    <div class="exam-card-body">
                        <h5 class="exam-title"><?= e($ex['title']) ?></h5>
                        <p class="text-muted small mb-0 flex-grow-1"><?= e((string)$ex['description']) ?></p>

                                                <table class="exam-meta-table w-100">
                            <tbody>
                                <tr><td><i class="bi bi-clock me-1"></i>Duration</td><td><?= (int)$ex['duration_minutes'] ?> min</td></tr>
                                
                                <!-- ✅ UPDATED: Shows "X (of Y)" if a limit is set -->
                                <tr>
                                    <td><i class="bi bi-question-circle me-1"></i>Questions</td>
                                    <td>
                                        <?php 
                                            $qCount = (int)$ex['question_count'];
                                            $qLimit = (int)($ex['questions_to_answer'] ?? 0);
                                            
                                            if ($qLimit > 0 && $qLimit < $qCount) {
                                                echo '<span class="text-primary fw-bold">' . $qLimit . '</span> <span class="text-muted" style="font-size:0.75rem;">(of ' . $qCount . ')</span>';
                                            } else {
                                                echo $qCount;
                                            }
                                        ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td><i class="bi bi-calculator me-1"></i>Marks</td>
                                    <td>
                                        <span class="text-success">+<?= e((string)$ex['positive_marks']) ?></span> /
                                        <span class="text-danger">-<?= e((string)$ex['negative_marks']) ?></span>
                                    </td>
                                </tr>
                                <tr><td><i class="bi bi-trophy me-1"></i>Pass mark</td><td><?= e((string)$ex['passing_percentage']) ?>%</td></tr>
                            </tbody>
                        </table>

                        <?php if ($done): ?>
                            <div class="score-display <?= $ex['last_passed'] ? 'passed' : 'failed' ?>">
                                <div class="small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.7rem;">Your Score</div>
                                <div class="score-value <?= $ex['last_passed'] ? 'passed' : 'failed' ?>">
                                    <?= e((string)$ex['last_score']) ?>
                                    <span style="font-size: 0.9rem; opacity: 0.7;">/ <?= e((string)$ex['last_percentage']) ?>%</span>
                                </div>
                                <div class="small fw-bold mt-1" style="color: <?= $ex['last_passed'] ? '#28a745' : '#dc3545' ?>">
                                    <?= $ex['last_passed'] ? '🎉 PASSED' : '📘 FAILED' ?>
                                </div>
                            </div>
                            <a class="btn btn-view-scorecard w-100"
                               href="<?= url('index.php?page=result&attempt_id=' . (int)$ex['last_attempt_id']) ?>">
                                <i class="bi bi-file-earmark-text me-1"></i>View Scorecard
                            </a>
                        <?php elseif ((int)$ex['question_count'] === 0): ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="bi bi-exclamation-circle me-1"></i>No questions published yet
                            </button>
                        <?php else: ?>
                            <a class="btn btn-start-exam w-100"
                               href="<?= url('index.php?page=exam&exam_id=' . (int)$ex['id']) ?>">
                                <i class="bi bi-<?= $inProgress ? 'play-circle' : 'play-fill' ?> me-1"></i>
                                <?= $inProgress ? 'Resume Examination' : 'Start Examination' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>