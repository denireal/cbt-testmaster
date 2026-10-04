<?php
/**
 * app/views/examinee/exam_screen.php
 * Enterprise CBT Interface: Anti-cheat, Offline Resilience, Keyboard Nav, Compact UI
 */
$pageTitle = 'Examination in Progress';

$publicQuestions = array_map(function ($q) {
    return [
        'id'            => (int)$q['id'],
        'question_text' => $q['question_text'],
        'diagram_path'  => $q['diagram_path'] ? url(ltrim($q['diagram_path'], '/')) : null,
        'options'       => array_map(function ($o) {
            return [
                'id'           => (int)$o['id'],
                'option_text'  => $o['option_text'],
                'option_letter'=> $o['option_letter'],
            ];
        }, $q['options']),
    ];
}, $questions);

$publicSaved = [];
foreach ($saved as $qid => $row) {
    $publicSaved[(int)$qid] = ['option_id' => $row['option_id'], 'is_flagged' => (bool)$row['is_flagged']];
}

require APP_ROOT . '/app/views/partials/header.php';
?>

<style>
    /* --- ANTI-CHEAT: DISABLE TEXT SELECTION --- */
    body { user-select: none !important; -webkit-user-select: none !important; }
    input, textarea { user-select: text !important; -webkit-user-select: text !important; }

    /* --- COMPACT UI STYLES (Same as before) --- */
    .question-block .card-body { padding: 1rem 1.25rem; }
    .q-text { font-size: 0.95rem; line-height: 1.5; }
    .option-label { padding: 0.5rem 0.75rem !important; margin-bottom: 0.5rem !important; border: 1px solid #dee2e6; border-radius: 6px; transition: all 0.15s ease; }
    .option-label:hover { background-color: #f8f9fa; border-color: #002147; }
    .option-letter { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #e9ecef; color: #002147; font-size: 0.8rem; font-weight: 700; margin-right: 10px; flex-shrink: 0; }
    .option-label input[type="radio"]:checked ~ .option-content .option-letter { background: #002147; color: white; }
    .option-text { font-size: 0.9rem; line-height: 1.4; }
    .question-block { transition: box-shadow 0.2s; border-left: 3px solid #002147; }
    .palette-btn { display: flex; align-items: center; justify-content: center; font-weight: 600; border-radius: 6px; width: 36px; height: 36px; }
    .palette-btn.answered { background-color: #198754; color: white; border-color: #198754; }
    .palette-btn.flagged { background-color: #a855f7; color: white; border-color: #a855f7; }

    /* --- TIMER STYLES --- */
    @media (min-width: 992px) {
        .sticky-sidebar { position: sticky; top: 20px; z-index: 100; }
        .bright-timer .time-display { font-size: 2.5rem; }
    }
    @media (max-width: 991.98px) {
        .mobile-sticky-timer { position: sticky; top: 0; z-index: 1020; background: linear-gradient(135deg, #002147 0%, #003366 100%); color: white; padding: 0.5rem 1rem; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0, 33, 71, 0.3); display: flex; justify-content: space-between; align-items: center; }
        .mobile-sticky-timer .time-display { font-size: 1.25rem; font-weight: 800; font-family: 'Courier New', monospace; }
    }
    .bright-timer { background: linear-gradient(135deg, #002147 0%, #003366 100%); color: #fff; border-radius: 10px; padding: 1rem; text-align: center; margin-bottom: 1rem; }
    .bright-timer .time-display { font-weight: 800; font-family: 'Courier New', monospace; letter-spacing: 2px; }
    .bright-timer.urgent, .mobile-sticky-timer.urgent { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%) !important; animation: pulse-red 1.5s infinite; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 12px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<!-- MOBILE TIMER -->
<div class="d-lg-none mobile-sticky-timer" id="mobileTimerBox">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-clock-fill"></i>
        <span id="autoSaveBadgeMobile" class="badge bg-success-subtle text-success border border-success" style="font-size: 0.65rem;"><i class="bi bi-check-circle-fill"></i></span>
    </div>
    <div class="time-display" id="mobileTimer">00:00</div>
</div>

<div class="container-fluid py-2 py-lg-3" style="max-width: 1200px;">
    <!-- DESKTOP INFO BAR -->
    <div class="d-none d-lg-flex justify-content-between align-items-center mb-3 bg-white p-2 rounded shadow-sm border">
        <div>
            <h1 class="h6 fw-bold mb-0" style="color: #002147;"><?= e($exam['title']) ?></h1>
            <small class="text-muted"><?= e($me['full_name']) ?> &middot; <span class="font-monospace fw-semibold"><?= e($me['reg_number']) ?></span></small>
        </div>
        <div>
            <span id="autoSaveBadge" class="badge bg-success-subtle text-success border border-success px-2 py-1" style="font-size: 0.75rem;">
                <i class="bi bi-check-circle-fill me-1"></i> Auto-saving
            </span>
        </div>
    </div>

    <div class="row g-2 g-lg-3">
        <!-- QUESTIONS -->
        <div class="col-12 col-lg-8">
            <div class="d-lg-none bg-white p-2 rounded shadow-sm border mb-2">
                <h1 class="h6 fw-bold mb-0" style="color: #002147; font-size: 0.95rem;"><?= e($exam['title']) ?></h1>
            </div>

            <div id="questions-container">
                <?php foreach ($publicQuestions as $index => $q): ?>
                    <div class="card border-0 shadow-sm mb-2 mb-lg-3 question-block" id="question-<?= $q['id'] ?>" data-index="<?= $index ?>" data-qid="<?= $q['id'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary px-2 py-1" style="font-size: 0.8rem;">Q<?= $index + 1 ?></span>
                                <button type="button" class="btn btn-sm btn-outline-warning flag-btn py-0 px-2" style="font-size: 0.75rem;" data-qid="<?= $q['id'] ?>">🚩 <span class="flag-text d-none d-sm-inline">Flag</span></button>
                            </div>
                            <h2 class="q-text mb-2"><?= nl2br(e($q['question_text'])) ?></h2>
                            <?php if ($q['diagram_path']): ?>
                                <div class="text-center my-2">
                                    <img src="<?= e($q['diagram_path']) ?>" alt="Diagram" class="cbt-diagram img-fluid rounded border" style="max-height: 250px; cursor: zoom-in;" data-bs-toggle="modal" data-bs-target="#lightboxModal">
                                </div>
                            <?php endif; ?>
                            <div class="options-container mt-2">
                                <?php foreach ($q['options'] as $opt): 
                                    $isChecked = isset($publicSaved[$q['id']]['option_id']) && $publicSaved[$q['id']]['option_id'] == $opt['id'] ? 'checked' : '';
                                ?>
                                    <div class="form-check option-label d-flex align-items-start" style="cursor: pointer;" data-qid="<?= $q['id'] ?>" data-optid="<?= $opt['id'] ?>">
                                        <input class="form-check-input mt-1" type="radio" name="q_<?= $q['id'] ?>" id="opt_<?= $opt['id'] ?>" value="<?= $opt['id'] ?>" <?= $isChecked ?>>
                                        <label class="form-check-label w-100 d-flex" for="opt_<?= $opt['id'] ?>" style="cursor: pointer;">
                                            <span class="option-content d-flex">
                                                <span class="option-letter"><?= e($opt['option_letter']) ?></span>
                                                <span class="option-text"><?= e($opt['option_text']) ?></span>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="col-12 col-lg-4">
            <div class="sticky-sidebar">
                <div class="bright-timer d-none d-lg-block" id="timerBox">
                    <div class="timer-label" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8;">Time Remaining</div>
                    <div class="time-display" id="timer"><?= gmdate('i:s', max(0, (int)$remaining)) ?></div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold py-2 d-flex justify-content-between align-items-center" style="font-size: 0.85rem;">
                        <span><i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i>Palette</span>
                        <span class="badge bg-light text-dark border" id="answeredCountBadge">0 / <?= count($publicQuestions) ?></span>
                    </div>
                    <div class="card-body p-3">
                        <div id="palette" class="d-flex flex-wrap gap-2 mb-3"></div>
                        <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow-sm" style="font-size: 0.9rem;" id="submitBtn">
                            <i class="bi bi-check-circle-fill me-1"></i> Submit Exam
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX MODAL -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <h6 class="modal-title text-white mb-0">Diagram Zoom</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="lightboxImg" src="" alt="Zoomed diagram" class="img-fluid rounded" style="max-height:80vh">
            </div>
        </div>
    </div>
</div>

<!-- SUBMIT MODAL -->
<div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 text-white py-4" style="background: linear-gradient(135deg, #002147 0%, #003366 100%);">
                <div class="w-100 text-center">
                    <i class="bi bi-exclamation-triangle-fill mb-2" style="font-size: 2.5rem; color: #ffc107;"></i>
                    <h5 class="modal-title fw-bold mb-0">Submit Examination?</h5>
                </div>
            </div>
            <div class="modal-body py-4 px-4 text-center">
                <p class="mb-2">Answered: <strong id="answeredCountModal">0</strong> / <strong><?= count($publicQuestions) ?></strong></p>
                <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>Answers will be locked permanently.</p>
            </div>
            <div class="modal-footer border-0 py-3 px-4 d-flex gap-2">
                <button type="button" class="btn btn-light border flex-fill py-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?= url('index.php?page=submit_exam') ?>" id="finalSubmitForm" class="flex-fill m-0">
                    <input type="hidden" name="attempt_id" value="<?= (int)$attempt['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn w-100 py-2 fw-semibold text-white" style="background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); border: none;">Yes, Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ⚠️ ANTI-CHEAT WARNING MODAL -->
<div class="modal fade" id="warningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 text-white py-3" style="background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);">
                <h6 class="modal-title fw-bold mb-0 w-100 text-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>WARNING
                </h6>
            </div>
            <div class="modal-body py-4 px-4 text-center">
                <i class="bi bi-eye-slash-fill text-danger" style="font-size: 2.5rem;"></i>
                <h5 class="fw-bold mt-3 mb-2" id="warningTitle">Violation Detected!</h5>
                <p class="text-muted small mb-0" id="warningMessage">
                    You left the exam screen. This is a violation of exam rules.
                </p>
            </div>
            <div class="modal-footer border-0 py-3 justify-content-center">
                <button type="button" class="btn btn-danger px-4 fw-semibold" data-bs-dismiss="modal">
                    I Understand
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.CBT_ATTEMPT_ID  = <?= (int)$attempt['id'] ?>;
    window.CBT_QUESTIONS   = <?= json_encode($publicQuestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.CBT_SAVED       = <?= json_encode($publicSaved, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.CBT_REMAINING   = <?= (int)$remaining ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const answers = JSON.parse(JSON.stringify(window.CBT_SAVED));
    let saveTimeout, isSubmitting = false, tabSwitchCount = 0, currentVisibleIndex = 0;
    let offlineQueue = [];
    const STORAGE_KEY = `cbt_attempt_${window.CBT_ATTEMPT_ID}`;

    const els = {
        timerBox: document.getElementById('timerBox'), mobileTimerBox: document.getElementById('mobileTimerBox'),
        timer: document.getElementById('timer'), mobileTimer: document.getElementById('mobileTimer'),
        palette: document.getElementById('palette'), submitBtn: document.getElementById('submitBtn'),
        answeredCountBadge: document.getElementById('answeredCountBadge'), answeredCountModal: document.getElementById('answeredCountModal'),
        submitModal: new bootstrap.Modal(document.getElementById('submitModal')),
        warningModal: new bootstrap.Modal(document.getElementById('warningModal')),
        lightboxImg: document.getElementById('lightboxImg')
    };

    // --- 1. ANTI-CHEAT: FULLSCREEN & TAB SWITCH ---
    function enterFullscreen() {
        const elem = document.documentElement;
        if (elem.requestFullscreen) elem.requestFullscreen();
        else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
    }
    enterFullscreen(); // Force fullscreen on load

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && !isSubmitting) {
            showWarning('Fullscreen Exited!', 'You must keep the exam in fullscreen mode. Please return to fullscreen immediately.');
            setTimeout(enterFullscreen, 1000);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && !isSubmitting) {
            tabSwitchCount++;
            if (tabSwitchCount > 1) {
                showWarning('EXAM TERMINATED', 'You switched tabs or left the screen too many times. Your exam has been automatically submitted.');
                setTimeout(() => silentSubmitExam(), 2000);
            } else {
                showWarning('Tab Switch Detected!', 'You left the exam screen. This is a violation of exam rules. <strong>One more violation will result in automatic submission.</strong>');
            }
        }
    });

    function showWarning(title, msg) {
        document.getElementById('warningTitle').textContent = title;
        document.getElementById('warningMessage').innerHTML = msg;
        els.warningModal.show();
    }

    // Disable copy/paste/right-click
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('cut', e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());

    // --- 2. OFFLINE RESILIENCE (LOCALSTORAGE) ---
    function saveToLocalStorage(qid, optId, isFlagged) {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        data[qid] = { option_id: optId, is_flagged: isFlagged };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    function updateSaveStatus(html, className) {
        const badge = document.getElementById('autoSaveBadge');
        if (badge) { badge.innerHTML = html; badge.className = className; }
    }

    function debounceSave(qid, optionId, isFlagged) {
        saveToLocalStorage(qid, optionId, isFlagged);
        updateSaveStatus('<i class="bi bi-arrow-repeat spin me-1"></i>Saving...', 'badge bg-warning-subtle text-warning border border-warning px-2 py-1');
        
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            fetch('<?= url("index.php?page=save_answer") ?>', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attempt_id: window.CBT_ATTEMPT_ID, question_id: qid, option_id: optionId, flagged: isFlagged })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) updateSaveStatus('<i class="bi bi-check-circle-fill me-1"></i>Saved', 'badge bg-success-subtle text-success border border-success px-2 py-1');
                else if (data.error && data.error.includes('locked')) window.location.replace('<?= url("index.php?page=student_dashboard") ?>');
            })
            .catch(() => {
                offlineQueue.push({ qid, optionId, isFlagged });
                updateSaveStatus('<i class="bi bi-wifi-off me-1"></i>Saved Locally', 'badge bg-info-subtle text-info border border-info px-2 py-1');
            });
        }, 500);
    }

    window.addEventListener('online', () => {
        if (offlineQueue.length > 0) {
            updateSaveStatus('<i class="bi bi-arrow-repeat spin me-1"></i>Syncing...', 'badge bg-warning-subtle text-warning border border-warning px-2 py-1');
            let synced = 0;
            offlineQueue.forEach(item => {
                fetch('<?= url("index.php?page=save_answer") ?>', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attempt_id: window.CBT_ATTEMPT_ID, question_id: item.qid, option_id: item.optionId, flagged: item.isFlagged })
                }).then(() => {
                    synced++;
                    if (synced === offlineQueue.length) {
                        offlineQueue = [];
                        updateSaveStatus('<i class="bi bi-check-circle-fill me-1"></i>Synced', 'badge bg-success-subtle text-success border border-success px-2 py-1');
                    }
                });
            });
        }
    });

    // --- 3. KEYBOARD NAVIGATION ---
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => { if (entry.isIntersecting) currentVisibleIndex = parseInt(entry.target.dataset.index); });
    }, { threshold: 0.6 });
    document.querySelectorAll('.question-block').forEach(block => observer.observe(block));

    document.addEventListener('keydown', (e) => {
        if (isSubmitting) return;
        const key = e.key.toLowerCase();
        const currentQ = window.CBT_QUESTIONS[currentVisibleIndex];
        
        if (['a', 'b', 'c', 'd', 'e'].includes(key)) {
            const optIndex = key.charCodeAt(0) - 97;
            if (currentQ.options[optIndex]) {
                const label = document.querySelector(`.option-label[data-optid="${currentQ.options[optIndex].id}"]`);
                if (label) label.click();
            }
        } else if (key === 'f') {
            const flagBtn = document.querySelector(`.flag-btn[data-qid="${currentQ.id}"]`);
            if (flagBtn) flagBtn.click();
        } else if (key === 'arrowleft') {
            if (currentVisibleIndex > 0) document.getElementById(`question-${window.CBT_QUESTIONS[currentVisibleIndex - 1].id}`).scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (key === 'arrowright') {
            if (currentVisibleIndex < window.CBT_QUESTIONS.length - 1) document.getElementById(`question-${window.CBT_QUESTIONS[currentVisibleIndex + 1].id}`).scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (key === 'enter') {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') document.getElementById('submitBtn').click();
        }
    });

    // --- 4. UI & PALETTE LOGIC ---
    function buildPalette() {
        els.palette.innerHTML = '';
        window.CBT_QUESTIONS.forEach((q, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn btn-sm border palette-btn'; btn.textContent = idx + 1;
            btn.dataset.qid = q.id;
            btn.addEventListener('click', () => document.getElementById(`question-${q.id}`).scrollIntoView({ behavior: 'smooth', block: 'center' }));
            els.palette.appendChild(btn);
        });
        updatePalette(); updateAnsweredCount(); initializeFlags();
    }

    function initializeFlags() {
        document.querySelectorAll('.flag-btn').forEach(btn => {
            const qid = parseInt(btn.dataset.qid);
            if (answers[qid] && answers[qid].is_flagged) { btn.classList.add('btn-warning', 'text-dark'); btn.classList.remove('btn-outline-warning'); }
        });
    }

    function updatePalette() {
        els.palette.querySelectorAll('.palette-btn').forEach(btn => {
            const qid = parseInt(btn.dataset.qid), saved = answers[qid] || {};
            btn.className = 'btn btn-sm border palette-btn';
            if (saved.is_flagged) btn.classList.add('flagged');
            else if (saved.option_id) btn.classList.add('answered');
        });
    }

    function updateAnsweredCount() {
        const count = Object.values(answers).filter(a => a.option_id).length;
        els.answeredCountBadge.textContent = `${count} / ${window.CBT_QUESTIONS.length}`;
        els.answeredCountModal.textContent = count;
    }

    document.querySelectorAll('.option-label').forEach(label => {
        label.addEventListener('click', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.classList.contains('option-letter')) return;
            const qid = parseInt(label.dataset.qid), optId = parseInt(label.dataset.optid);
            label.querySelector('input[type="radio"]').checked = true;
            if (!answers[qid]) answers[qid] = {};
            answers[qid].option_id = optId;
            updatePalette(); updateAnsweredCount(); debounceSave(qid, optId, answers[qid].is_flagged || false);
        });
    });

    document.querySelectorAll('.flag-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const qid = parseInt(btn.dataset.qid);
            if (!answers[qid]) answers[qid] = {};
            answers[qid].is_flagged = !answers[qid].is_flagged;
            if (answers[qid].is_flagged) { btn.classList.add('btn-warning', 'text-dark'); btn.classList.remove('btn-outline-warning'); }
            else { btn.classList.remove('btn-warning', 'text-dark'); btn.classList.add('btn-outline-warning'); }
            updatePalette(); debounceSave(qid, answers[qid].option_id || null, answers[qid].is_flagged);
        });
    });

    // --- 5. TIMER & SUBMIT ---
    let remaining = window.CBT_REMAINING;
    window.onbeforeunload = null;
    window.addEventListener('beforeunload', (e) => { e.stopImmediatePropagation(); return undefined; }, true);

    function silentSubmitExam() {
        if (isSubmitting) return;
        isSubmitting = true;
        els.submitBtn.disabled = true; els.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        document.querySelectorAll('input[type="radio"], .flag-btn').forEach(el => el.disabled = true);
        const formData = new FormData();
        formData.append('attempt_id', window.CBT_ATTEMPT_ID); formData.append('csrf', window.CBT_CSRF);
        fetch('<?= url("index.php?page=submit_exam") ?>', { method: 'POST', body: formData, credentials: 'same-origin' })
        .finally(() => window.location.replace('<?= url("index.php?page=result&attempt_id=") ?>' + window.CBT_ATTEMPT_ID));
    }

    function tick() {
        if (remaining <= 0) {
            els.timer.textContent = '00:00'; els.mobileTimer.textContent = '00:00';
            els.timerBox?.classList.add('urgent'); els.mobileTimerBox.classList.add('urgent');
            silentSubmitExam(); return;
        }
        const m = Math.floor(remaining / 60).toString().padStart(2, '0'), s = (remaining % 60).toString().padStart(2, '0');
        els.timer.textContent = `${m}:${s}`; els.mobileTimer.textContent = `${m}:${s}`;
        if (remaining <= 300) { els.timerBox?.classList.add('urgent'); els.mobileTimerBox.classList.add('urgent'); }
        remaining--;
    }
    setInterval(tick, 1000); tick();

    els.submitBtn.addEventListener('click', () => els.submitModal.show());
    document.getElementById('finalSubmitForm').addEventListener('submit', (e) => { e.preventDefault(); els.submitModal.hide(); silentSubmitExam(); });
    document.querySelectorAll('.cbt-diagram').forEach(img => img.addEventListener('click', () => els.lightboxImg.src = img.src));

    buildPalette();
});
</script>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>