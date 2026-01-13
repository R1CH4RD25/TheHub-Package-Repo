/**
 * Debug Helper - Viewport & Element Inspector
 * 
 * Shows viewport dimensions, breakpoints, and element info on hover
 * Toggle with Ctrl+Shift+D or click the debug icon
 * 
 * Usage: Include this script and call DebugHelper.init()
 */

class DebugHelper {
    constructor() {
        this.enabled = localStorage.getItem('debug_helper_enabled') === 'true';
        this.panel = null;
        this.hoverBox = null;
        this.updateInterval = null;
    }

    init() {
        this.createDebugPanel();
        this.createHoverBox();
        this.attachEventListeners();
        
        if (this.enabled) {
            this.show();
        }
        
        // Keyboard shortcut: Ctrl+Shift+D
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                this.toggle();
            }
        });
    }

    createDebugPanel() {
        this.panel = document.createElement('div');
        this.panel.id = 'debug-helper-panel';
        this.panel.innerHTML = `
            <div class="debug-header">
                <span class="debug-title">🔍 Debug Info</span>
                <button class="debug-close" onclick="window.debugHelper.hide()">✕</button>
            </div>
            <div class="debug-content">
                <div class="debug-section">
                    <strong>Viewport</strong>
                    <div id="debug-viewport">Loading...</div>
                </div>
                <div class="debug-section">
                    <strong>Breakpoint</strong>
                    <div id="debug-breakpoint">Loading...</div>
                </div>
                <div class="debug-section">
                    <strong>Screen</strong>
                    <div id="debug-screen">Loading...</div>
                </div>
                <div class="debug-section">
                    <strong>Mouse</strong>
                    <div id="debug-mouse">Move mouse...</div>
                </div>
                <div class="debug-section">
                    <strong>Hover Element</strong>
                    <div id="debug-hover">Hover over elements...</div>
                </div>
            </div>
            <div class="debug-footer">
                <small>Press Ctrl+Shift+D to toggle</small>
            </div>
        `;
        
        document.body.appendChild(this.panel);
        this.injectStyles();
    }

    createHoverBox() {
        this.hoverBox = document.createElement('div');
        this.hoverBox.id = 'debug-hover-box';
        document.body.appendChild(this.hoverBox);
    }

    injectStyles() {
        if (document.getElementById('debug-helper-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'debug-helper-styles';
        styles.textContent = `
            #debug-helper-panel {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 320px;
                background: rgba(0, 0, 0, 0.95);
                color: #00ff00;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
                z-index: 999999;
                display: none;
                backdrop-filter: blur(10px);
            }
            
            #debug-helper-panel.visible {
                display: block;
                animation: debugSlideIn 0.3s ease-out;
            }
            
            @keyframes debugSlideIn {
                from {
                    transform: translateY(100px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .debug-header {
                padding: 12px 16px;
                border-bottom: 1px solid rgba(0, 255, 0, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .debug-title {
                font-weight: bold;
                font-size: 14px;
            }
            
            .debug-close {
                background: none;
                border: none;
                color: #00ff00;
                cursor: pointer;
                font-size: 18px;
                padding: 0;
                width: 24px;
                height: 24px;
                border-radius: 4px;
                transition: all 0.2s;
            }
            
            .debug-close:hover {
                background: rgba(0, 255, 0, 0.2);
            }
            
            .debug-content {
                padding: 16px;
                max-height: 400px;
                overflow-y: auto;
            }
            
            .debug-section {
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid rgba(0, 255, 0, 0.1);
            }
            
            .debug-section:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            
            .debug-section strong {
                color: #00ffff;
                display: block;
                margin-bottom: 4px;
                font-size: 11px;
                text-transform: uppercase;
            }
            
            .debug-section div {
                color: #00ff00;
                line-height: 1.4;
            }
            
            .debug-footer {
                padding: 8px 16px;
                border-top: 1px solid rgba(0, 255, 0, 0.3);
                text-align: center;
                color: rgba(0, 255, 0, 0.6);
                font-size: 10px;
            }
            
            #debug-hover-box {
                position: fixed;
                border: 2px solid #ff00ff;
                background: rgba(255, 0, 255, 0.1);
                pointer-events: none;
                z-index: 999998;
                display: none;
                box-shadow: 0 0 10px rgba(255, 0, 255, 0.5);
            }
            
            #debug-hover-box.visible {
                display: block;
            }
            
            /* Floating debug toggle button */
            #debug-toggle-btn {
                position: fixed;
                bottom: 20px;
                right: 360px;
                width: 48px;
                height: 48px;
                background: rgba(0, 0, 0, 0.8);
                color: #00ff00;
                border: 2px solid #00ff00;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                z-index: 999999;
                transition: all 0.3s;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            }
            
            #debug-toggle-btn:hover {
                background: rgba(0, 255, 0, 0.2);
                transform: scale(1.1);
            }
            
            @media (max-width: 768px) {
                #debug-helper-panel {
                    width: calc(100% - 40px);
                    right: 20px;
                    bottom: 20px;
                    font-size: 11px;
                }
                
                #debug-toggle-btn {
                    right: 20px;
                    bottom: 80px;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }

    attachEventListeners() {
        // Update viewport info
        this.updateInterval = setInterval(() => {
            if (this.enabled) {
                this.updateViewportInfo();
            }
        }, 100);

        // Mouse tracking
        document.addEventListener('mousemove', (e) => {
            if (!this.enabled) return;
            
            const mouseEl = document.getElementById('debug-mouse');
            if (mouseEl) {
                mouseEl.textContent = `X: ${e.clientX}px, Y: ${e.clientY}px`;
            }
            
            // Element hover detection
            const target = e.target;
            if (target && target !== this.panel && !this.panel.contains(target)) {
                this.showHoverInfo(target, e);
            }
        });

        // Window resize
        window.addEventListener('resize', () => {
            if (this.enabled) {
                this.updateViewportInfo();
            }
        });
    }

    updateViewportInfo() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        
        // Viewport
        const viewportEl = document.getElementById('debug-viewport');
        if (viewportEl) {
            viewportEl.innerHTML = `${width}px × ${height}px<br>Ratio: ${(width/height).toFixed(2)}`;
        }
        
        // Breakpoint
        const breakpointEl = document.getElementById('debug-breakpoint');
        if (breakpointEl) {
            let breakpoint = 'xs';
            let color = '#ff6b6b';
            
            if (width >= 1400) {
                breakpoint = 'xxl';
                color = '#51cf66';
            } else if (width >= 1200) {
                breakpoint = 'xl';
                color = '#4dabf7';
            } else if (width >= 992) {
                breakpoint = 'lg';
                color = '#748ffc';
            } else if (width >= 768) {
                breakpoint = 'md';
                color = '#ffd43b';
            } else if (width >= 576) {
                breakpoint = 'sm';
                color = '#ffa94d';
            }
            
            breakpointEl.innerHTML = `<span style="color: ${color}; font-weight: bold;">${breakpoint.toUpperCase()}</span> (${width}px)`;
        }
        
        // Screen
        const screenEl = document.getElementById('debug-screen');
        if (screenEl) {
            screenEl.innerHTML = `
                ${screen.width}px × ${screen.height}px<br>
                DPR: ${window.devicePixelRatio || 1}x<br>
                Orientation: ${screen.orientation?.type || 'unknown'}
            `;
        }
    }

    showHoverInfo(element, event) {
        const rect = element.getBoundingClientRect();
        const hoverEl = document.getElementById('debug-hover');
        
        if (hoverEl) {
            const tagName = element.tagName.toLowerCase();
            const id = element.id ? `#${element.id}` : '';
            const classes = element.className ? `.${element.className.split(' ').join('.')}` : '';
            
            hoverEl.innerHTML = `
                <strong style="color: #ff00ff;">${tagName}${id}${classes}</strong><br>
                Size: ${Math.round(rect.width)}px × ${Math.round(rect.height)}px<br>
                Pos: (${Math.round(rect.left)}, ${Math.round(rect.top)})<br>
                Display: ${getComputedStyle(element).display}
            `;
        }
        
        // Show hover box
        if (this.hoverBox) {
            this.hoverBox.style.left = rect.left + 'px';
            this.hoverBox.style.top = rect.top + 'px';
            this.hoverBox.style.width = rect.width + 'px';
            this.hoverBox.style.height = rect.height + 'px';
            this.hoverBox.classList.add('visible');
        }
    }

    show() {
        this.enabled = true;
        localStorage.setItem('debug_helper_enabled', 'true');
        this.panel.classList.add('visible');
        this.updateViewportInfo();
        console.log('🔍 Debug Helper enabled');
    }

    hide() {
        this.enabled = false;
        localStorage.setItem('debug_helper_enabled', 'false');
        this.panel.classList.remove('visible');
        if (this.hoverBox) {
            this.hoverBox.classList.remove('visible');
        }
        console.log('🔍 Debug Helper disabled');
    }

    toggle() {
        if (this.enabled) {
            this.hide();
        } else {
            this.show();
        }
    }

    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        if (this.panel) {
            this.panel.remove();
        }
        if (this.hoverBox) {
            this.hoverBox.remove();
        }
    }
}

// Auto-initialize on load
if (typeof window !== 'undefined') {
    window.debugHelper = new DebugHelper();
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.debugHelper.init();
        });
    } else {
        window.debugHelper.init();
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DebugHelper;
}
