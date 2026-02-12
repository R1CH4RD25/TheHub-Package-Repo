/**
 * Sensitive Action Client
 *
 * Frontend SDK for the sensitive action (reveal) pattern.
 * Provides:
 * - Reason modal dialog
 * - Token request flow
 * - Countdown timer UI
 * - Auto-expiry cleanup
 * - Consume-and-display pattern
 *
 * Usage:
 *   // Initialize
 *   const sa = new SensitiveAction();
 *
 *   // Request reveal with reason modal
 *   document.querySelector('.reveal-btn').addEventListener('click', async (e) => {
 *     const result = await sa.requestReveal({
 *       packageId: 'com.woodson.student-directory',
 *       actionName: 'revealPassword',
 *       targetRecordId: 123,
 *       ttl: 30,
 *       onReveal: (metadata) => {
 *         // Fetch and display the actual sensitive data
 *         fetchSensitiveData(metadata.targetRecordId);
 *       }
 *     });
 *   });
 *
 *   // Direct token consume (if you already have a token)
 *   const data = await sa.consumeToken(token);
 *
 * @see /api/package/sensitive-action.php
 */

class SensitiveAction {
    constructor(options = {}) {
        this.apiBase = options.apiBase || '/api/sensitive-action.php';
        this.csrfToken = options.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.activeTimers = new Map(); // token → {interval, element}
    }

    /**
     * Request a sensitive data reveal with reason modal
     *
     * Shows a modal asking the user for a reason, requests a token,
     * then shows a countdown timer. On confirmation, consumes the token.
     *
     * @param {Object} options
     * @param {string} options.packageId - Package identifier
     * @param {string} options.actionName - Action name (e.g., 'revealPassword')
     * @param {number} [options.targetRecordId] - Record ID being revealed
     * @param {number} [options.ttl=30] - Token lifetime in seconds
     * @param {Function} options.onReveal - Callback when token is consumed (receives metadata)
     * @param {Function} [options.onExpire] - Callback when token expires
     * @param {Function} [options.onError] - Callback on error
     * @param {string} [options.title] - Modal title
     * @param {string} [options.description] - Modal description text
     */
    async requestReveal(options) {
        const {
            packageId, actionName, targetRecordId = null,
            ttl = 30, onReveal, onExpire, onError,
            title = 'Sensitive Data Request',
            description = 'This action will be logged. Please provide a reason for accessing this data.'
        } = options;

        // Show reason modal
        const reason = await this._showReasonModal(title, description);
        if (!reason) return null; // User cancelled

        try {
            // Request token
            const tokenData = await this._requestToken({
                packageId, actionName, targetRecordId, reason, ttl
            });

            // Show countdown
            const consumed = await this._showCountdown(tokenData, {
                title: title.replace('Request', 'Access'),
                onExpire
            });

            if (consumed) {
                // Consume the token
                const metadata = await this._consumeToken(tokenData.token);
                if (onReveal) onReveal(metadata);
                return metadata;
            }

            return null;
        } catch (error) {
            if (onError) {
                onError(error);
            } else {
                this._showMessage(error.message || 'Request failed', 'error');
            }
            return null;
        }
    }

    /**
     * Consume a token directly (no modal)
     */
    async consumeToken(token) {
        return await this._consumeToken(token);
    }

    /**
     * Validate a token
     */
    async validateToken(token) {
        return await this._apiCall('validate', { token });
    }

    /**
     * Revoke a token
     */
    async revokeToken(token) {
        return await this._apiCall('revoke', { token });
    }

    // =========================================================================
    // UI COMPONENTS
    // =========================================================================

    /**
     * Show reason input modal
     * @returns {Promise<string|null>} Reason text or null if cancelled
     */
    _showReasonModal(title, description) {
        return new Promise((resolve) => {
            // Remove any existing modal
            document.getElementById('sa-reason-modal')?.remove();

            const modal = document.createElement('div');
            modal.id = 'sa-reason-modal';
            modal.className = 'sa-modal-overlay';
            modal.innerHTML = `
                <div class="sa-modal">
                    <div class="sa-modal-header">
                        <svg class="sa-icon-shield" viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                        </svg>
                        <h3>${this._escape(title)}</h3>
                    </div>
                    <div class="sa-modal-body">
                        <p>${this._escape(description)}</p>
                        <label for="sa-reason-input">Reason <span class="sa-required">*</span></label>
                        <textarea id="sa-reason-input" class="sa-textarea"
                            placeholder="Enter your reason for accessing this data..."
                            minlength="5" required rows="3"></textarea>
                        <p class="sa-hint">Minimum 5 characters. This will be permanently recorded in the audit log.</p>
                    </div>
                    <div class="sa-modal-footer">
                        <button class="sa-btn sa-btn-cancel" id="sa-cancel-btn">Cancel</button>
                        <button class="sa-btn sa-btn-confirm" id="sa-confirm-btn" disabled>
                            <svg viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            Request Access
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const input = document.getElementById('sa-reason-input');
            const confirmBtn = document.getElementById('sa-confirm-btn');
            const cancelBtn = document.getElementById('sa-cancel-btn');

            // Enable button when reason is long enough
            input.addEventListener('input', () => {
                confirmBtn.disabled = input.value.trim().length < 5;
            });

            // Focus input
            setTimeout(() => input.focus(), 100);

            // Enter key submits
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey && !confirmBtn.disabled) {
                    e.preventDefault();
                    confirmBtn.click();
                }
            });

            // Confirm
            confirmBtn.addEventListener('click', () => {
                const reason = input.value.trim();
                modal.remove();
                resolve(reason);
            });

            // Cancel
            cancelBtn.addEventListener('click', () => {
                modal.remove();
                resolve(null);
            });

            // Escape key
            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    modal.remove();
                    resolve(null);
                }
            });

            // Click outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(null);
                }
            });
        });
    }

    /**
     * Show countdown timer modal
     * @returns {Promise<boolean>} True if user clicked "Reveal Now", false if expired/cancelled
     */
    _showCountdown(tokenData, options = {}) {
        return new Promise((resolve) => {
            document.getElementById('sa-countdown-modal')?.remove();

            let secondsLeft = tokenData.expiresIn;
            const title = options.title || 'Sensitive Data Access';

            const modal = document.createElement('div');
            modal.id = 'sa-countdown-modal';
            modal.className = 'sa-modal-overlay';
            modal.innerHTML = `
                <div class="sa-modal sa-modal-countdown">
                    <div class="sa-modal-header sa-header-active">
                        <svg class="sa-icon-timer" viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                        </svg>
                        <h3>${this._escape(title)}</h3>
                    </div>
                    <div class="sa-modal-body sa-countdown-body">
                        <div class="sa-countdown-ring">
                            <svg viewBox="0 0 120 120" width="120" height="120">
                                <circle class="sa-ring-bg" cx="60" cy="60" r="54" fill="none" stroke="#e0e0e0" stroke-width="8"/>
                                <circle class="sa-ring-progress" id="sa-ring" cx="60" cy="60" r="54" fill="none"
                                    stroke="var(--hub-primary, #1a73e8)" stroke-width="8" stroke-linecap="round"
                                    stroke-dasharray="${2 * Math.PI * 54}"
                                    stroke-dashoffset="0"
                                    transform="rotate(-90, 60, 60)"/>
                            </svg>
                            <span class="sa-countdown-number" id="sa-seconds">${secondsLeft}</span>
                        </div>
                        <p class="sa-countdown-label">Token expires in <strong id="sa-seconds-label">${secondsLeft}</strong> seconds</p>
                        <p class="sa-countdown-hint">Click "Reveal Now" to view the sensitive data, or wait for the token to expire.</p>
                    </div>
                    <div class="sa-modal-footer">
                        <button class="sa-btn sa-btn-cancel" id="sa-countdown-cancel">Cancel</button>
                        <button class="sa-btn sa-btn-reveal" id="sa-countdown-reveal">
                            <svg viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            Reveal Now
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const ring = document.getElementById('sa-ring');
            const secondsEl = document.getElementById('sa-seconds');
            const secondsLabel = document.getElementById('sa-seconds-label');
            const circumference = 2 * Math.PI * 54;
            const totalSeconds = secondsLeft;

            // Countdown timer
            const interval = setInterval(() => {
                secondsLeft--;
                secondsEl.textContent = secondsLeft;
                secondsLabel.textContent = secondsLeft;

                // Update ring
                const progress = 1 - (secondsLeft / totalSeconds);
                ring.style.strokeDashoffset = circumference * progress;

                // Color changes as time runs out
                if (secondsLeft <= 5) {
                    ring.style.stroke = '#d32f2f';
                    secondsEl.style.color = '#d32f2f';
                } else if (secondsLeft <= 10) {
                    ring.style.stroke = '#f57c00';
                    secondsEl.style.color = '#f57c00';
                }

                if (secondsLeft <= 0) {
                    clearInterval(interval);
                    modal.remove();
                    if (options.onExpire) options.onExpire();
                    this._showMessage('Token expired', 'warning');
                    resolve(false);
                }
            }, 1000);

            // Reveal button
            document.getElementById('sa-countdown-reveal').addEventListener('click', () => {
                clearInterval(interval);
                modal.remove();
                resolve(true);
            });

            // Cancel button
            document.getElementById('sa-countdown-cancel').addEventListener('click', async () => {
                clearInterval(interval);
                modal.remove();
                // Revoke the token
                try {
                    await this.revokeToken(tokenData.token);
                } catch (e) { /* ignore */ }
                resolve(false);
            });

            // Escape key
            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    clearInterval(interval);
                    modal.remove();
                    this.revokeToken(tokenData.token).catch(() => {});
                    resolve(false);
                }
            });
        });
    }

    // =========================================================================
    // API CALLS
    // =========================================================================

    async _requestToken({ packageId, actionName, targetRecordId, reason, ttl }) {
        return await this._apiCall('request', {
            packageId, actionName, targetRecordId, reason, ttl
        });
    }

    async _consumeToken(token) {
        return await this._apiCall('consume', { token });
    }

    async _apiCall(action, body) {
        const response = await fetch(`${this.apiBase}?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || `Request failed (${response.status})`);
        }

        return data;
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    _escape(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    _showMessage(text, type = 'info') {
        // Use hub's shared showMessage if available
        if (typeof showMessage === 'function') {
            showMessage(text, type);
        } else {
            console.log(`[SensitiveAction] ${type}: ${text}`);
        }
    }

    /**
     * Destroy and cleanup
     */
    destroy() {
        this.activeTimers.forEach(({ interval }) => clearInterval(interval));
        this.activeTimers.clear();
        document.getElementById('sa-reason-modal')?.remove();
        document.getElementById('sa-countdown-modal')?.remove();
    }
}

// Export for module systems, attach to window for vanilla JS
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SensitiveAction;
} else {
    window.SensitiveAction = SensitiveAction;
}
