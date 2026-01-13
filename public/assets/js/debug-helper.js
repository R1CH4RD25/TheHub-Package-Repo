/**
 * Debug Helper - Comprehensive Logging System
 * 
 * Logs element widths, window sizes, and user action flow to console.
 * Helps debug responsive design and trace user interactions.
 */
(function() {
    'use strict';

    // Global debug flag
    window.DEBUG_MODE = true;

    /**
     * Debug logger with timestamps and color coding
     */
    window.debugLog = function(category, message, data = null) {
        if (!window.DEBUG_MODE) return;
        
        const timestamp = new Date().toLocaleTimeString('en-US', { hour12: false, fractional: 3 });
        const colors = {
            'ACTION': '#FF6B6B',      // Red
            'API': '#4ECDC4',         // Teal
            'RESPONSE': '#95E1D3',    // Light teal
            'ERROR': '#F38181',       // Pink
            'SUCCESS': '#00D9FF',     // Cyan
            'UI': '#FFE66D',          // Yellow
            'WINDOW': '#2196F3',      // Blue
            'ELEMENT': '#9C27B0'      // Purple
        };
        
        const color = colors[category] || '#888';
        console.log(`%c[${timestamp}] ${category}:`, `color: ${color}; font-weight: bold;`, message, data || '');
    };

    /**
     * Get current breakpoint
     */
    function getCurrentBreakpoint() {
        const width = window.innerWidth;
        if (width < 576) return 'xs (<576px)';
        if (width < 768) return 'sm (576-767px)';
        if (width < 992) return 'md (768-991px)';
        if (width < 1200) return 'lg (992-1199px)';
        if (width < 1400) return 'xl (1200-1399px)';
        return 'xxl (≥1400px)';
    }

    /**
     * Get element info with overflow detection
     */
    function getElementInfo(element) {
        const rect = element.getBoundingClientRect();
        const computed = window.getComputedStyle(element);
        const scrollWidth = element.scrollWidth;
        const overflowAmount = scrollWidth - rect.width;
        const hasOverflow = overflowAmount > 1;
        
        return {
            width: Math.round(rect.width),
            scrollWidth: Math.round(scrollWidth),
            overflowX: computed.overflowX,
            hasOverflow: hasOverflow,
            overflowAmount: hasOverflow ? Math.round(overflowAmount) : 0
        };
    }

    /**
     * Get element identifier
     */
    function getElementIdentifier(element) {
        const tag = element.tagName.toLowerCase();
        const id = element.id ? `#${element.id}` : '';
        const classes = element.className ? `.${element.className.trim().split(/\s+/).join('.')}` : '';
        return `${tag}${id}${classes}`;
    }

    /**
     * Log element with styling
     */
    function logElement(label, element, indent = 0) {
        if (!element) {
            console.log(`${' '.repeat(indent)}${label}: NOT FOUND`);
            return;
        }

        const info = getElementInfo(element);
        const identifier = getElementIdentifier(element);
        const indentStr = ' '.repeat(indent);
        
        if (info.hasOverflow) {
            console.log(
                `${indentStr}%c⚠️ ${label}%c ${identifier}`,
                'color: #ff9800; font-weight: bold;',
                'color: #666;'
            );
            console.log(
                `${indentStr}   Width: ${info.width}px, Scroll: ${info.scrollWidth}px, Overflow: +${info.overflowAmount}px`
            );
        } else {
            console.log(
                `${indentStr}✓ ${label}: ${identifier} = ${info.width}px`
            );
        }
    }

    /**
     * Main element width logging
     */
    function logElementWidths() {
        console.clear();
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #2196f3; font-weight: bold;');
        console.log('%c🔍 RESPONSIVE DEBUG - ELEMENT WIDTHS', 'color: #2196f3; font-weight: bold; font-size: 16px;');
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #2196f3; font-weight: bold;');
        
        // Viewport info
        console.log('\n%c📐 VIEWPORT', 'color: #9c27b0; font-weight: bold; font-size: 14px;');
        console.log(`   Width: ${window.innerWidth}px`);
        console.log(`   Height: ${window.innerHeight}px`);
        console.log(`   Breakpoint: ${getCurrentBreakpoint()}`);
        console.log(`   Device Pixel Ratio: ${window.devicePixelRatio}`);
        console.log(`   Orientation: ${window.innerWidth > window.innerHeight ? 'landscape' : 'portrait'}`);

        // Page structure
        console.log('\n%c📄 PAGE STRUCTURE', 'color: #9c27b0; font-weight: bold; font-size: 14px;');
        logElement('Document', document.documentElement, 2);
        logElement('Body', document.body, 2);

        // Main containers
        console.log('\n%c📦 MAIN CONTAINERS', 'color: #9c27b0; font-weight: bold; font-size: 14px;');
        const containers = document.querySelectorAll('.container, .container-fluid, [class*="wrapper"], [class*="content"]');
        containers.forEach((el, i) => {
            logElement(`Container ${i + 1}`, el, 2);
        });

        // Tables
        console.log('\n%c📊 TABLES', 'color: #9c27b0; font-weight: bold; font-size: 14px;');
        const tables = document.querySelectorAll('table');
        if (tables.length === 0) {
            console.log('   No tables found');
        } else {
            tables.forEach((table, i) => {
                const wrapper = table.closest('.data-table-container, .table-responsive');
                if (wrapper) {
                    logElement(`Table ${i + 1} Wrapper`, wrapper, 2);
                }
                logElement(`Table ${i + 1}`, table, 2);
            });
        }

        // Summary
        console.log('\n%c📋 SUMMARY', 'color: #9c27b0; font-weight: bold; font-size: 14px;');
        const allElements = document.querySelectorAll('*');
        const overflowElements = Array.from(allElements).filter(el => {
            const info = getElementInfo(el);
            return info.hasOverflow;
        });
        
        if (overflowElements.length > 0) {
            console.log(`%c   ⚠️ ${overflowElements.length} elements with overflow detected!`, 'color: #ff9800; font-weight: bold;');
            overflowElements.forEach((el, i) => {
                const info = getElementInfo(el);
                console.log(`      ${i + 1}. ${getElementIdentifier(el)} (overflow: +${info.overflowAmount}px)`);
            });
        } else {
            console.log('%c   ✓ No overflow issues detected', 'color: #4caf50; font-weight: bold;');
        }

        console.log('\n%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #2196f3; font-weight: bold;');
        console.log('%c💡 TIP: Call window.logElementWidths() to rerun analysis', 'color: #888; font-style: italic;');
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n', 'color: #2196f3; font-weight: bold;');
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(logElementWidths, 1000);
        });
    } else {
        setTimeout(logElementWidths, 1000);
    }

    // Expose globally
    window.logElementWidths = logElementWidths;

    // Window resize tracking with detailed logging
    let resizeTimeout;
    let lastWidth = window.innerWidth;
    let lastHeight = window.innerHeight;
    
    window.addEventListener('resize', () => {
        const newWidth = window.innerWidth;
        const newHeight = window.innerHeight;
        const widthDelta = newWidth - lastWidth;
        const heightDelta = newHeight - lastHeight;
        
        debugLog('WINDOW', `Resized: ${lastWidth}px × ${lastHeight}px → ${newWidth}px × ${newHeight}px (Δ${widthDelta > 0 ? '+' : ''}${widthDelta}px, Δ${heightDelta > 0 ? '+' : ''}${heightDelta}px)`);
        debugLog('WINDOW', `New breakpoint: ${getCurrentBreakpoint()}`);
        
        lastWidth = newWidth;
        lastHeight = newHeight;
        
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            debugLog('WINDOW', 'Resize complete - running element analysis');
            logElementWidths();
        }, 500);
    });

    // Log initial load
    debugLog('WINDOW', `Initial load: ${window.innerWidth}px × ${window.innerHeight}px (${getCurrentBreakpoint()})`);
    console.log('%c🐛 DEBUG MODE ENABLED - All actions will be logged to console', 'color: #00ff00; font-weight: bold; font-size: 14px; background: #000; padding: 5px;');

})();
