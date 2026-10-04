<?php $pageTitle = 'Examinee Management'; require APP_ROOT . '/app/views/partials/header.php'; ?>

<h1 class="h4 fw-bold mb-1">Examinee Management</h1>
<p class="text-muted small mb-4">Register students, set login credentials and assign them to exam groups.</p>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Register Examinee</div>
            <div class="card-body">
                <form method="post" action="<?= url('index.php?page=admin_examinees') ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">File No / Matric Number *</label>
                        <input type="text" name="reg_number" class="form-control form-control-sm" required placeholder="CS2023005">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input type="text" name="full_name" class="form-control form-control-sm" required placeholder="Timi Grace">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="john@student.edu">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Password *</label>
                        <input type="text" name="password" class="form-control form-control-sm" required value="student123">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold d-block">Exam Groups</label>
                        <div class="border rounded p-2" style="max-height:150px;overflow:auto">
                            <?php foreach ($groups as $g): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?= (int)$g['id'] ?>"
                                           id="ng<?= (int)$g['id'] ?>">
                                    <label class="form-check-label" style="font-size:.8rem" for="ng<?= (int)$g['id'] ?>"><?= e($g['name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="btn btn-success btn-sm w-100">Register Examinee</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <!-- ✅ UPDATED HEADER: Added flexbox and Bulk Upload Button -->
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Registered Examinees (<?= count($examinees) ?>)</span>
                <button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#bulkExamineeModal">⬆ Bulk Upload CSV</button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Reg No</th><th>Name</th><th>Email</th><th>Groups</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($examinees as $u): ?>
                        <tr>
                            <td class="font-monospace fw-semibold"><?= e($u['reg_number']) ?></td>
                            <td><?= e($u['full_name']) ?></td>
                            <td class="text-muted"><?= e((string)$u['email']) ?></td>
                            <td style="max-width:220px">
                                <?php foreach (array_filter(explode(',', (string)$u['group_names'])) as $gn): ?>
                                    <span class="badge bg-light text-dark border"><?= e(trim($gn)) ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($u['group_names'])): ?><span class="text-muted">Unassigned</span><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary js-edit-examinee"
                                        data-id="<?= (int)$u['id'] ?>"
                                        data-reg="<?= e($u['reg_number']) ?>"
                                        data-name="<?= e($u['full_name']) ?>"
                                        data-email="<?= e((string)$u['email']) ?>"
                                        data-groups="<?= e((string)$u['group_ids']) ?>">Edit</button>

                                <form method="post" action="<?= url('index.php?page=admin_examinees') ?>" class="d-inline js-confirm"
                                      data-message="Delete <?= e($u['reg_number']) ?> and all attempt history?">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($examinees)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No examinees registered yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Examinee Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('index.php?page=admin_examinees') ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-header">
                <h5 class="modal-title">Edit Examinee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Reg / Matric Number</label>
                    <input type="text" name="reg_number" id="edit_reg" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="full_name" id="edit_name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">New Password (leave blank to keep)</label>
                    <input type="text" name="password" class="form-control form-control-sm" placeholder="••••••">
                </div>
                <div>
                    <label class="form-label small fw-semibold d-block">Exam Groups</label>
                    <?php foreach ($groups as $g): ?>
                        <div class="form-check">
                            <input class="form-check-input js-edit-group" type="checkbox" name="group_ids[]" value="<?= (int)$g['id'] ?>"
                                   data-gid="<?= (int)$g['id'] ?>">
                            <label class="form-check-label" style="font-size:.8rem"><?= e($g['name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ✅ NEW: Bulk Examinee Upload Modal -->
<div class="modal fade" id="bulkExamineeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" enctype="multipart/form-data" action="<?= url('index.php?page=admin_examinees') ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="bulk_upload_examinees">

            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload Examinees</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info small">
                    <strong>CSV Format Required:</strong><br>
                    <code>reg_number, full_name, email, password, group_name</code><br><br>
                    <em><strong>Tips:</strong><br>
                    • Leave the <code>email</code> column blank if the student doesn't have one.<br>
                    • If the <code>group_name</code> doesn't exist in the system, the student will be created but left unassigned.<br>
                    • Duplicate Reg Numbers will be automatically skipped.</em>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select CSV File *</label>
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-info text-white">Upload Examinees</button>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>