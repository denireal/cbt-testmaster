/**
 * public/assets/js/cbt.js
 * Custom jQuery/AJAX scripts for the CBT system (Bootstrap 5 + jQuery).
 *
 * Handles:
 *  - CBT exam engine: palette, autosave, flags, synced timer, auto-submit
 *  - Diagram lightbox
 *  - Admin helpers (edit modals, delete confirmations)
 */
(function ($) {
    'use strict';

    var BASE = window.CBT_BASE_URL || '';
    var CSRF = window.CBT_CSRF || '';
    var AJAX = BASE + '/process_ajax.php';

    /* ================================================================== *
     |  1. CBT EXAM ENGINE                                                |
     * ================================================================== */
    if (window.CBT_QUESTIONS && window.CBT_ATTEMPT_ID) {
        var questions   = window.CBT_QUESTIONS;
        var attemptId   = window.CBT_ATTEMPT_ID;
        var answers     = {};   // questionId -> optionId
        var flags       = {};   // questionId -> bool
        var current     = 0;
        var remaining   = parseInt(window.CBT_REMAINING || 0, 10);
        var timerHandle = null;
        var submitting  = false;

        // Restore previously saved responses (resume support)
        $.each(window.CBT_SAVED || {}, function (qid, row) {
            if (row.option_id) { answers[qid] = row.option_id; }
            flags[qid] = !!row.is_flagged;
        });

        /* -------------------------- TIMER --------------------------- */
        function pad(n) { return (n < 10 ? '0' : '') + n; }

        function renderTimer() {
            var m = Math.floor(remaining / 60), s = remaining % 60;
            $('#timer').text(pad(m) + ':' + pad(s));
            $('#timer').toggleClass('cbt-timer-warning', remaining <= 300);
        }

        function startTimer() {
            renderTimer();
            timerHandle = setInterval(function () {
                remaining--;
                renderTimer();
                if (remaining <= 0) {
                    clearInterval(timerHandle);
                    submitExam(true);          // auto-submit at zero
                }
            }, 1000);

            // Re-sync with the server clock every 30s (anti tampering)
            setInterval(syncServerTime, 30000);
        }

        function syncServerTime() {
            $.post(AJAX + '?action=time_left', { attempt_id: attemptId, csrf: CSRF })
                .done(function (res) {
                    if (res.status !== 'success') { return; }
                    if (res.expired) {
                        clearInterval(timerHandle);
                        window.location.href = res.summary
                            ? BASE + '/index.php?page=result&attempt_id=' + attemptId
                            : BASE + '/index.php?page=student_dashboard';
                        return;
                    }
                    remaining = parseInt(res.remaining, 10);
                    renderTimer();
                });
        }

        /* ------------------------ RENDERING ------------------------- */
        function buildPalette() {
            var html = '';
            $.each(questions, function (i, q) {
                html += '<button type="button" class="btn btn-sm cbt-palette" data-index="' + i + '">' + (i + 1) + '</button>';
            });
            $('#palette').html(html);
        }

        function paintPalette() {
            $('#palette .cbt-palette').each(function () {
                var i  = parseInt($(this).data('index'), 10);
                var q  = questions[i];
                var $b = $(this);

                $b.removeClass('btn-success btn-light btn-primary cbt-palette-flagged cbt-palette-current')
                  .addClass(answers[q.id] ? 'btn-success' : 'btn-light');

                if (flags[q.id]) { $b.addClass('cbt-palette-flagged'); }
                if (i === current) { $b.addClass('cbt-palette-current'); }
            });
        }

        function renderQuestion(index) {
            current = index;
            var q = questions[index];

            $('#qBadge').text('Question ' + (index + 1) + ' of ' + questions.length);
            $('#qText').text(q.question_text);

            if (q.diagram_path) {
                $('#diagramImg').attr('src', q.diagram_path);
                $('#lightboxImg').attr('src', q.diagram_path);
                $('#diagramBox').removeClass('d-none');
            } else {
                $('#diagramBox').addClass('d-none');
            }

            var html = '';
            $.each(q.options, function (_, opt) {
                var selected = answers[q.id] === opt.id;
                html += '<div class="cbt-option' + (selected ? ' selected' : '') + '" data-qid="' + q.id + '" data-oid="' + opt.id + '">'
                      +   '<span class="fw-bold me-2">' + opt.option_letter + '.</span>'
                      +   $('<span/>').text(opt.option_text).html()
                      + '</div>';
            });
            $('#optionsBox').html(html);

            $('#flagText').text(flags[q.id] ? 'Flagged for Review' : 'Flag for Review');
            $('#flagBtn').toggleClass('btn-warning', !!flags[q.id]).toggleClass('btn-outline-warning', !flags[q.id]);
            $('#prevBtn').prop('disabled', index === 0);
            $('#nextBtn').prop('disabled', index === questions.length - 1);

            paintPalette();
        }

        /* ------------------------- ACTIONS -------------------------- */
        function setStatus(state) {
            var $s = $('#saveStatus');
            if (state === 'saving') { $s.removeClass('text-success text-danger').addClass('text-muted').text('Saving…'); }
            else if (state === 'error') { $s.removeClass('text-success text-muted').addClass('text-danger').text('Save failed'); }
            else { $s.removeClass('text-muted text-danger').addClass('text-success').text('Saved ✓'); }
        }

        $(document).on('click', '.cbt-option', function () {
            var qid = parseInt($(this).data('qid'), 10);
            var oid = parseInt($(this).data('oid'), 10);

            answers[qid] = oid;
            renderQuestion(current);
            setStatus('saving');

            $.post(AJAX + '?action=save_answer', { attempt_id: attemptId, question_id: qid, option_id: oid, csrf: CSRF })
                .done(function (res) { setStatus(res.status === 'success' ? 'saved' : 'error'); })
                .fail(function () { setStatus('error'); });
        });

        $('#flagBtn').on('click', function () {
            var qid = questions[current].id;
            flags[qid] = !flags[qid];
            paintPalette();
            $('#flagText').text(flags[qid] ? 'Flagged for Review' : 'Flag for Review');
            $('#flagBtn').toggleClass('btn-warning', flags[qid]).toggleClass('btn-outline-warning', !flags[qid]);

            $.post(AJAX + '?action=toggle_flag', { attempt_id: attemptId, question_id: qid, flagged: flags[qid] ? 1 : 0, csrf: CSRF });
        });

        $('#prevBtn').on('click', function () { if (current > 0) { renderQuestion(current - 1); } });
        $('#nextBtn').on('click', function () { if (current < questions.length - 1) { renderQuestion(current + 1); } });

        $(document).on('click', '#palette .cbt-palette', function () {
            renderQuestion(parseInt($(this).data('index'), 10));
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'ArrowLeft')  { $('#prevBtn').trigger('click'); }
            if (e.key === 'ArrowRight') { $('#nextBtn').trigger('click'); }
        });

        function submitExam(auto) {
            if (submitting) { return; }
            if (!auto && !window.confirm('Submit your examination now? Answers will be locked.')) { return; }

            submitting = true;
            clearInterval(timerHandle);
            $('#submitBtn').prop('disabled', true).text(auto ? 'Time expired — submitting…' : 'Submitting…');

            $.post(AJAX + '?action=submit_exam', { attempt_id: attemptId, csrf: CSRF })
                .done(function (res) {
                    window.location.href = res.redirect
                        || (BASE + '/index.php?page=result&attempt_id=' + attemptId);
                })
                .fail(function () {
                    submitting = false;
                    $('#submitBtn').prop('disabled', false).text('Submit Final Exam');
                    window.alert('Submission failed. Please try again.');
                });
        }

        $('#submitBtn').on('click', function () { submitExam(false); });

        // Warn before leaving mid-exam
        $(window).on('beforeunload', function () {
            if (!submitting) { return 'Your examination is still in progress.'; }
        });

        buildPalette();
        renderQuestion(0);
        startTimer();
    }

    /* ================================================================== *
     |  2. ADMIN HELPERS                                                  |
     * ================================================================== */

    // Delete confirmations
    $(document).on('submit', 'form.js-confirm', function (e) {
        if (!window.confirm($(this).data('message') || 'Are you sure?')) { e.preventDefault(); }
    });

    // Edit examinee modal
    $(document).on('click', '.js-edit-examinee', function () {
        var d = $(this).data();
        $('#edit_id').val(d.id);
        $('#edit_reg').val(d.reg);
        $('#edit_name').val(d.name);
        $('#edit_email').val(d.email);
        var assigned = String(d.groups || '').split(',');
        $('.js-edit-group').each(function () {
            $(this).prop('checked', assigned.indexOf(String($(this).data('gid'))) !== -1);
        });
        new bootstrap.Modal('#editModal').show();
    });

    // Create / edit exam modal
    $(document).on('click', '[data-bs-target="#examModal"]', function () {
        var mode = $(this).data('mode') || 'create';
        $('#exam_action').val(mode);
        $('#examModalLabel').text(mode === 'create' ? 'Create Exam' : 'Edit Exam');
        if (mode === 'create') {
            $('#exam_id').val('');
            $('#exam_title, #exam_description').val('');
            $('#exam_group').val('');
            $('#exam_duration').val(15);
            $('#exam_pass').val(50);
            $('#exam_pos').val('1.00');
            $('#exam_neg').val('0.25');
            $('#exam_sq, #exam_so, #exam_score, #exam_active').prop('checked', true);
        }
    });

    $(document).on('click', '.js-edit-exam', function () {
        var ex = $(this).data('exam');
        $('#exam_action').val('update');
        $('#examModalLabel').text('Edit Exam');
        $('#exam_id').val(ex.id);
        $('#exam_title').val(ex.title);
        $('#exam_description').val(ex.description || '');
        $('#exam_group').val(ex.group_id);
        $('#exam_duration').val(ex.duration_minutes);
        $('#exam_pass').val(ex.passing_percentage);
        $('#exam_pos').val(ex.positive_marks);
        $('#exam_neg').val(ex.negative_marks);
        $('#exam_sq').prop('checked', ex.shuffle_questions == 1);
        $('#exam_so').prop('checked', ex.shuffle_options == 1);
        $('#exam_score').prop('checked', ex.show_immediate_score == 1);
        $('#exam_active').prop('checked', ex.is_active == 1);
        new bootstrap.Modal('#examModal').show();
    });

    // Create / edit question modal
    $(document).on('click', '[data-bs-target="#qModal"]', function () {
        var mode = $(this).data('mode') || 'create';
        $('#q_action').val(mode);
        $('#qModalLabel').text(mode === 'create' ? 'Add MCQ Question' : 'Edit MCQ Question');
        if (mode === 'create') {
            $('#q_id').val('');
            $('#q_text, #q_explanation, #q_existing_diagram').val('');
            $('#q_diagram').val('');
            $('#q_diagram_current').addClass('d-none');
            $('#q_options .js-option').val('');
            $('#q_options .js-correct').first().prop('checked', true);
        }
    });

    $(document).on('click', '.js-edit-question', function () {
        var q = $(this).data('question');
        $('#q_action').val('update');
        $('#qModalLabel').text('Edit MCQ Question');
        $('#q_id').val(q.id);
        $('#q_text').val(q.question_text);
        $('#q_explanation').val(q.explanation || '');
        $('#q_existing_diagram').val(q.diagram_path || '');
        $('#q_diagram').val('');

        if (q.diagram_path) {
            $('#q_diagram_preview').attr('src', BASE + q.diagram_path);
            $('#q_diagram_current').removeClass('d-none');
        } else {
            $('#q_diagram_current').addClass('d-none');
        }

        var $inputs = $('#q_options .js-option');
        var $radios = $('#q_options .js-correct');
        $.each(q.options, function (i, opt) {
            if ($inputs[i]) { $inputs[i].value = opt.option_text; }
            if (opt.is_correct == 1 && $radios[i]) { $radios[i].checked = true; }
        });
        new bootstrap.Modal('#qModal').show();
    });

    // Add a 5th option row on demand
    $(document).on('click', '#q_add_option', function () {
        var count = $('#q_options .input-group').length;
        if (count >= 6) { return; }
        var letter = String.fromCharCode(65 + count);
        var row = '<div class="input-group input-group-sm mb-2">'
                +   '<div class="input-group-text"><input class="form-check-input mt-0 js-correct" type="radio" name="correct_index" value="' + count + '"></div>'
                +   '<span class="input-group-text fw-bold">' + letter + '</span>'
                +   '<input type="text" name="option_text[]" class="form-control js-option" placeholder="Option ' + letter + '">'
                + '</div>';
        $('#q_options').append(row);
        $(this).hide();
    });

})(jQuery);
