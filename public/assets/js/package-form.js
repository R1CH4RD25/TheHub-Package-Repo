/**
 * Package Form Component JS (Layer 3)
 *
 * Handles: AJAX form submission, client-side validation,
 * collapsible sections, dependent fields, optionsQuery loading.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ========================================
    // COLLAPSIBLE SECTIONS
    // ========================================
    document.querySelectorAll('.pkg-form-section-header').forEach(function (header) {
        header.addEventListener('click', function () {
            var section = this.closest('.pkg-form-section');
            section.classList.toggle('collapsed');
            var body = section.querySelector('.pkg-form-section-body');
            if (body) {
                body.style.display = section.classList.contains('collapsed') ? 'none' : 'block';
            }
            var icon = this.querySelector('.toggle-icon');
            if (icon) {
                icon.style.transform = section.classList.contains('collapsed') ? 'rotate(-90deg)' : '';
            }
        });
    });

    // ========================================
    // DYNAMIC OPTIONS (optionsQuery)
    // ========================================
    document.querySelectorAll('select[data-options-query]').forEach(function (select) {
        var query = select.dataset.optionsQuery;
        var packageId = select.dataset.package;
        if (!query || !packageId) return;

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Loading...';
        placeholder.disabled = true;
        placeholder.selected = true;
        select.appendChild(placeholder);

        fetch('/api/package.php?action=query&package=' + encodeURIComponent(packageId) +
              '&query=' + encodeURIComponent(query))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var rows = data.data || [];
                select.innerHTML = '';

                var empty = document.createElement('option');
                empty.value = '';
                empty.textContent = select.dataset.placeholder || 'Select...';
                select.appendChild(empty);

                rows.forEach(function (row) {
                    var opt = document.createElement('option');
                    opt.value = row.value || row.id || '';
                    opt.textContent = row.label || row.name || opt.value;
                    if (select.dataset.selectedValue && opt.value === select.dataset.selectedValue) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });
            })
            .catch(function () {
                select.innerHTML = '<option value="">Failed to load options</option>';
            });
    });

    // ========================================
    // FORM SUBMISSION
    // ========================================
    document.querySelectorAll('.pkg-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('.pkg-field-error').forEach(function (el) {
                el.textContent = '';
                el.style.display = 'none';
            });

            // Client-side validation
            if (!validateForm(form)) return;

            var config = (window.__pkgFormConfig || {})[form.id];
            if (!config) {
                console.error('No form config found for', form.id);
                return;
            }

            var submitBtn = form.querySelector('[type="submit"]');
            var originalText = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Saving...';
            }

            // Collect form data
            var formData = new FormData(form);
            var input = {};

            formData.forEach(function (value, key) {
                // Handle array fields (e.g., checkboxes)
                if (key.endsWith('[]')) {
                    var arrayKey = key.slice(0, -2);
                    if (!input[arrayKey]) input[arrayKey] = [];
                    input[arrayKey].push(value);
                } else {
                    input[key] = value;
                }
            });

            // If there's a file input, use FormData directly
            var hasFiles = form.querySelector('input[type="file"]');
            var body;
            var headers = {};

            if (hasFiles) {
                formData.append('package', config.packageId);
                formData.append('mutation', config.mutation);
                body = formData;
            } else {
                body = JSON.stringify({
                    packageId: config.packageId,
                    mutationName: config.mutation,
                    input: input,
                    csrfToken: config.csrfToken || config.csrf,
                });
                headers['Content-Type'] = 'application/json';
                headers['X-Requested-With'] = 'XMLHttpRequest';
            }

            // Use new Sprint 3 mutation endpoint with fallback
            var mutationUrl = hasFiles
                ? '/api/package.php?action=mutation'
                : '/api/package-mutation.php';

            fetch(mutationUrl, {
                method: 'POST',
                headers: headers,
                body: body,
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    // Server-side validation errors
                    if (data.fieldErrors) {
                        Object.keys(data.fieldErrors).forEach(function (field) {
                            var input = form.querySelector('[name="' + field + '"]');
                            if (input) {
                                input.classList.add('is-invalid');
                                var errEl = input.closest('.pkg-form-field')
                                    ? input.closest('.pkg-form-field').querySelector('.pkg-field-error')
                                    : null;
                                if (errEl) {
                                    errEl.textContent = data.fieldErrors[field];
                                    errEl.style.display = 'block';
                                }
                            }
                        });
                    }
                    showToast(data.error, 'error');
                } else {
                    showToast(data.message || 'Saved successfully', 'success');

                    // Redirect if specified
                    if (data.redirect || config.successRedirect) {
                        setTimeout(function () {
                            window.location.href = data.redirect || config.successRedirect;
                        }, 500);
                    }
                }
            })
            .catch(function (err) {
                showToast('Network error: ' + err.message, 'error');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        });
    });

    // ========================================
    // CLIENT-SIDE VALIDATION
    // ========================================
    function validateForm(form) {
        var valid = true;

        form.querySelectorAll('[required]').forEach(function (input) {
            var value = input.value ? input.value.trim() : '';
            if (!value || (input.type === 'checkbox' && !input.checked)) {
                markInvalid(input, 'This field is required');
                valid = false;
            }
        });

        form.querySelectorAll('[type="email"]').forEach(function (input) {
            if (input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                markInvalid(input, 'Please enter a valid email address');
                valid = false;
            }
        });

        form.querySelectorAll('[pattern]').forEach(function (input) {
            if (input.value) {
                var regex = new RegExp(input.pattern);
                if (!regex.test(input.value)) {
                    markInvalid(input, input.title || 'Invalid format');
                    valid = false;
                }
            }
        });

        form.querySelectorAll('[minlength]').forEach(function (input) {
            var min = parseInt(input.getAttribute('minlength'), 10);
            if (input.value && input.value.length < min) {
                markInvalid(input, 'Must be at least ' + min + ' characters');
                valid = false;
            }
        });

        form.querySelectorAll('[data-type="currency"]').forEach(function (input) {
            if (input.value && isNaN(parseFloat(input.value))) {
                markInvalid(input, 'Please enter a valid number');
                valid = false;
            }
        });

        if (!valid) {
            var first = form.querySelector('.is-invalid');
            if (first) {
                first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                first.focus();
            }
        }

        return valid;
    }

    function markInvalid(input, message) {
        input.classList.add('is-invalid');
        var field = input.closest('.pkg-form-field');
        if (field) {
            var errEl = field.querySelector('.pkg-field-error');
            if (errEl) {
                errEl.textContent = message;
                errEl.style.display = 'block';
            }
        }
    }

    // Live validation clear
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('is-invalid')) {
            e.target.classList.remove('is-invalid');
            var field = e.target.closest('.pkg-form-field');
            if (field) {
                var errEl = field.querySelector('.pkg-field-error');
                if (errEl) {
                    errEl.textContent = '';
                    errEl.style.display = 'none';
                }
            }
        }
    });

    // ========================================
    // CANCEL / BACK BUTTON
    // ========================================
    document.querySelectorAll('.pkg-form-cancel').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var href = this.getAttribute('href');
            if (href) {
                window.location.href = href;
            } else {
                window.history.back();
            }
        });
    });

    // ========================================
    // CURRENCY FORMATTING
    // ========================================
    document.querySelectorAll('.pkg-currency-input').forEach(function (input) {
        input.addEventListener('blur', function () {
            var val = parseFloat(this.value);
            if (!isNaN(val)) {
                this.value = val.toFixed(2);
            }
        });
    });

    // ========================================
    // FILE INPUT PREVIEW
    // ========================================
    document.querySelectorAll('.pkg-file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var preview = this.closest('.pkg-form-field').querySelector('.pkg-file-preview');
            if (!preview) return;

            preview.innerHTML = '';
            Array.from(this.files).forEach(function (file) {
                var item = document.createElement('div');
                item.className = 'pkg-file-item';
                item.innerHTML = '<i class="bi bi-file-earmark"></i> ' +
                    escapeHtml(file.name) + ' <small>(' + formatBytes(file.size) + ')</small>';
                preview.appendChild(item);
            });
        });
    });

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
