/**
 * Package Table Component JS (Layer 3)
 *
 * Handles: sorting, pagination, search/filter, bulk selection,
 * row actions (mutations with confirmation modals), and copy-to-clipboard.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ========================================
    // SORTING
    // ========================================

    // Apply default sort indicators on load
    document.querySelectorAll('.pkg-table').forEach(function (table) {
        var tableId = table.id;
        var config = (window.__pkgTableConfig || {})[tableId];
        if (config && config.defaultSort) {
            var col = config.defaultSort.column;
            var dir = config.defaultSort.direction || 'asc';
            var th = table.querySelector('.pkg-col-sortable[data-sort-key="' + col + '"]');
            if (th) {
                th.classList.add('sort-' + dir);
                th.dataset.sortDir = dir;
            }
        }
    });

    document.querySelectorAll('.pkg-col-sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            var key = this.dataset.sortKey;
            var table = this.closest('.pkg-table');
            var tableId = table.id;
            var config = (window.__pkgTableConfig || {})[tableId];
            if (!config) return;

            // Toggle sort direction
            var currentDir = this.dataset.sortDir || '';
            var newDir = currentDir === 'asc' ? 'desc' : 'asc';

            // Clear other sort indicators
            table.querySelectorAll('.pkg-col-sortable').forEach(function (el) {
                el.classList.remove('sort-asc', 'sort-desc');
                el.dataset.sortDir = '';
            });

            this.classList.add('sort-' + newDir);
            this.dataset.sortDir = newDir;

            // Reload table data
            pkgTableReload(tableId, { sort: key, sortDir: newDir, page: 1 });
        });

        // Keyboard support
        th.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // ========================================
    // PAGINATION
    // ========================================
    document.querySelectorAll('.pkg-page-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (this.disabled) return;
            var page = parseInt(this.dataset.page, 10);
            var tableId = this.closest('.pkg-pagination').dataset.table;
            pkgTableReload(tableId, { page: page });
        });
    });

    document.querySelectorAll('.pkg-page-size-select').forEach(function (select) {
        select.addEventListener('change', function () {
            var tableId = this.dataset.table;
            var pageSize = parseInt(this.value, 10);
            pkgTableReload(tableId, { pageSize: pageSize, page: 1 });
        });
    });

    // ========================================
    // FILTERS
    // ========================================
    document.querySelectorAll('.pkg-filter-input').forEach(function (input) {
        var debounceMs = parseInt(input.dataset.debounce || '0', 10);
        var timer = null;

        var handler = function () {
            var filters = collectFilters(input.closest('.pkg-filters'));
            var targetTable = input.closest('.pkg-filters').dataset.targetTable;
            if (targetTable) {
                pkgTableReload(targetTable, Object.assign({ page: 1 }, filters));
            }
        };

        if (debounceMs > 0) {
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(handler, debounceMs);
            });
        } else {
            input.addEventListener('change', handler);
        }
    });

    // Clear filters
    document.querySelectorAll('.pkg-clear-filters').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var container = this.closest('.pkg-filters');
            container.querySelectorAll('.pkg-filter-input').forEach(function (input) {
                if (input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
            // Navigate to page without filter params
            window.location.href = window.location.pathname;
        });
    });

    function collectFilters(container) {
        var filters = {};
        container.querySelectorAll('.pkg-filter-input').forEach(function (input) {
            var name = input.name;
            if (input.type === 'checkbox') {
                if (input.checked) filters[name] = input.value;
            } else if (input.value) {
                filters[name] = input.value;
            }
        });
        return filters;
    }

    // ========================================
    // BULK SELECTION
    // ========================================
    document.querySelectorAll('.pkg-select-all').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var tableId = this.dataset.table;
            var table = document.getElementById(tableId);
            var checked = this.checked;
            table.querySelectorAll('.pkg-row-select').forEach(function (cb) {
                cb.checked = checked;
            });
            updateBulkCount(tableId);
        });
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('pkg-row-select')) {
            var tableId = e.target.dataset.table;
            updateBulkCount(tableId);
        }
    });

    function updateBulkCount(tableId) {
        var table = document.getElementById(tableId);
        var selected = table.querySelectorAll('.pkg-row-select:checked');
        var bulkDiv = document.getElementById(tableId + '-bulk');
        if (bulkDiv) {
            bulkDiv.style.display = selected.length > 0 ? 'flex' : 'none';
            var countEl = bulkDiv.querySelector('.bulk-count');
            if (countEl) countEl.textContent = selected.length + ' selected';
        }
    }

    function getSelectedIds(tableId) {
        var table = document.getElementById(tableId);
        var ids = [];
        table.querySelectorAll('.pkg-row-select:checked').forEach(function (cb) {
            ids.push(cb.value);
        });
        return ids;
    }

    // ========================================
    // ROW ACTIONS (MUTATIONS)
    // ========================================
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="mutation"]');
        if (!btn) return;

        e.preventDefault();
        var mutation = btn.dataset.mutation;
        var rowId = btn.dataset.rowId;
        var packageId = btn.dataset.package;
        var csrf = btn.dataset.csrf;
        var requiresReason = btn.dataset.requiresReason === 'true';
        var requiresConfirm = btn.dataset.requiresConfirm === 'true';
        var confirmMessage = btn.dataset.confirmMessage || 'Are you sure you want to perform this action?';
        var cooldown = parseInt(btn.dataset.cooldown || '0', 10);
        var reasonOptions = [];

        try {
            reasonOptions = JSON.parse(btn.dataset.reasonOptions || '[]');
        } catch (err) { /* ignore */ }

        if (requiresReason || requiresConfirm) {
            showMutationModal({
                mutation: mutation,
                rowId: rowId,
                packageId: packageId,
                csrf: csrf,
                requiresReason: requiresReason,
                reasonOptions: reasonOptions,
                confirmMessage: confirmMessage,
                cooldown: cooldown,
                triggerBtn: btn,
            });
        } else {
            executeMutation(mutation, { id: rowId }, packageId, csrf, btn, cooldown);
        }
    });

    // Bulk mutation actions
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="bulk-mutation"]');
        if (!btn) return;

        e.preventDefault();
        var tableId = btn.dataset.table;
        var ids = getSelectedIds(tableId);
        if (ids.length === 0) return;

        var mutation = btn.dataset.mutation;
        var csrf = btn.dataset.csrf;

        if (confirm('Apply action to ' + ids.length + ' selected records?')) {
            executeMutation(mutation, { ids: ids }, '', csrf, btn, 0);
        }
    });

    // ========================================
    // MUTATION MODAL
    // ========================================
    function showMutationModal(opts) {
        var modal = document.getElementById('pkgMutationModal');
        var title = document.getElementById('pkgMutationModalTitle');
        var body = document.getElementById('pkgMutationModalBody');
        var confirmBtn = document.getElementById('pkgMutationModalConfirm');

        title.textContent = 'Confirm Action';
        var bodyHtml = '<p>' + escapeHtml(opts.confirmMessage) + '</p>';

        if (opts.requiresReason) {
            bodyHtml += '<div class="reason-field">';
            bodyHtml += '<label for="pkg-mutation-reason">Reason <span class="pkg-required">*</span></label>';
            if (opts.reasonOptions.length > 0) {
                bodyHtml += '<select id="pkg-mutation-reason" class="form-select" required>';
                bodyHtml += '<option value="">Select a reason...</option>';
                opts.reasonOptions.forEach(function (opt) {
                    bodyHtml += '<option value="' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</option>';
                });
                bodyHtml += '</select>';
            } else {
                bodyHtml += '<textarea id="pkg-mutation-reason" class="form-control" required placeholder="Enter reason..."></textarea>';
            }
            bodyHtml += '</div>';
        }

        body.innerHTML = bodyHtml;

        // Show modal
        modal.style.display = 'flex';

        // Confirm handler
        var newConfirm = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);

        newConfirm.addEventListener('click', function () {
            var input = { id: opts.rowId };

            if (opts.requiresReason) {
                var reasonEl = document.getElementById('pkg-mutation-reason');
                var reason = reasonEl ? reasonEl.value.trim() : '';
                if (!reason) {
                    reasonEl.classList.add('is-invalid');
                    return;
                }
                input.reason = reason;
            }

            closeModal();
            executeMutation(opts.mutation, input, opts.packageId, opts.csrf, opts.triggerBtn, opts.cooldown);
        });

        // Close handlers
        modal.querySelectorAll('[data-dismiss="modal"]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        function closeModal() {
            modal.style.display = 'none';
        }
    }

    // ========================================
    // EXECUTE MUTATION
    // ========================================
    function executeMutation(mutation, input, packageId, csrf, triggerBtn, cooldown) {
        if (triggerBtn) triggerBtn.disabled = true;

        var body = Object.assign({}, input, {
            package: packageId,
            mutation: mutation,
            csrf_token: csrf,
        });

        fetch('/api/package.php?action=mutation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.error) {
                showToast(data.error, 'error');
            } else {
                showToast(data.message || 'Action completed successfully', 'success');

                // Refresh the table if applicable
                var table = triggerBtn ? triggerBtn.closest('.pkg-table') : null;
                if (table) {
                    pkgTableReload(table.id, {});
                }
            }
        })
        .catch(function (err) {
            showToast('Network error: ' + err.message, 'error');
        })
        .finally(function () {
            if (triggerBtn) {
                if (cooldown > 0) {
                    var original = triggerBtn.innerHTML;
                    var remaining = cooldown;
                    triggerBtn.textContent = remaining + 's';
                    var interval = setInterval(function () {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(interval);
                            triggerBtn.innerHTML = original;
                            triggerBtn.disabled = false;
                        } else {
                            triggerBtn.textContent = remaining + 's';
                        }
                    }, 1000);
                } else {
                    triggerBtn.disabled = false;
                }
            }
        });
    }

    // ========================================
    // TABLE RELOAD (AJAX)
    // ========================================
    window.pkgTableReload = function (tableId, params) {
        var config = (window.__pkgTableConfig || {})[tableId];
        if (!config) return;

        // Build new URL with pagination/sort/filter params for server-side rendering
        var currentUrl = new URL(window.location.href);
        var searchParams = currentUrl.searchParams;

        // Apply new params (page, pageSize, sort, sortDir, filters)
        Object.keys(params).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                // Map JS param names to server-side expected names
                var serverKey = key;
                if (key === 'pageSize') serverKey = 'per_page';
                if (key === 'sortDir') serverKey = 'direction';
                searchParams.set(serverKey, params[key]);
            } else {
                searchParams.delete(key);
            }
        });

        // Navigate to updated URL — server re-renders with correct page/sort
        window.location.href = currentUrl.toString();
    };

    // ========================================
    // COPY TO CLIPBOARD
    // ========================================
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pkg-copy-btn');
        if (!btn) return;

        e.preventDefault();
        var value = btn.dataset.value;
        navigator.clipboard.writeText(value).then(function () {
            btn.classList.add('copied');
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'bi bi-check';
                setTimeout(function () {
                    icon.className = 'bi bi-clipboard';
                    btn.classList.remove('copied');
                }, 1500);
            }
        });
    });

    // ========================================
    // TOAST NOTIFICATIONS
    // ========================================
    window.showToast = function (message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'pkg-toast pkg-toast-' + type;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    };

    // ========================================
    // SHOW/HIDE MASKED VALUES (Passwords)
    // Works on both desktop (click) and mobile (touch)
    // ========================================
    function handleMaskedToggle(e) {
        var btn = e.target.closest('.pkg-show-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        // Find the masked element — it's a sibling span inside the same <td>
        var td = btn.closest('td');
        if (!td) return;
        var maskedEl = td.querySelector('.pkg-masked-value');
        if (!maskedEl) return;

        var actual = maskedEl.dataset.actual || '';
        var isShowing = btn.classList.contains('active');

        if (isShowing) {
            // Hide it
            maskedEl.textContent = actual ? '\u2022'.repeat(Math.min(actual.length, 8)) : '\u2022\u2022\u2022\u2022\u2022\u2022';
            btn.innerHTML = '<i class="fas fa-eye"></i> Show';
            btn.classList.remove('active');
        } else {
            // Show it
            maskedEl.textContent = actual || '(empty)';
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
            btn.classList.add('active');
        }
    }

    // Listen to both click and touchend for mobile compatibility
    document.addEventListener('click', handleMaskedToggle);
    document.addEventListener('touchend', function (e) {
        var btn = e.target.closest('.pkg-show-btn');
        if (!btn) return;
        // Prevent the subsequent click event from firing (avoids double-toggle)
        e.preventDefault();
        handleMaskedToggle(e);
    });

    // ========================================
    // FILTER FORM SUBMIT (prevent page reload, use AJAX)
    // ========================================
    document.querySelectorAll('.pkg-filters form, form.pkg-filters-row').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var container = this.closest('.pkg-filters') || this;
            var filters = collectFilters(container);
            // Build URL preserving existing params (id, page route) and adding filter params
            var currentUrl = new URL(window.location.href);
            var searchParams = currentUrl.searchParams;
            // Remove old filter params, add new ones, reset to page 1
            Object.keys(filters).forEach(function (key) {
                if (filters[key]) {
                    searchParams.set(key, filters[key]);
                } else {
                    searchParams.delete(key);
                }
            });
            searchParams.set('page_num', '1'); // Reset to page 1 on filter change
            searchParams.delete('page_num'); // Remove if the backend uses 'page' not 'page_num'
            // If a 'page' filter param exists as a number, keep it at 1
            if (searchParams.has('page') && !isNaN(searchParams.get('page'))) {
                // 'page' might be used for route (e.g. 'index'), only reset if numeric
            }
            window.location.href = currentUrl.toString();
        });
    });

    // ========================================
    // UTILITIES
    // ========================================
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
