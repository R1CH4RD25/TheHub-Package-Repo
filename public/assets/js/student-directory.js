/**
 * Student Directory Client
 *
 * Handles all student directory UI interactions:
 * - Student listing with search/filter/paginate
 * - Student detail view with inline edit
 * - Add/edit student forms
 * - CSV import with preview
 * - Export downloads
 * - Print cards
 * - Bulk operations
 */
document.addEventListener('DOMContentLoaded', function () {
    const API_BASE = '/api/student-directory.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ─── State ──────────────────────────────────────────────────
    let currentPage = 1;
    let currentFilters = {};
    let selectedStudents = new Set();

    // ─── Init ───────────────────────────────────────────────────
    const listContainer = document.getElementById('student-list');
    const detailContainer = document.getElementById('student-detail');
    const formContainer = document.getElementById('student-form');
    const importContainer = document.getElementById('student-import');
    const statsContainer = document.getElementById('student-stats');

    if (listContainer) initStudentList();
    if (statsContainer) loadStats();

    // ─── API Helpers ────────────────────────────────────────────
    async function apiGet(action, params = {}) {
        const url = new URL(API_BASE, window.location.origin);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([k, v]) => {
            if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, v);
        });

        const res = await fetch(url.toString());
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function apiPost(action, data = {}) {
        const res = await fetch(`${API_BASE}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...data, csrfToken }),
        });
        if (!res.ok && res.status !== 400) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function apiUpload(action, formData) {
        formData.append('csrfToken', csrfToken);
        const res = await fetch(`${API_BASE}?action=${action}`, {
            method: 'POST',
            body: formData,
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ─── Student List ───────────────────────────────────────────
    function initStudentList() {
        // Search box
        const searchInput = document.getElementById('student-search');
        if (searchInput) {
            let debounce;
            searchInput.addEventListener('input', () => {
                clearTimeout(debounce);
                debounce = setTimeout(() => {
                    currentFilters.search = searchInput.value;
                    currentPage = 1;
                    loadStudents();
                }, 300);
            });
        }

        // Grade filter
        const gradeFilter = document.getElementById('grade-filter');
        if (gradeFilter) {
            loadGradeOptions(gradeFilter);
            gradeFilter.addEventListener('change', () => {
                currentFilters.grade = gradeFilter.value;
                currentPage = 1;
                loadStudents();
            });
        }

        // Grad year filter
        const yearFilter = document.getElementById('grad-year-filter');
        if (yearFilter) {
            loadGradYearOptions(yearFilter);
            yearFilter.addEventListener('change', () => {
                currentFilters.graduation_year = yearFilter.value;
                currentPage = 1;
                loadStudents();
            });
        }

        // Select all checkbox
        const selectAll = document.getElementById('select-all-students');
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                document.querySelectorAll('.student-checkbox').forEach(cb => {
                    cb.checked = selectAll.checked;
                    if (selectAll.checked) {
                        selectedStudents.add(cb.value);
                    } else {
                        selectedStudents.delete(cb.value);
                    }
                });
                updateBulkActions();
            });
        }

        // Bulk delete button
        const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', handleBulkDelete);
        }

        // Print cards button
        const printCardsBtn = document.getElementById('print-cards-btn');
        if (printCardsBtn) {
            printCardsBtn.addEventListener('click', handlePrintCards);
        }

        loadStudents();
    }

    async function loadStudents() {
        if (!listContainer) return;

        const tbody = listContainer.querySelector('tbody') || listContainer;
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';

        try {
            const result = await apiGet('list', {
                ...currentFilters,
                page: currentPage,
                per_page: 50,
            });

            if (!result.success) throw new Error(result.error || 'Failed to load students');

            renderStudentTable(result.data, result.pagination);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error: ${err.message}</td></tr>`;
        }
    }

    function renderStudentTable(students, pagination) {
        const tbody = listContainer.querySelector('tbody');
        if (!tbody) return;

        if (students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No students found</td></tr>';
            return;
        }

        tbody.innerHTML = students.map(s => `
            <tr>
                <td><input type="checkbox" class="student-checkbox" value="${s.student_id}"
                    ${selectedStudents.has(s.student_id) ? 'checked' : ''}></td>
                <td>${escHtml(s.student_id)}</td>
                <td>
                    <a href="#" class="student-link" data-id="${s.student_id}">
                        ${escHtml(s.last_name)}, ${escHtml(s.first_name)}
                    </a>
                </td>
                <td>${escHtml(s.grade)}</td>
                <td>${escHtml(s.graduation_year || '')}</td>
                <td class="chromebook-login">${escHtml(s.chromebook_login || '')}</td>
                <td>${escHtml(s.ou_for_google || '')}</td>
            </tr>
        `).join('');

        // Bind checkboxes
        tbody.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                if (cb.checked) selectedStudents.add(cb.value);
                else selectedStudents.delete(cb.value);
                updateBulkActions();
            });
        });

        // Bind student links
        tbody.querySelectorAll('.student-link').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                viewStudent(link.dataset.id);
            });
        });

        // Render pagination
        renderPagination(pagination);
    }

    function renderPagination(p) {
        const paginationEl = document.getElementById('student-pagination');
        if (!paginationEl) return;

        let html = `<span class="text-muted">Showing page ${p.page} of ${p.total_pages} (${p.total} total)</span>`;
        html += '<div class="pagination-btns">';

        if (p.page > 1) {
            html += `<button class="btn btn-sm" onclick="window.studentDir.goToPage(${p.page - 1})">← Prev</button>`;
        }
        if (p.has_more) {
            html += `<button class="btn btn-sm" onclick="window.studentDir.goToPage(${p.page + 1})">Next →</button>`;
        }

        html += '</div>';
        paginationEl.innerHTML = html;
    }

    function updateBulkActions() {
        const count = selectedStudents.size;
        const bulkBar = document.getElementById('bulk-actions');
        if (bulkBar) {
            bulkBar.style.display = count > 0 ? 'flex' : 'none';
            const countEl = bulkBar.querySelector('.selected-count');
            if (countEl) countEl.textContent = `${count} selected`;
        }
    }

    // ─── Student Detail ─────────────────────────────────────────
    async function viewStudent(studentId) {
        try {
            const result = await apiGet('view', { student_id: studentId });
            if (!result.success) throw new Error(result.error);

            renderStudentDetail(result.data);
        } catch (err) {
            showMessage('Error loading student: ' + err.message, 'error');
        }
    }

    function renderStudentDetail(student) {
        if (!detailContainer) return;

        detailContainer.innerHTML = `
            <div class="student-detail-card">
                <div class="detail-header">
                    <h3>${escHtml(student.first_name)} ${escHtml(student.last_name)}</h3>
                    <span class="badge">${escHtml(student.grade)}</span>
                    <div class="detail-actions">
                        <button class="btn btn-sm btn-primary" onclick="window.studentDir.editStudent('${student.student_id}')">Edit</button>
                        <button class="btn btn-sm btn-warning" onclick="window.studentDir.resetPassword('${student.student_id}')">Reset Password</button>
                    </div>
                </div>

                <div class="detail-sections">
                    <div class="detail-section">
                        <h4>Personal Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item"><label>Student ID</label><span>${escHtml(student.student_id)}</span></div>
                            <div class="detail-item"><label>First Name</label><span>${escHtml(student.first_name)}</span></div>
                            <div class="detail-item"><label>Last Name</label><span>${escHtml(student.last_name)}</span></div>
                            <div class="detail-item"><label>Nickname</label><span>${escHtml(student.nickname || '—')}</span></div>
                            <div class="detail-item"><label>Date of Birth</label><span>${escHtml(student.dob || '—')}</span></div>
                            <div class="detail-item"><label>Grade</label><span>${escHtml(student.grade)}</span></div>
                            <div class="detail-item"><label>Graduation Year</label><span>${escHtml(student.graduation_year || '—')}</span></div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Account Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item"><label>Chromebook Login</label><span>${escHtml(student.chromebook_login || '—')}</span></div>
                            <div class="detail-item"><label>Group Email</label><span>${escHtml(student.group_email || '—')}</span></div>
                            <div class="detail-item"><label>OU for Google</label><span>${escHtml(student.ou_for_google || '—')}</span></div>
                            <div class="detail-item">
                                <label>Password</label>
                                <span class="password-field">
                                    <span class="password-masked">••••••••</span>
                                    <span class="password-value" style="display:none">${escHtml(student.password || '—')}</span>
                                    <button class="btn btn-xs btn-outline toggle-password" title="Show/hide password">👁</button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Demographics</h4>
                        <div class="detail-grid">
                            <div class="detail-item"><label>Hispanic/Latino</label><span>${student.hispanic_latino ? '✓' : '—'}</span></div>
                            <div class="detail-item"><label>American Indian</label><span>${student.american_indian_alaskan_native ? '✓' : '—'}</span></div>
                            <div class="detail-item"><label>Asian</label><span>${student.asian ? '✓' : '—'}</span></div>
                            <div class="detail-item"><label>Black/African American</label><span>${student.black_african_american ? '✓' : '—'}</span></div>
                            <div class="detail-item"><label>Hawaiian/Pacific Isl</label><span>${student.hawaiian_pacific_isl ? '✓' : '—'}</span></div>
                            <div class="detail-item"><label>White</label><span>${student.white ? '✓' : '—'}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Toggle password visibility
        detailContainer.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const field = btn.closest('.password-field');
                const masked = field.querySelector('.password-masked');
                const value = field.querySelector('.password-value');
                const isHidden = value.style.display === 'none';
                masked.style.display = isHidden ? 'none' : '';
                value.style.display = isHidden ? '' : 'none';
                btn.textContent = isHidden ? '🔒' : '👁';
            });
        });

        // Scroll to detail
        detailContainer.scrollIntoView({ behavior: 'smooth' });
    }

    // ─── Mutations ──────────────────────────────────────────────
    async function handleBulkDelete() {
        const ids = Array.from(selectedStudents);
        if (ids.length === 0) return;

        if (!confirm(`Delete ${ids.length} student(s)? This cannot be undone.`)) return;

        try {
            const result = await apiPost('bulkDelete', { student_ids: ids });
            if (result.success) {
                showMessage(result.message, 'success');
                selectedStudents.clear();
                updateBulkActions();
                loadStudents();
            } else {
                showMessage(result.message || result.error, 'error');
            }
        } catch (err) {
            showMessage('Delete failed: ' + err.message, 'error');
        }
    }

    async function handlePrintCards() {
        const ids = Array.from(selectedStudents);
        const grade = document.getElementById('grade-filter')?.value || null;

        if (ids.length === 0 && !grade) {
            showMessage('Select students or a grade to print cards', 'warning');
            return;
        }

        try {
            const res = await fetch(`${API_BASE}?action=printCards`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_ids: ids.length > 0 ? ids : [],
                    grade: ids.length === 0 ? grade : null,
                    csrfToken,
                }),
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const contentType = res.headers.get('content-type');
            if (contentType?.includes('text/html')) {
                const html = await res.text();
                const win = window.open('', '_blank');
                win.document.write(html);
                win.document.close();
            } else {
                const result = await res.json();
                showMessage(result.message || result.error, result.success ? 'success' : 'error');
            }
        } catch (err) {
            showMessage('Print failed: ' + err.message, 'error');
        }
    }

    async function resetPassword(studentId) {
        if (!confirm('Reset this student\'s password?')) return;

        try {
            const result = await apiPost('resetPassword', { student_id: studentId });
            if (result.success) {
                showMessage(`Password reset: ${result.new_password}`, 'success');
                viewStudent(studentId); // Refresh detail
            } else {
                showMessage(result.message || result.error, 'error');
            }
        } catch (err) {
            showMessage('Reset failed: ' + err.message, 'error');
        }
    }

    // ─── Student Form ───────────────────────────────────────────
    async function editStudent(studentId) {
        try {
            const result = await apiGet('view', { student_id: studentId });
            if (!result.success) throw new Error(result.error);
            showStudentForm(result.data);
        } catch (err) {
            showMessage('Error loading student: ' + err.message, 'error');
        }
    }

    function showStudentForm(student = null) {
        if (!formContainer) return;
        const isEdit = !!student;

        formContainer.innerHTML = `
            <div class="student-form-card">
                <h3>${isEdit ? 'Edit' : 'Add'} Student</h3>
                <form id="student-edit-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Student ID *</label>
                            <input type="text" name="student_id" value="${escHtml(student?.student_id || '')}"
                                ${isEdit ? 'readonly' : 'required'}>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" value="${escHtml(student?.first_name || '')}" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" value="${escHtml(student?.last_name || '')}" required>
                        </div>
                        <div class="form-group">
                            <label>Nickname</label>
                            <input type="text" name="nickname" value="${escHtml(student?.nickname || '')}">
                        </div>
                        <div class="form-group">
                            <label>Grade *</label>
                            <select name="grade" required>
                                <option value="">Select Grade</option>
                                ${['PK','KG','1','2','3','4','5','6','7','8','9','10','11','12'].map(g =>
                                    `<option value="${g}" ${student?.grade === g ? 'selected' : ''}>${g}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="${escHtml(student?.dob || '')}">
                        </div>
                        <div class="form-group">
                            <label>Sex</label>
                            <select name="sex">
                                <option value="">Select</option>
                                <option value="M" ${student?.sex === 'M' ? 'selected' : ''}>Male</option>
                                <option value="F" ${student?.sex === 'F' ? 'selected' : ''}>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" value="${escHtml(student?.middle_name || '')}">
                        </div>
                    </div>
                    <div class="form-section">
                        <h4>Race</h4>
                        <div class="checkbox-grid">
                            <label><input type="checkbox" name="hispanic_latino" value="1" ${student?.hispanic_latino ? 'checked' : ''}> Hispanic/Latino</label>
                            <label><input type="checkbox" name="american_indian_alaskan_native" value="1" ${student?.american_indian_alaskan_native ? 'checked' : ''}> American Indian/Alaskan Native</label>
                            <label><input type="checkbox" name="asian" value="1" ${student?.asian ? 'checked' : ''}> Asian</label>
                            <label><input type="checkbox" name="black_african_american" value="1" ${student?.black_african_american ? 'checked' : ''}> Black/African American</label>
                            <label><input type="checkbox" name="hawaiian_pacific_isl" value="1" ${student?.hawaiian_pacific_isl ? 'checked' : ''}> Hawaiian/Pacific Isl</label>
                            <label><input type="checkbox" name="white" value="1" ${student?.white ? 'checked' : ''}> White</label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Add'} Student</button>
                        <button type="button" class="btn btn-secondary" onclick="window.studentDir.cancelForm()">Cancel</button>
                    </div>
                </form>
            </div>
        `;

        const form = document.getElementById('student-edit-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const data = Object.fromEntries(fd.entries());

            // Checkbox handling
            ['hispanic_latino', 'american_indian_alaskan_native', 'asian', 'black_african_american', 'hawaiian_pacific_isl', 'white'].forEach(f => {
                data[f] = fd.has(f) ? 1 : 0;
            });

            try {
                const action = isEdit ? 'update' : 'create';
                const result = await apiPost(action, data);
                if (result.success) {
                    showMessage(result.message, 'success');
                    formContainer.innerHTML = '';
                    loadStudents();
                    if (isEdit) viewStudent(data.student_id);
                } else {
                    showMessage(result.message || result.error, 'error');
                }
            } catch (err) {
                showMessage('Save failed: ' + err.message, 'error');
            }
        });

        formContainer.scrollIntoView({ behavior: 'smooth' });
    }

    // ─── Import ──────────────────────────────────────────────────
    function initImport() {
        const uploadForm = document.getElementById('import-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', handleImportUpload);
        }
    }

    async function handleImportUpload(e) {
        e.preventDefault();
        const fileInput = document.getElementById('import-file');
        if (!fileInput?.files[0]) {
            showMessage('Select a CSV file', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('csrf_token', csrfToken);

        try {
            const result = await apiUpload('importPreview', formData);
            if (!result.success) throw new Error(result.message);
            renderImportPreview(result);
        } catch (err) {
            showMessage('Upload failed: ' + err.message, 'error');
        }
    }

    function renderImportPreview(data) {
        const previewEl = document.getElementById('import-preview');
        if (!previewEl) return;

        let html = `<h4>Preview (${data.total_rows} rows)</h4>`;

        // Field mapping UI
        html += '<div class="field-mapping"><h5>Map CSV columns to student fields:</h5>';
        html += '<div class="mapping-grid">';
        data.headers.forEach(header => {
            const suggested = data.suggested_mapping?.[header] || 'skip';
            html += `<div class="mapping-row">
                <label>${escHtml(header)}</label>
                <select class="field-map" data-csv-col="${escHtml(header)}">
                    <option value="skip" ${suggested === 'skip' ? 'selected' : ''}>-- Skip --</option>
                    ${data.importable_fields.map(f =>
                        `<option value="${f}" ${suggested === f ? 'selected' : ''}>${f}</option>`
                    ).join('')}
                </select>
            </div>`;
        });
        html += '</div></div>';

        // Preview table
        html += '<div class="preview-table-wrap"><table class="table table-sm"><thead><tr>';
        data.headers.forEach(h => html += `<th>${escHtml(h)}</th>`);
        html += '</tr></thead><tbody>';
        data.preview.forEach(row => {
            html += '<tr>';
            data.headers.forEach(h => html += `<td>${escHtml(row[h] || '')}</td>`);
            html += '</tr>';
        });
        html += '</tbody></table></div>';

        // Import mode + button
        html += `
            <div class="import-options">
                <div class="form-group">
                    <label>Import Mode</label>
                    <select id="import-mode">
                        <option value="append">Append (skip existing)</option>
                        <option value="rewrite">Rewrite (update existing)</option>
                        <option value="ignore">Ignore (insert new only)</option>
                    </select>
                </div>
                <button class="btn btn-primary" id="process-import-btn">Process Import</button>
            </div>
        `;

        previewEl.innerHTML = html;

        document.getElementById('process-import-btn')?.addEventListener('click', handleProcessImport);
    }

    async function handleProcessImport() {
        const selects = document.querySelectorAll('.field-map');
        const fieldMapping = {};
        selects.forEach(sel => {
            fieldMapping[sel.dataset.csvCol] = sel.value;
        });

        const mode = document.getElementById('import-mode')?.value || 'append';

        try {
            const result = await apiPost('importProcess', { field_mapping: fieldMapping, mode });
            if (result.success) {
                showMessage(result.message, 'success');
                loadStudents();
            } else {
                showMessage(result.message || result.error, 'error');
            }
        } catch (err) {
            showMessage('Import failed: ' + err.message, 'error');
        }
    }

    // ─── Stats ──────────────────────────────────────────────────
    async function loadStats() {
        if (!statsContainer) return;

        try {
            const result = await apiGet('stats');
            if (!result.success) return;

            const d = result.data;
            statsContainer.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-value">${d.total}</div><div class="stat-label">Total Students</div></div>
                    <div class="stat-card"><div class="stat-value">${d.elementary}</div><div class="stat-label">Elementary</div></div>
                    <div class="stat-card"><div class="stat-value">${d.junior_high}</div><div class="stat-label">Junior High</div></div>
                    <div class="stat-card"><div class="stat-value">${d.high_school}</div><div class="stat-label">High School</div></div>
                </div>
            `;
        } catch (err) {
            console.error('Stats load error:', err);
        }
    }

    // ─── Dropdown Loaders ───────────────────────────────────────
    async function loadGradeOptions(select) {
        try {
            const result = await apiGet('grades');
            if (!result.success) return;
            result.data.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.grade;
                opt.textContent = g.grade;
                select.appendChild(opt);
            });
        } catch (e) { /* silent */ }
    }

    async function loadGradYearOptions(select) {
        try {
            const result = await apiGet('gradYears');
            if (!result.success) return;
            result.data.forEach(y => {
                const opt = document.createElement('option');
                opt.value = y.graduation_year;
                opt.textContent = y.graduation_year;
                select.appendChild(opt);
            });
        } catch (e) { /* silent */ }
    }

    // ─── Export ─────────────────────────────────────────────────
    function exportStandard() {
        const grade = document.getElementById('grade-filter')?.value || '';
        window.open(`${API_BASE}?action=exportStandard&grade=${encodeURIComponent(grade)}`, '_blank');
    }

    function exportGoogle() {
        const grade = document.getElementById('grade-filter')?.value || '';
        window.open(`${API_BASE}?action=exportGoogle&grade=${encodeURIComponent(grade)}`, '_blank');
    }

    // ─── Utilities ──────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function showMessage(text, type = 'info') {
        if (typeof window.showMessage === 'function') {
            window.showMessage(text, type);
            return;
        }
        // Fallback inline notification
        const msg = document.createElement('div');
        msg.className = `alert alert-${type}`;
        msg.textContent = text;
        msg.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:6px;max-width:400px;';
        if (type === 'success') msg.style.background = '#d4edda';
        else if (type === 'error') msg.style.background = '#f8d7da';
        else if (type === 'warning') msg.style.background = '#fff3cd';
        else msg.style.background = '#d1ecf1';
        document.body.appendChild(msg);
        setTimeout(() => msg.remove(), 5000);
    }

    // ─── Public API ─────────────────────────────────────────────
    window.studentDir = {
        loadStudents,
        viewStudent,
        editStudent,
        resetPassword,
        goToPage: (page) => { currentPage = page; loadStudents(); },
        showAddForm: () => showStudentForm(null),
        cancelForm: () => { if (formContainer) formContainer.innerHTML = ''; },
        exportStandard,
        exportGoogle,
        initImport,
    };
});
