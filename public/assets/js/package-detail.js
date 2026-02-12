/**
 * Package Detail Component JS (Layer 3)
 *
 * Handles: action button interactions, copy-to-clipboard for fields,
 * image lightbox, mutation actions from detail view.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ========================================
    // ACTION BUTTONS (ROUTE + MUTATION)
    // ========================================
    // Route actions handled natively by <a> tags
    // Mutation actions delegate to package-table.js mutation handler

    // ========================================
    // COPY TO CLIPBOARD ON DETAIL FIELDS
    // ========================================
    document.querySelectorAll('.pkg-detail-copy').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var value = this.dataset.value || this.previousElementSibling.textContent;
            navigator.clipboard.writeText(value.trim()).then(function () {
                btn.classList.add('copied');
                var originalTitle = btn.title;
                btn.title = 'Copied!';

                var icon = btn.querySelector('i');
                if (icon) {
                    var origClass = icon.className;
                    icon.className = 'bi bi-check-lg';
                    setTimeout(function () {
                        icon.className = origClass;
                        btn.classList.remove('copied');
                        btn.title = originalTitle;
                    }, 1500);
                }
            }).catch(function () {
                // Fallback for older browsers
                var textarea = document.createElement('textarea');
                textarea.value = value.trim();
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            });
        });
    });

    // ========================================
    // IMAGE LIGHTBOX
    // ========================================
    document.querySelectorAll('.pkg-detail-image').forEach(function (img) {
        img.style.cursor = 'pointer';
        img.addEventListener('click', function () {
            var overlay = document.createElement('div');
            overlay.className = 'pkg-lightbox-overlay';
            overlay.innerHTML = '<img src="' + this.src + '" alt="' + (this.alt || '') + '">';
            document.body.appendChild(overlay);

            overlay.addEventListener('click', function () {
                overlay.remove();
            });

            document.addEventListener('keydown', function handler(e) {
                if (e.key === 'Escape') {
                    overlay.remove();
                    document.removeEventListener('keydown', handler);
                }
            });
        });
    });

    // ========================================
    // MASKED FIELD TOGGLE
    // ========================================
    document.querySelectorAll('.pkg-masked-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var container = this.closest('.pkg-detail-value');
            var maskedEl = container.querySelector('.pkg-masked-value');
            var clearEl = container.querySelector('.pkg-clear-value');

            if (!maskedEl || !clearEl) return;

            var isRevealed = clearEl.style.display !== 'none';
            if (isRevealed) {
                maskedEl.style.display = 'inline';
                clearEl.style.display = 'none';
                this.innerHTML = '<i class="bi bi-eye"></i>';
                this.title = 'Show';
            } else {
                maskedEl.style.display = 'none';
                clearEl.style.display = 'inline';
                this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                this.title = 'Hide';
            }
        });
    });

    // ========================================
    // PRINT DETAIL VIEW
    // ========================================
    document.querySelectorAll('.pkg-detail-print').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    });

    // ========================================
    // BACK BUTTON
    // ========================================
    document.querySelectorAll('.pkg-back-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var href = this.getAttribute('href');
            if (!href || href === '#') {
                e.preventDefault();
                window.history.back();
            }
        });
    });

    // ========================================
    // INJECT LIGHTBOX CSS
    // ========================================
    var style = document.createElement('style');
    style.textContent = [
        '.pkg-lightbox-overlay {',
        '  position: fixed; top: 0; left: 0; right: 0; bottom: 0;',
        '  background: rgba(0,0,0,0.85);',
        '  display: flex; align-items: center; justify-content: center;',
        '  z-index: 10000; cursor: pointer;',
        '}',
        '.pkg-lightbox-overlay img {',
        '  max-width: 90vw; max-height: 90vh;',
        '  border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);',
        '}',
        '.pkg-detail-copy.copied { color: #22c55e !important; }',
        '@media print {',
        '  .pkg-detail-actions, .pkg-back-btn, .pkg-detail-print { display: none !important; }',
        '}',
    ].join('\n');
    document.head.appendChild(style);
});
