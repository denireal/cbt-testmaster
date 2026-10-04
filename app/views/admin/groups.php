<?php $pageTitle = 'Exam Groups'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<h1 class="h4 fw-bold mb-1">Exam Groups</h1>
<p class="text-muted small mb-4">Create groups such as <em>Computer Science 100L</em>, <em>General Studies</em>, <em>Remedial</em> and assign examinees to them.</p>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Create Exam Group</div>
            <div class="card-body">
                <form method="post" action="<?= url('index.php?page=admin_groups') ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Group Name *</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Computer Science 100L">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Optional description"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100">Save Group</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Existing Groups (<?= count($groups) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Group</th><th class="text-center">Members</th><th class="text-center">Exams</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($g['name']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem"><?= e((string)$g['description']) ?></div>
                                </td>
                                <td class="text-center"><span class="badge bg-primary"><?= (int)$g['member_count'] ?></span></td>
                                <td class="text-center"><span class="badge bg-info text-dark"><?= (int)$g['exam_count'] ?></span></td>
                                <td class="text-end text-nowrap">
                                    <!-- ✅ NEW: Edit Button -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-edit-group"
                                            data-id="<?= (int)$g['id'] ?>"
                                            data-name="<?= e($g['name']) ?>"
                                            data-description="<?= e((string)$g['description']) ?>">
                                        Edit
                                    </button>

                                    <form method="post" action="<?= url('index.php?page=admin_groups') ?>" class="d-inline js-confirm"
                                          data-message="Delete group <?= e($g['name']) ?> and its exam assignments?">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No groups yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Assign Examinees to Groups</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>Examinee</th><th>Reg No</th><th>Groups</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($examinees as $u): $assigned = array_filter(explode(',', (string)$u['group_ids'])); ?>
                        <tr>
                            <form method="post" action="<?= url('index.php?page=admin_groups') ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <td class="fw-semibold"><?= e($u['full_name']) ?></td>
                                <td class="font-monospace"><?= e($u['reg_number']) ?></td>
                                <td>
                                    <?php foreach ($groups as $g): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="group_ids[]"
                                                   value="<?= (int)$g['id'] ?>" id="g<?= (int)$u['id'] ?>_<?= (int)$g['id'] ?>"
                                                   <?= in_array((string)$g['id'], $assigned, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" style="font-size:.8rem"
                                                   for="g<?= (int)$u['id'] ?>_<?= (int)$g['id'] ?>"><?= e($g['name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-primary">Save</button></td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($examinees)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No examinees registered yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ✅ NEW: Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('index.php?page=admin_groups') ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_group_id">

            <div class="modal-header">
                <h5 class="modal-title">Edit Exam Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Group Name *</label>
                    <input type="text" name="name" id="edit_group_name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" id="edit_group_description" class="form-control form-control-sm" rows="3"></textarea>
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Changing the group name will automatically update it on all assigned exams and examinees.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ✅ NEW: JavaScript to populate the Edit Modal -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editModal = new bootstrap.Modal(document.getElementById('editGroupModal'));
    
    document.querySelectorAll('.js-edit-group').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_group_id').value = btn.dataset.id;
            document.getElementById('edit_group_name').value = btn.dataset.name;
            document.getElementById('edit_group_description').value = btn.dataset.description;
            editModal.show();
        });
    });
});
</script>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>